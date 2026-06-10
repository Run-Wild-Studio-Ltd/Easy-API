<?php

namespace runwildstudio\easyapi\services;

use ArrayAccess;
use Cake\Utility\Hash;
use Craft;
use craft\base\Component;
use craft\errors\MissingComponentException;
use runwildstudio\easyapi\base\AuthTypeInterface;
use runwildstudio\easyapi\authtypes\basic;
use runwildstudio\easyapi\authtypes\none;
use runwildstudio\easyapi\authtypes\oauth;
use runwildstudio\easyapi\authtypes\oauth2;
use runwildstudio\easyapi\events\ApiAuthEvent;
use runwildstudio\easyapi\events\RegisterEasyApiAuthTypesEvent;
use runwildstudio\easyapi\models\ApiModel;
use runwildstudio\easyapi\EasyApi;
use craft\helpers\Component as ComponentHelper;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use yii\base\Event;
use yii\base\InvalidConfigException;

/**
 *
 * @property-read mixed $registeredAuthTypes
 */
class EasyApiAuthTypes extends Component
{
    // Constants
    // =========================================================================

    public const EVENT_REGISTER_EASY_API_AUTH_TYPES = 'registerEasyApiAuthTypes';

    // Properties
    // =========================================================================

    /**
     * @var array
     */
    private array $_authTypes = [];

    /**
     * @var
     */
    private mixed $_headers = null;

    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function init(): void
    {
        parent::init();

        foreach ($this->getRegisteredApiAuthTypes() as $authTypeClass) {
            $authType = $this->createAuthType($authTypeClass);

            // Does this data type exist in Craft right now?
            if (!class_exists($authType->getClass())) {
                continue;
            }

            // strtolower for backwards compatibility
            $handle = strtolower($authType::displayName());

            $this->_authTypes[$handle] = $authType;
        }
    }

    /**
     * @return array
     */
    public function authTypesList(): array
    {
        $list = [];

        foreach ($this->_authTypes as $handle => $authType) {
            $list[$handle] = $authType::$name;
        }

        return $list;
    }

    /**
     * @param $handle
     * @return mixed|null
     */
    public function getRegisteredApiAuthType($handle): mixed
    {
        return $this->_authTypes[$handle] ?? null;
    }

    /**
     * @return array
     */
    public function getRegisteredApiAuthTypes(): array
    {
        if (count($this->_authTypes)) {
            return $this->_authTypes;
        }

        $event = new RegisterEasyApiAuthTypesEvent([
            'authTypes' => [
                none::class,
                basic::class,
                oauth::class,
                oauth2::class,
            ],
        ]);

        $this->trigger(self::EVENT_REGISTER_EASY_API_AUTH_TYPES, $event);

        return $event->authTypes;
    }

    /**
     * @param $config
     * @return AuthTypeInterface
     * @throws InvalidConfigException
     */
    public function createAuthType($config): AuthTypeInterface
    {
        if (is_string($config)) {
            $config = ['type' => $config];
        }

        try {
            $authType = ComponentHelper::createComponent($config, AuthTypeInterface::class);
        } catch (MissingComponentException $e) {
            $config['errorMessage'] = $e->getMessage();
            $config['expectedType'] = $config['type'];
            unset($config['type']);

            $authType = new MissingAuthType($config);
        }

        /** @var AuthTypeInterface $authType */
        return $authType;
    }

    /**
     * @param $url
     * @param $value
     * @param $duration
     * @return void
     */
    private function _setCache($url, $value, $duration): void
    {
        Craft::$app->cache->set(base64_encode(urlencode($url)), $value, $duration, null);
    }

    /**
     * @param $url
     * @return mixed
     */
    private function _getCache($url): mixed
    {
        return Craft::$app->cache->get(base64_encode(urlencode($url)));
    }
}
