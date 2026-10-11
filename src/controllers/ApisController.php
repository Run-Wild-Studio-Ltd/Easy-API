<?php

namespace runwildstudio\easyapi\controllers;

use Cake\Utility\Hash;
use Craft;
use craft\errors\MissingComponentException;
use craft\feedme\models\FeedModel;
use craft\feedme\Plugin as FeedMePlugin;
use craft\feedme\services\Feeds as FeedService;
use runwildstudio\easyapi\models\ApiModel;
use runwildstudio\easyapi\EasyApi;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\web\Controller;
use Exception;
use Throwable;
use yii\base\ExitException;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * @property Plugin $module
 */
class ApisController extends Controller
{
    // Properties
    // =========================================================================

    /**
     * @var string[]
     */
    protected int|bool|array $allowAnonymous = ['run-task'];


    // Public Methods
    // =========================================================================

    /**
     * @return Response
     */
    public function actionApisIndex(): Response
    {
        $variables['apis'] = EasyApi::$plugin->apis->getApis();

        return $this->renderTemplate('easyapi/apis/index', $variables);
    }

    /**
     * @param null $apiId
     * @param null $api
     * @return Response
     */
    public function actionEditApi($apiId = null, $api = null): Response
    {
        $variables = [];

        if (!$api) {
            if ($apiId) {
                $variables['api'] = EasyApi::$plugin->apis->getApiById($apiId);
            } else {
                $variables['api'] = new ApiModel();
            }
        } else {
            $variables['api'] = $api;
        }

        $variables['authTypes'] = EasyApi::$plugin->auth->authTypesList();
        $variables['authTypeClasses'] = EasyApi::$plugin->auth->getRegisteredApiAuthTypes();
        $variables['dataTypes'] = EasyApi::$plugin->data->dataTypesList();
        $variables['elements'] = EasyApi::$plugin->elements->getRegisteredElements();

        return $this->renderTemplate('easyapi/apis/_edit', $variables);
    }

    /**
     * @param null $apiId
     * @return Response
     * @throws \yii\base\Exception
     */
    public function actionRunApi($apiId = null): Response
    {
        $request = Craft::$app->getRequest();

        $api = EasyApi::$plugin->apis->getApiById($apiId);

        $return = $request->getParam('return') ?: 'easyapi';

        $variables['api'] = $api;
        $variables['task'] = $this->_runImportTask($api);

        if ($request->getParam('direct')) {
            $view = $this->getView();
            $view->setTemplateMode($view::TEMPLATE_MODE_CP);
            return $this->renderTemplate('easyapi/apis/_direct', $variables);
        }

        return $this->redirect($return);
    }

    /**
     * @param null $apiId
     * @return Response
     */
    public function actionStatusApi($apiId = null): Response
    {
        $api = EasyApi::$plugin->apis->getApiById($apiId);

        $variables['api'] = $api;

        return $this->renderTemplate('easyapi/apis/_status', $variables);
    }

    /**
     * @return Response|null
     * @throws BadRequestHttpException
     * @throws MissingComponentException
     */
    public function actionSaveApi(): ?Response
    {
        $api = $this->_getModelFromPost();

        if ($api->getErrors()) {
            $this->setFailFlash(Craft::t('easyapi', 'Couldn’t save the api.'));

            // Send the category group back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'api' => $api,
            ]);

