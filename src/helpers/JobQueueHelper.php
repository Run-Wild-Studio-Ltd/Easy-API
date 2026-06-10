<?php

namespace runwildstudio\easyapi\helpers;

use Craft;
use craft\feedme\Plugin as FeedMePlugin;
use craft\feedme\models\FeedModel;
use craft\feedme\services\Feeds as FeedService;
use craft\feedme\queue\jobs\FeedImport;
use craft\queue\BaseJob;
use runwildstudio\easyapi\EasyApi;
use runwildstudio\easyapi\services\EasyApiDataTypes;
use runwildstudio\easyapi\console\controllers\ApisController;

class JobQueueHelper extends BaseJob
{
    public function execute($queue): void
    {
        $apis = EasyApi::getInstance()->getApis();
        $settings = EasyApi::$plugin->getSettings();
        $apisController = new ApisController(null, null);

        $processedElementIds = [];
        foreach ($apis->getApis('queueOrder') as $api) {
            if ($api->queueRequest) {
                $feedService = new FeedService();
                $feed = $feedService->getFeedById($api->feedId);

                if ($this->_shouldRunFeedImport($api, $feed)) {
                    EasyApi::getInstance()->module->queue->push(new FeedImport([
                        'feed' => $feed,
                        'limit' => null,
                        'offset' => null,
                        'processedElementIds' => $processedElementIds
                    ]));
                } else {
                    $this->_handleApiDirectImport($api, $api->apiUrl);
                }
            }
        }

        $job = new \runwildstudio\easyapi\helpers\JobQueueHelper([
            'description' => 'Easy API background process',
        ]);

        $delayInSeconds = $settings->jobQueueInterval * 60;
                
        Craft::$app->getQueue()->delay($delayInSeconds)->push($job);
    }

    private function _shouldRunFeedImport($api, FeedModel $feed): bool
    {
        if (!$feed || !$feed->getElement()) {
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

    private function _handleApiDirectImport($api, ?string $apiUrl = null): void
    {
        $effectiveUrl = $apiUrl ?: $api->apiUrl;
        $responseData = EasyApiDataTypes::getRawData($effectiveUrl, $api->id);

        if (!empty($api->postImportHandler)) {
            [$moduleKey, $method] = explode('.', $api->postImportHandler);
            $module = Craft::$app->getModule($moduleKey);
            if ($module && method_exists($module, $method)) {
                $module->$method($responseData['data'] ?? null, $effectiveUrl, $api->id);
            } else {
                Craft::warning("EasyApi: Handler not callable: {$api->postImportHandler}", __METHOD__);
            }
        } else {
            Craft::warning("EasyApi: No postImportHandler configured for unsupported feed import", __METHOD__);
        }
    }
}
