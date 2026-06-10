<?php

namespace runwildstudio\easyapi\services;

use Cake\Utility\Hash;
use Craft;
use craft\base\Component;
use craft\db\ActiveQuery;
use craft\db\Query;
use craft\feedme\services\Feeds as FeedService;
use craft\feedme\models\FeedModel;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use runwildstudio\easyapi\EasyApi;
use runwildstudio\easyapi\errors\ApiException;
use runwildstudio\easyapi\events\ApiEvent;
use runwildstudio\easyapi\models\ApiModel;
use runwildstudio\easyapi\records\ApiRecord;
use Exception;
use Throwable;

/**
 *
 * @property-read mixed $totalApis
 */
class Apis extends Component
{
    // Properties
    // =========================================================================

    /**
     * @var array
     */
    private array $_overrides = [];

    // Constants
    // =========================================================================

    public const EVENT_BEFORE_SAVE_API = 'onBeforeSaveApi';
    public const EVENT_AFTER_SAVE_API = 'onAfterSaveApi';


    // Public Methods
    // =========================================================================

    /**
     * @param null $orderBy
     * @return array
     */
    public function getApis($orderBy = null): array
    {
        $query = $this->_getQuery();

        if ($orderBy) {
            $query->orderBy($orderBy);
        }

        $results = $query->all();

        foreach ($results as $key => $result) {
            $results[$key] = $this->_createModelFromRecord($result);
        }

        return $results;
    }

    /**
     * @return int
     */
    public function getTotalApis(): int
    {
        return count($this->getApis());
    }

    /**
     * @param $apiId
     * @return ApiModel|null
     */
    public function getApiById($apiId): ?ApiModel
    {
        $result = $this->_getQuery()
            ->where(['id' => $apiId])
            ->one();

        return $this->_createModelFromRecord($result);
    }

    /**
     * @param $apiId
     * @return ApiModel|null
     */
    public function getApiByFeedId($feedId): ?ApiModel
    {
        $result = $this->_getQuery()
            ->where(['feedId' => $feedId])
            ->one();

        return $this->_createModelFromRecord($result);
    }