            return null;
        }

        return $this->_saveAndRedirect($api, 'easyapi/apis/', true);
    }

    /**
     * @return Response|null
     * @throws BadRequestHttpException
     * @throws MissingComponentException
     */
    public function actionSaveAndElementApi(): ?Response
    {
        $api = $this->_getModelFromPost();

        if ($api->getErrors()) {
            $this->setFailFlash(Craft::t('easyapi', 'Couldn’t save the api.'));

            // Send the category group back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'api' => $api,
            ]);

            return null;
        }

        return $this->_saveAndRedirect($api, 'feed-me/feeds/element/', true, true);
    }

    /**
     * @return Response
     * @throws MissingComponentException
     */
    public function actionSaveAndDuplicateApi(): Response
    {
        $request = Craft::$app->getRequest();

        $apiId = $request->getParam('apiId');
        $api = EasyApi::$plugin->apis->getApiById($apiId);

        EasyApi::$plugin->apis->duplicateApi($api);

        Craft::$app->getSession()->setNotice(Craft::t('easyapi', 'Api duplicated.'));

        return $this->redirect('easyapi/apis');
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @throws \yii\db\Exception
     */
    public function actionDeleteApi(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $apiId = $request->getRequiredBodyParam('id');

        EasyApi::$plugin->apis->deleteApiById($apiId);

        return $this->asJson(['success' => true]);
    }

    /**
     * @throws \yii\base\Exception
     * @throws ExitException
     */
    public function actionRunTask(): void
    {
        $request = Craft::$app->getRequest();

        $apiId = $request->getParam('apiId');

        if ($apiId) {
            $this->actionRunApi($apiId);
        }

        Craft::$app->end();
    }

    /**
     * @return false|string
     * @throws Exception
     */
    public function actionDebug(): bool|string
    {
        $request = Craft::$app->getRequest();

        $apiId = $request->getParam('apiId');
        $limit = $request->getParam('limit');
        $offset = $request->getParam('offset');

        $api = EasyApi::$plugin->apis->getApiById($apiId);

        ob_start();

        // Keep track of processed elements here - particularly for paginated apis
        $processedElementIds = [];

        EasyApi::$plugin->process->debugApi($api, $limit, $offset, $processedElementIds);

        return ob_get_clean();
    }

    /**
     * @return Response
     * @throws Throwable
     * @throws BadRequestHttpException
     */
    public function actionReorderApis(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $apiIds = Json::decode(Craft::$app->getRequest()->getRequiredBodyParam('ids'));
        $apiIds = array_filter($apiIds);
        EasyApi::$plugin->getApis()->reorderApis($apiIds);

        return $this->asJson(['success' => true]);
    }


    // Private Methods
    // =========================================================================

    /**
     * @param $api
     * @return bool|null
     * @throws MissingComponentException
     */
    private function _runImportTask($api): ?bool
    {
        $request = Craft::$app->getRequest();

        $direct = $request->getParam('direct');
        $authorization = $request->getParam('authorization');
        $url = $request->getParam('url');

        $limit = $request->getParam('limit');
        $offset = $request->getParam('offset');

        // Keep track of processed elements here - particularly for paginated apis
        $processedElementIds = [];

        // Are we running from the CP?
        if ($request->getIsCpRequest()) {
            $feedService = new FeedService();
            $feed = $feedService->getFeedById($api->feedId);

            if ($this->_shouldRunFeedImport($api, $feed)) {
                // pushFeedImport() pre-seeds the job's batch data so Queue::push()
                // doesn't run the whole feed fetch inside this request
                if (EasyApi::getInstance()->pushFeedImport($feed, $processedElementIds)) {
                    // Drain the queue in a detached CLI process so the run doesn't
                    // hold this request (and page) open
                    $this->_spawnBackgroundProcess('queue/run');
                }
            } else {
                $this->_spawnBackgroundProcess(sprintf(
                    'easyapi/apis/run-direct %s --url=%s',
                    escapeshellarg((string)$api->id),
                    escapeshellarg((string)$api->apiUrl)
                ));
            }
        }

        // If not, are we running directly?
        if ($direct) {
            // If a custom URL param is provided (for direct-processing), use that instead of stored URL
            if ($url) {
                $api->apiUrl = $url;
            }

            // Create the import task only if provided the correct authorization
            $feedService = new FeedService();
            $feed = $feedService->getFeedById($api->feedId);
            $proceed = $authorization == $feed['passkey'];
                
            if ($proceed) {
                if ($this->_shouldRunFeedImport($api, $feed)) {
                    if (EasyApi::getInstance()->pushFeedImport($feed, $processedElementIds)) {
                        $this->_spawnBackgroundProcess('queue/run');
                    }
                } else {
                    $this->_spawnBackgroundProcess(sprintf(
                        'easyapi/apis/run-direct %s --url=%s',
                        escapeshellarg((string)$api->id),
                        escapeshellarg((string)$api->apiUrl)
                    ));
                }
            }

            return $proceed;
        }

        return null;
    }

    /**
     * Spawn a detached CLI process so long-running work doesn't block the web request.
     */
    private function _spawnBackgroundProcess(string $commandArgs): void
    {
        try {
            // @root is the project root - @app resolves to Craft's own src dir
            $command = sprintf(
                '%s %s %s',
                escapeshellarg($this->_phpCliBinary()),
                escapeshellarg(Craft::getAlias('@root/craft')),
                $commandArgs
            );

            if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
                pclose(popen('start /B "" ' . $command, 'r'));
            } else {
                exec($command . ' > /dev/null 2>&1 &');
            }

            Craft::info('EasyApi: spawned background process: ' . $command, __METHOD__);
        } catch (\Throwable $e) {
            Craft::warning('EasyApi: unable to spawn background process: ' . $e->getMessage(), __METHOD__);
        }
    }

    /**
     * Best-effort resolution of a PHP CLI binary - under php-cgi/php-fpm,
     * PHP_BINARY can't run console commands.
     */
    private function _phpCliBinary(): string
    {
        $binary = PHP_BINARY ?: 'php';
        $base = basename($binary);

        if (str_contains($base, 'cgi') || str_contains($base, 'fpm')) {
            $bindir = PHP_BINDIR . DIRECTORY_SEPARATOR . 'php';
            if (is_executable($bindir)) {
                return $bindir;
            }

            $cli = preg_replace('/php-.+$/', 'php', $binary);
            if ($cli && is_executable($cli)) {
                return $cli;
            }

            return 'php';
        }

        return $binary;
    }

    private function _shouldRunFeedImport($api, ?FeedModel $feed): bool
    {
        if (!$feed || !$feed->getElement()) {
            return false;
        }

        // Apis that do all their work in a postImportHandler have no feed field
        // mapping - a FeedImport job would just fail with "Field mapping not
        // setup." after fetching, so they take the direct path instead
        if (empty($feed->fieldMapping)) {
            return false;
        }

        if (!empty($api->parentElementType) && FeedMePlugin::$plugin !== null) {
            $parentElement = FeedMePlugin::$plugin->elements->getRegisteredElement($api->parentElementType);
            if ($parentElement === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param $api
     * @param $redirect
     * @param false $withId
     * @return Response|null
     * @throws MissingComponentException
     */
    private function _saveAndRedirect($api, $redirect, bool $withId = false, bool $useFeed = false): ?Response
    {
        if (!EasyApi::$plugin->apis->saveApi($api)) {
            Craft::$app->getSession()->setError(Craft::t('easyapi', 'Unable to save api.'));

            Craft::$app->getUrlManager()->setRouteParams([
                'api' => $api,
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('easyapi', 'Api saved.'));

        if ($withId) {
            if ($useFeed) {
                $redirect .= $api->feedId;
            } else {
                $redirect .= $api->id;
            }
        }

        return $this->redirect($redirect);
    }

    /**
     * @return ApiModel
     * @throws BadRequestHttpException
     */
    private function _getModelFromPost(): ApiModel
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        if ($request->getBodyParam('apiId')) {
            $api = EasyApi::$plugin->apis->getApiById($request->getBodyParam('apiId'));
        } else {
            $api = new ApiModel();
        }

        $api->name = $request->getBodyParam('name', $api->name);
        $api->apiUrl = $request->getBodyParam('apiUrl', $api->apiUrl);
        $api->contentType = $request->getBodyParam('contentType', $api->contentType);
        $api->authorizationType = $request->getBodyParam('authorizationType', $api->authorizationType);
        $api->authorization = $request->getBodyParam('authorization', $api->authorization);
        $api->authorizationUrl = $request->getBodyParam('authorizationUrl', $api->authorizationUrl);
        $api->authorizationAppId = $request->getBodyParam('authorizationAppId', $api->authorizationAppId);
        $api->authorizationAppSecret = $request->getBodyParam('authorizationAppSecret', $api->authorizationAppSecret);
        $api->authorizationScope = $request->getBodyParam('authorizationScope', $api->authorizationScope);
        $api->authorizationGrantType = $request->getBodyParam('authorizationGrantType', $api->authorizationGrantType);
        $api->authorizationUsername = $request->getBodyParam('authorizationUsername', $api->authorizationUsername);
        $api->authorizationPassword = $request->getBodyParam('authorizationPassword', $api->authorizationPassword);
        $api->authorizationRedirect = $request->getBodyParam('authorizationRedirect', $api->authorizationRedirect);
        $api->authorizationCode = $request->getBodyParam('authorizationCode', $api->authorizationCode);
        $api->authorizationRefreshToken = $request->getBodyParam('authorizationRefreshToken', $api->authorizationRefreshToken);

        if ($api->authorizationType == 'none') {
            $api->authorizationCustomParameters = '';
        }
        if ($api->authorizationType == 'basic') {
            $api->authorizationCustomParameters = $request->getBodyParam('authorizationCustomParametersBasic', $api->authorizationCustomParameters);
        }
        if ($api->authorizationType == 'oauth') {
            $api->authorizationCustomParameters = $request->getBodyParam('authorizationCustomParameters', $api->authorizationCustomParameters);
        }
        if ($api->authorizationType == 'oauth2') {
            $api->authorizationCustomParameters = $request->getBodyParam('authorizationCustomParameters', $api->authorizationCustomParameters);
        }
        
        $api->httpAction = $request->getBodyParam('httpAction', $api->httpAction);
        $api->direction = $request->getBodyParam('direction', $api->direction);
        $api->requestHeader = $request->getBodyParam('requestHeader', $api->requestHeader);
        $api->requestBody = $request->getBodyParam('requestBody', $api->requestBody);
        $api->siteId = $request->getBodyParam('siteId', $api->siteId);
        $api->parentElementType = $request->getBodyParam('parentElementType', $api->parentElementType);
        $api->parentElementGroup = $request->getBodyParam('parentElementGroup', $api->parentElementGroup);
        $api->parentElementIdField = $request->getBodyParam('parentElementIdField', $api->parentElementIdField);
        $api->parentFilter = $request->getBodyParam('parentFilter', $api->parentFilter);
        $api->offsetField = $request->getBodyParam('offsetField', $api->offsetField);
        $api->offsetUpateURL = $request->getBodyParam('offsetUpateURL', $api->offsetUpateURL);
        $api->offsetTermination = $request->getBodyParam('offsetTermination', $api->offsetTermination);
        $api->queueRequest = $request->getBodyParam('queueRequest', $api->queueRequest);
        $api->useLive = $request->getBodyParam('useLive', $api->useLive);
        $api->feedId = $request->getBodyParam('feedId', $api->feedId);
        //FeedMe fields
        $api->elementType = $request->getBodyParam('elementType', $api->elementType);
        $api->elementGroup = $request->getBodyParam('elementGroup', $api->elementGroup);
        $api->duplicateHandle = $request->getBodyParam('duplicateHandle', $api->duplicateHandle);
        $api->postImportHandler = $request->getBodyParam('postImportHandler', $api->postImportHandler);
        
        if ($request->getBodyParam('queueOrder', $api->queueOrder) != '')
        {
            $api->queueOrder = $request->getBodyParam('queueOrder', $api->queueOrder);
        } else {
            $api->queueOrder = null;
        }

        if (isset($api->elementGroup[$api->elementType])) {
            $elementGroup = $api->elementGroup[$api->elementType];

            if (($api->elementType === 'craft\elements\Category') && empty($elementGroup)) {
                $api->addError('elementGroup', Craft::t('feed-me', 'Category Group is required'));
            }

            if ($api->elementType === 'craft\elements\Entry') {
                if (empty($elementGroup['section']) || empty($elementGroup['entryType'])) {
                    $api->addError('elementGroup', Craft::t('feed-me', 'Entry Section and Type are required'));
                }
            }

            if (($api->elementType === 'craft\commerce\elements\Product') && empty($elementGroup)) {
                $api->addError('elementGroup', Craft::t('feed-me', 'Commerce Product Type is required'));
            }

            if (($api->elementType === 'craft\digitalproducts\elements\Product') && empty($elementGroup)) {
                $api->addError('elementGroup', Craft::t('feed-me', 'Digital Product Group is required'));
            }

            if (($api->elementType === 'craft\elements\Asset') && empty($elementGroup)) {
                $api->addError('elementGroup', Craft::t('feed-me', 'Asset Volume is required'));
            }

            if (($api->elementType === 'craft\elements\Tag') && empty($elementGroup)) {
                $api->addError('elementGroup', Craft::t('feed-me', 'Tag Group is required'));
            }

            if (($api->elementType === 'Solspace\Calendar\Elements\Event') && empty($elementGroup)) {
                $api->addError('elementGroup', Craft::t('feed-me', 'Calendar is required'));
            }
        }

        return $api;
    }
}