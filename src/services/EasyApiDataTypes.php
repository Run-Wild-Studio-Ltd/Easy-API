<?php

namespace runwildstudio\easyapi\services;

use ArrayAccess;
use Cake\Utility\Hash;
use Craft;
use craft\base\Component;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\GlobalSet;
use craft\elements\Tag;
use craft\errors\MissingComponentException;
use runwildstudio\easyapi\base\DataTypeInterface;
use runwildstudio\easyapi\datatypes\Json;
use runwildstudio\easyapi\datatypes\Xml;
use runwildstudio\easyapi\events\ApiDataEvent;
use runwildstudio\easyapi\events\RegisterEasyApiDataTypesEvent;
use runwildstudio\easyapi\models\ApiModel;
use runwildstudio\easyapi\EasyApi;
use craft\helpers\Component as ComponentHelper;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use yii\base\Event;
use yii\base\InvalidConfigException;

/**
 *
 * @property-read mixed $registeredDataTypes
 */
class EasyApiDataTypes extends Component
{
    public const EVENT_REGISTER_EASY_API_DATA_TYPES = 'registerEasyApiDataTypes';
    public const EVENT_BEFORE_FETCH_API = 'onBeforeFetchApi';
    public const EVENT_AFTER_FETCH_API = 'onAfterFetchApi';
    public const EVENT_AFTER_PARSE_API = 'onAfterParseApi';

    private array $_dataTypes = [];
    private mixed $_headers = null;

    public function init(): void
    {
        parent::init();

        foreach ($this->getRegisteredApiDataTypes() as $dataTypeClass) {
            $dataType = $this->createDataType($dataTypeClass);

            if (!class_exists($dataType->getClass())) {
                continue;
            }

            $handle = strtolower($dataType::displayName());
            $this->_dataTypes[$handle] = $dataType;
        }
    }

    public function dataTypesList(): array
    {
        $list = [];
        foreach ($this->_dataTypes as $handle => $dataType) {
            $list[$handle] = $dataType::$name;
        }
        return $list;
    }

    public function getRegisteredApiDataType($handle): mixed
    {
        return $this->_dataTypes[$handle] ?? null;
    }

    public function getRegisteredApiDataTypes(): array
    {
        $event = new RegisterEasyApiDataTypesEvent([
            'dataTypes' => [
                Json::class,
                Xml::class,
            ],
        ]);

        $this->trigger(self::EVENT_REGISTER_EASY_API_DATA_TYPES, $event);

        return $event->dataTypes;
    }

    public function createDataType($config): DataTypeInterface
    {
        if (is_string($config)) {
            $config = ['type' => $config];
        }

        try {
            $dataType = ComponentHelper::createComponent($config, DataTypeInterface::class);
        } catch (MissingComponentException $e) {
            $config['errorMessage'] = $e->getMessage();
            $config['expectedType'] = $config['type'];
            unset($config['type']);

            $dataType = new MissingDataType($config);
        }

        return $dataType;
    }

    /**
     * Fetch raw data from API (GET only)
     */
    public static function getRawData($url, $apiId = null): array
    {
        $event = new ApiDataEvent([
            'url' => $url,
            'apiId' => $apiId,
        ]);

        Event::trigger(static::class, self::EVENT_BEFORE_FETCH_API, $event);

        if ($event->response) {
            return $event->response;
        }

        $url = $event->url;
        $url = Craft::getAlias($url);

        $api = EasyApi::$plugin->apis->getApiById($apiId);
        if (!$url) {
            $url = $api->apiUrl;
        }

        // Get auth headers
        $auth = $api->getAuthType()->getAuthValue($api);
        Craft::info('Raw auth response: ' . print_r($auth, true), __METHOD__);

        if (!$auth['success']) {
            $response = ['success' => false, 'error' => $auth['error']];
            Craft::$app->getErrorHandler()->logException($auth['error']);
            return $response;
        }

        try {
            $curl = curl_init();

            // Build proper headers
            $curlHeaders = [
                'Content-Type: application/' . $api->contentType,
                'Accept: application/json',
                'Authorization: ' . $auth['value'],  // ensure "Authorization: Bearer ..."
            ];

            // Log the full curl command for debugging
            $curlCommand = 'curl -X GET \'' . $url . '\'';
            foreach ($curlHeaders as $h) {
                $curlCommand .= ' -H \'' . $h . '\'';
            }
            Craft::info('Executing cURL: ' . $curlCommand, __METHOD__);

            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_HTTPHEADER => $curlHeaders,
            ]);