    /**
     * @param ApiModel $model
     * @param bool $runValidation
     * @return bool
     * @throws Exception
     */
public function saveApi(ApiModel $model, bool $runValidation = true): bool
    {
        $isNewModel = !$model->id;

        // Fire a 'beforeSaveApi' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_API)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_API, new ApiEvent([
                'api' => $model,
                'isNew' => $isNewModel,
            ]));
        }

        if ($runValidation && !$model->validate()) {
            Craft::info('Api not saved due to validation error.', __METHOD__);
            return false;
        }

        if ($isNewModel) {
            $record = new ApiRecord();
        } else {
            $record = ApiRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('easyapi', 'No api exists with the ID “{id}”', ['id' => $model->id]));
            }
        }

        if (empty($model->feedId)) {
            $feedRecord = $this->_createFeedRecord($model);
            $model->feedId = $feedRecord->id;
        } else {
            $this->_updateFeedRecord($model);
        }

        $record->name = $model->name;
        $record->apiUrl = $model->apiUrl;
        $record->contentType = $model->contentType;
        $record->authorizationType = $model->authorizationType;
        $record->authorizationUrl = $model->authorizationUrl;
        $record->authorizationAppId = $model->authorizationAppId;
        $record->authorizationAppSecret = $model->authorizationAppSecret;
        $record->authorizationScope = $model->authorizationScope;
        $record->authorizationGrantType = $model->authorizationGrantType;
        $record->authorizationUsername = $model->authorizationUsername;
        $record->authorizationPassword = $model->authorizationPassword;
        $record->authorizationRedirect = $model->authorizationRedirect;
        $record->authorizationCode = $model->authorizationCode;
        $record->authorizationRefreshToken = $model->authorizationRefreshToken;
        $record->authorizationCustomParameters = $model->authorizationCustomParameters;
        $record->authorization = $model->authorization;
        $record->httpAction = $model->httpAction;
        $record->updateElementIdField = $model->updateElementIdField;
        $record->direction = $model->direction;
        $record->requestHeader = $model->requestHeader;
        $record->requestBody = $model->requestBody;
        $record->siteId = $model->siteId;
        $record->parentElementType = $model->parentElementType;
        $record->parentElementGroup = $model->parentElementGroup;
        $record->parentElementIdField = $model->parentElementIdField;
        $record->parentFilter = $model->parentFilter;
        $record->offsetField = $model->offsetField;
        $record->offsetUpateURL = $model->offsetUpateURL;
        $record->offsetTermination = $model->offsetTermination;
        $record->queueRequest = $model->queueRequest;
        $record->queueOrder = $model->queueOrder;
        $record->useLive = $model->useLive;
        $record->feedId = $model->feedId;

        if ($model->parentElementGroup) {
            $record->setAttribute('parentElementGroup', Json::encode($model->parentElementGroup));
        }

        if ($isNewModel) {
            $maxSortOrder = (new Query())
                ->from(['{{%easyapi_apis}}'])
                ->max('[[sortOrder]]');

            $record->sortOrder = 1; //$maxSortOrder ? $maxSortOrder + 1 : 1;
        }

        //Feed Me required fields
        $record->elementType = $model->elementType;
        $record->duplicateHandle = $model->duplicateHandle;
        if ($model->elementGroup) {
            $record->setAttribute('elementGroup', Json::encode($model->elementGroup));
        }
        $record->postImportHandler = $model->postImportHandler;
        
        $record->save(false);

        if (!$model->id) {
            $model->id = $record->id;
        }

        // Fire an 'afterSaveApi' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_API)) {
            $this->trigger(self::EVENT_AFTER_SAVE_API, new ApiEvent([
                'api' => $model,
                'isNew' => $isNewModel,
            ]));
        }

        return true;
    }

    /**
     * @param $apiId
     * @return int
     * @throws \yii\db\Exception
     */
    public function deleteApiById($apiId): int
    {
        $apiRecord = $this->_getApiRecordById($apiId);
        $feedId = $apiRecord->feedId;
        $this->_deleteFeed($feedId);
        return Craft::$app->getDb()->createCommand()
            ->delete('{{%easyapi_apis}}', ['id' => $apiId])
            ->execute();
    }

    /**
     * @param $api
     * @return bool
     * @throws Exception
     */
    public function duplicateApi($api): bool
    {
        $api->id = null;

        //Duplicate the Feed as well.
        $feedService = new FeedService();
        $feedModel = $feedService->getFeedById($api->feedId);
        $feedService->duplicateFeed($feedModel);
        $api->feedId = $feedModel->id;

        return $this->saveApi($api);
    }

    /**
     * @param $handle
     * @param $apiId
     * @return mixed|null
     */
    public function getModelOverrides($handle, $apiId): mixed
    {
        if (empty($this->_overrides[$apiId])) {
            $this->_overrides[$apiId] = Hash::get(Craft::$app->getConfig()->getConfigFromFile('easyapi'), 'apiOptions.' . $apiId);
        }

        return $this->_overrides[$apiId][$handle] ?? null;
    }

    /**
     * @param array $apiIds
     * @return bool
     * @throws Throwable
     * @throws \yii\db\Exception
     */
    public function reorderApis(array $apiIds): bool
    {
        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            foreach ($apiIds as $apiOrder => $apiId) {
                $apiRecord = $this->_getApiRecordById($apiId);
                $apiRecord->sortOrder = $apiOrder + 1;
                $apiRecord->save();
            }

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }

        return true;
    }


    // Private Methods
    // =========================================================================

    /**
     * @return ActiveQuery
     */
    private function _getQuery(): ActiveQuery
    {
        return ApiRecord::find()
            ->select([
                'id',
                'name',
                'apiUrl',
                'contentType',
                'authorizationType',
                'authorizationUrl',
                'authorizationAppId',
                'authorizationAppSecret',
                'authorizationScope',
                'authorizationGrantType',
                'authorizationUsername',
                'authorizationPassword',
                'authorizationRedirect',
                'authorizationCode',
                'authorizationRefreshToken',
                'authorizationCustomParameters',
                'authorization',
                'httpAction',
                'requestHeader',
                'requestBody',
                'siteId',
                'sortOrder',
                'parentElementType',
                'parentElementGroup',
                'parentElementIdField',
                'parentFilter',
                'offsetField',
                'offsetUpateURL',
                'offsetTermination',
                'queueRequest',
                'queueOrder',
                'useLive',
                'dateCreated',
                'dateUpdated',
                'uid',
                'feedId',
                //Feed Me Fields
                'elementType',
                'elementGroup',
                'duplicateHandle',
                'postImportHandler',
            ])
            ->orderBy(['sortOrder' => SORT_ASC]);
    }

    /**
     * @param ApiRecord|null $record
     * @return ApiModel|null
     */
    private function _createModelFromRecord(ApiRecord $record = null): ?ApiModel
    {
        if (!$record) {
            return null;
        }

        $attributes = $record->toArray();

        foreach ($attributes as $attribute => $value) {
            $override = $this->getModelOverrides($attribute, $record['id']);

            if ($override) {
                $attributes[$attribute] = $override;
            }
        }

        return new ApiModel($attributes);
    }

    /**
     * @param int|null $apiId
     * @return ApiRecord
     * @throws Exception
     */
    private function _getApiRecordById(int $apiId = null): ApiRecord
    {
        if ($apiId !== null) {
            $apiRecord = ApiRecord::findOne(['id' => $apiId]);

            if (!$apiRecord) {
                throw new ApiException(Craft::t('easyapi', 'No api exists with the ID “{id}”.', ['id' => $apiId]));
            }
        } else {
            $apiRecord = new ApiRecord();
        }

        return $apiRecord;
    }

    private function _createFeedRecord($model): FeedModel
    {
        $feedService = new FeedService();

        // Create a new FeedMe feed record
        $feedModel = new FeedModel();
        $feedModel->name = $model->name . '-' . EasyApi::$plugin->getPluginName();
        $feedModel->feedUrl = $model->apiUrl;
        $feedModel->feedType = $model->contentType;
        $feedModel->elementType = $model->elementType;
        $feedModel->elementGroup = $model->elementGroup;
        $feedModel->duplicateHandle = $model->duplicateHandle;
        $feedModel->passkey = StringHelper::randomString(10);
        $feedModel->setEmptyValues = false;
        $feedModel->backup = false;

        // Validate and save the feed record
        if (!$feedModel->validate()) {
            // Handle validation errors
        }

        if (!$feedService->saveFeed($feedModel)) {
            // Handle save errors
        }

        // Feed record created successfully
        return $feedModel;
    }

    private function _updateFeedRecord($model)
    {
        $feedService = new FeedService();
        $feedModel = $feedService->getFeedById($model->feedId);

        // Update the FeedMe feed record
        $feedModel->name = $model->name . '-' . EasyApi::$plugin->getPluginName();
        $feedModel->feedUrl = $model->apiUrl;
        $feedModel->feedType = $model->contentType;
        $feedModel->elementType = $model->elementType;
        $feedModel->elementGroup = $model->elementGroup;
        $feedModel->duplicateHandle = $model->duplicateHandle;

        // Validate and save the feed record
        if (!$feedModel->validate()) {
            // Handle validation errors
        }

        if (!$feedService->saveFeed($feedModel)) {
            // Handle save errors
        }
    }

    private function _deleteFeed($feedId)
    {
        $feedService = new FeedService();

        // Retrieve the feed record by its ID
        $feedModel = $feedService->getFeedById($feedId);

        if ($feedModel) {
            // Delete the feed record
            if (!$feedService->deleteFeedById($feedId)) {
                // Handle delete errors
            }
        } else {
            // Handle case where feed record with given ID is not found
        }
    }
}
