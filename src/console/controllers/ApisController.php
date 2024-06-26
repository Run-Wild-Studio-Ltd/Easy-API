<?php

namespace runwildstudio\easyapi\console\controllers;

use craft\feedme\models\FeedModel;
use craft\feedme\services\Feeds as FeedService;
use craft\feedme\queue\jobs\FeedImport;
use runwildstudio\easyapi\EasyApi;
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
        return $options;
    }

    /**
     * Queues up apis to be processed.
     *
     * @param string|null $apiId A comma-separated list of api IDs to process
     * @return int
     */
    public function actionQueue(string $apiId = null): int
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
        
        EasyApi::getInstance()->module->queue->push(new FeedImport([
            'feed' => $feed,
            'limit' => null,
            'offset' => null,
            'continueOnError' => $continueOnError
        ]));

        $this->stdout('done' . PHP_EOL, Console::FG_GREEN);
    }
}