            $data = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            // Log the raw API response and HTTP code
            //Craft::info('Raw API response: ' . $data, __METHOD__);
            //Craft::info('Raw API HTTP code: ' . $httpCode, __METHOD__);

            if ($httpCode >= 400) {
                $response = ['success' => false, 'error' => "HTTP $httpCode"];
            } else {
                $response = ['success' => true, 'data' => $data];
            }
        } catch (Exception $e) {
            $response = ['success' => false, 'error' => $e->getMessage()];
            Craft::$app->getErrorHandler()->logException($e);
        }

        return self::_triggerEventAfterFetchApi([
            'url' => $url,
            'apiId' => $apiId,
            'response' => $response,
        ]);
    }

    public function getApiData($apiModel, bool $usePrimaryElement = true): mixed
    {
        $apiDataResponse = $apiModel->getDataType()->getApi($apiModel->apiUrl, $apiModel, $usePrimaryElement);

        $event = new ApiDataEvent([
            'url' => $apiModel->apiUrl,
            'response' => $apiDataResponse,
            'apiId' => $apiModel->id,
        ]);

        Event::trigger(static::class, self::EVENT_AFTER_PARSE_API, $event);

        return $event->response;
    }

    public function getApiForTemplate(array $options = []): mixed
    {
        $pluginSettings = EasyApi::$plugin->getSettings();

        $url = Hash::get($options, 'url');
        $type = Hash::get($options, 'type');
        $element = Hash::get($options, 'element');
        $cache = Hash::get($options, 'cache', true);

        $limit = Hash::get($options, 'limit');
        $offset = Hash::get($options, 'offset');

        $headers = Hash::get($options, 'headers');

        $cacheId = ($headers) ? $url . '#' . $element : $url . '#headers-' . $element;

        if (!$url || !$type) {
            return [];
        }

        $api = new ApiModel();
        $api->apiUrl = $url;
        $api->contentType = $type;

        if ($element) {
            $api->primaryElement = $element;
        }

        if ($cache === false) {
            if ($headers) {
                $data = $this->_headers;
            } else {
                $data = Hash::get($this->getApiData($api), 'data');
            }

            if ($offset) $data = array_slice($data, $offset);
            if ($limit) $data = array_slice($data, 0, $limit);

            return $data;
        }

        if (is_numeric($cache) || $cache === true) {
            $cache = (is_numeric($cache)) ? $cache : $pluginSettings->cache;

            $cachedRequest = $this->_getCache($cacheId);

            if ($cachedRequest) return $cachedRequest;

            if ($headers) {
                $data = $this->_headers;
            } else {
                $data = Hash::get($this->getApiData($api), 'data');
            }

            if ($offset) $data = array_slice($data, $offset);
            if ($limit) $data = array_slice($data, 0, $limit);

            $this->_setCache($cacheId, $data, $cache);
            return $data;
        }

        return [];
    }

    private function _setCache($url, $value, $duration): void
    {
        Craft::$app->cache->set(base64_encode(urlencode($url)), $value, $duration, null);
    }

    private function _getCache($url): mixed
    {
        return Craft::$app->cache->get(base64_encode(urlencode($url)));
    }

    private static function _triggerEventAfterFetchApi($data)
    {
        $event = new ApiDataEvent($data);
        Event::trigger(static::class, self::EVENT_AFTER_FETCH_API, $event);
        return $event->response;
    }
}
