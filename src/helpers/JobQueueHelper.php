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

        // If a daily run time is configured, only run once per day at/after
        // that time — reschedule for the next occurrence otherwise. A
        // last-run marker gives catch-up behaviour: if the worker was down
        // at the scheduled time it still runs once it comes back.
        $todayRun = $this->_scheduledRunToday($settings);
        if ($todayRun !== null) {
            $lastRun = (int)Craft::$app->getCache()->get('easyapi-jobqueue-lastrun', 0);
            if (time() < $todayRun || $lastRun >= $todayRun) {
                $this->_reschedule($queue, $this->_secondsUntilNextRun($todayRun));
                return;
            }
        }

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

        if ($todayRun !== null) {
            Craft::$app->getCache()->set('easyapi-jobqueue-lastrun', time(), 172800);
        }

        $delayInSeconds = $todayRun !== null
            ? $this->_secondsUntilNextRun($todayRun)
            : $settings->jobQueueInterval * 60;

        $this->_reschedule($queue, $delayInSeconds);
    }

    /**
     * Seconds until the next daily occurrence of today's scheduled run
     * timestamp (tomorrow's if today's has passed).
     */
    private function _secondsUntilNextRun(int $todayRun): int
    {
        $next = $todayRun > time() ? $todayRun : $todayRun + 86400;
        return max(60, $next - time());
    }

    /**
     * Timestamp of today's configured jobQueueTime occurrence, or null when
     * no valid daily time is set (interval scheduling applies instead).
     */
    private function _scheduledRunToday($settings): ?int
    {
        if (empty($settings->jobQueueTime) || !preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', trim($settings->jobQueueTime))) {
            return null;
        }

        [$hour, $minute] = array_map('intval', explode(':', trim($settings->jobQueueTime)));
        $tz = new \DateTimeZone(Craft::$app->getTimeZone());

        return (new \DateTime('today', $tz))->setTime($hour, $minute)->getTimestamp();
    }

    private function _reschedule($queue, int $delayInSeconds): void
    {
        $job = new self([
            'description' => 'Easy API background process',
        ]);

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
