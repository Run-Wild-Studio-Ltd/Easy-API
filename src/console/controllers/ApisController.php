<?php

namespace runwildstudio\easyapi\console\controllers;

use Craft;
use craft\feedme\Plugin as FeedMePlugin;
use craft\feedme\services\Feeds as FeedService;
use runwildstudio\easyapi\EasyApi;
use runwildstudio\easyapi\services\EasyApiDataTypes;
use craft\helpers\Console;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * @property Plugin $module
 */
class ApisController extends Controller
{
    // Properties
    // =========================================================================

    /**
     * @var int|null The total number of api items to process.
     */
    public ?int $limit = null;

    /**
     * @var int|null The number of items to skip.
     */
    public ?int $offset = null;

    /**
     * @var bool Whether to continue processing an api (and subsequent pages) if an error occurs.
     * @since 4.3.0
     */
    public bool $continueOnError = false;

    /**
     * @var bool Whether to process all apis. If this is true, the limit and offset params are ignored.
     */
    public bool $all = false;

    /**
     * @var string|null A URL to use instead of the api's stored URL.
     */
    public ?string $url = null;

    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);
        $options[] = 'limit';
        $options[] = 'offset';
        $options[] = 'continueOnError';
        $options[] = 'all';
        $options[] = 'url';
        return $options;
    }

    /**
     * Queues up apis to be processed.
     *
     * @param string|null $apiId A comma-separated list of api IDs to process
     * @return int
     */
    public function actionQueue(?string $apiId = null): int
    {
        $apis = EasyApi::getInstance()->getApis();
        $tally = 0;

        if ($this->all) {
            foreach ($apis->getApis() as $api) {
                $this->queueApi($api, null, null, $this->continueOnError);
                $tally++;
            }
        } elseif ($apiId) {
            $ids = explode(',', $apiId);

            foreach ($ids as $id) {
                $api = $apis->getApiById($id);

                if (!$api) {
                    $this->stderr("Invalid api ID: $id" . PHP_EOL, Console::FG_RED);
                    continue;
                }

                $this->queueApi($api, $this->limit, $this->offset, $this->continueOnError);
                $tally++;
            }
        }

        if ($tally) {
            $this->stdout(($tally === 1 ? '1 api' : "$tally apis") . ' queued up to be processed.' . PHP_EOL, Console::FG_GREEN);
        } else {
            $this->stdout('Could not determine the apis to process.' . PHP_EOL, Console::FG_GREEN);
        }

        return ExitCode::OK;
    }

    /**
     * Runs a direct (non-Feed-Me) api import in the current process.
     * Intended to be spawned in the background by web requests so the
     * import doesn't block the page.
     *
     * @param string|null $apiId The api ID to process
     * @return int
     */
    public function actionRunDirect(?string $apiId = null): int
    {
        $api = EasyApi::getInstance()->getApis()->getApiById($apiId);

        if (!$api) {
            $this->stderr("Invalid api ID: $apiId" . PHP_EOL, Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $url = $this->url ?: $api->apiUrl;

        // If the api is backed by a Feed Me feed, run the fetch through Feed Me so
        // the EVENT_BEFORE_FETCH_FEED handler applies the full EasyApi pipeline
        // (parent-element loop, per-parent postImportHandler, pagination).
        if ($api->feedId && FeedMePlugin::$plugin !== null) {
            $feed = (new FeedService())->getFeedById($api->feedId);

            if ($feed) {
                if ($this->url) {
                    $feed->feedUrl = $url;
                }

                $response = FeedMePlugin::$plugin->data->getFeedData($feed);

                if (empty($response['success'])) {
                    $this->stderr('Feed fetch failed: ' . ($response['error'] ?? 'unknown error') . PHP_EOL, Console::FG_RED);
                    return ExitCode::UNAVAILABLE;
                }

                $this->stdout('Import complete.' . PHP_EOL, Console::FG_GREEN);
                return ExitCode::OK;
            }
        }

        // No feed - fetch the raw URL and run the post-import handler directly
        $responseData = EasyApiDataTypes::getRawData($url, $api->id);

        if (!empty($api->postImportHandler)) {
            [$moduleKey, $method] = explode('.', $api->postImportHandler);
            $module = Craft::$app->getModule($moduleKey);
            if ($module && method_exists($module, $method)) {
                $module->$method($responseData['data'] ?? null, $url, $api->id);
            } else {
                $this->stderr("Handler not callable: {$api->postImportHandler}" . PHP_EOL, Console::FG_RED);
            }
        } else {
            $this->stdout('No postImportHandler configured; data fetched only.' . PHP_EOL, Console::FG_YELLOW);
        }

        return ExitCode::OK;
    }

    /**
     * Push an api to the queue to be processed.
     *
     * @param      $api
     * @param null $limit
     * @param null $offset
     * @param bool $continueOnError
     */
    protected function queueApi($api, $limit = null, $offset = null, bool $continueOnError = false): void
    {
        $this->stdout('Queuing up feed ');
        $this->stdout($api->name, Console::FG_CYAN);
        $this->stdout(' ... ');

        $feedService = new FeedService();
        $feed = $feedService->getFeedById($api->feedId);

        // pushFeedImport() pre-seeds the job's batch data so Queue::push() doesn't
        // run the whole feed fetch just to build the job description
        EasyApi::getInstance()->pushFeedImport($feed, null, $continueOnError);

        $this->stdout('done' . PHP_EOL, Console::FG_GREEN);
    }
}
