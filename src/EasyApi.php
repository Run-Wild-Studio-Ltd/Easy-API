<?php

namespace runwildstudio\easyapi;

use Craft;
use craft\base\Model;
use craft\events\RegisterUrlRulesEvent;
use craft\feedme\datatypes\DataBatcher;
use craft\feedme\events\FeedDataEvent;
use craft\feedme\events\FeedEvent;
use craft\feedme\models\FeedModel;
use craft\feedme\Plugin as FeedMePlugin;
use craft\feedme\queue\jobs\FeedImport;
use craft\feedme\services\DataTypes;
use craft\feedme\services\Feeds;
use craft\feedme\services\Process as FeedMeProcess;
use craft\helpers\UrlHelper;
use craft\queue\BaseBatchedJob;
use craft\services\Plugins;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use runwildstudio\easyapi\base\PluginTrait;
use runwildstudio\easyapi\models\Settings;
use runwildstudio\easyapi\services\Apis;
use runwildstudio\easyapi\services\EasyApiAuthTypes;
use runwildstudio\easyapi\services\EasyApiDataTypes;
use runwildstudio\easyapi\services\Elements;
use runwildstudio\easyapi\services\FeedMeEvents;
use runwildstudio\easyapi\services\Fields;
use runwildstudio\easyapi\services\Logs;
use runwildstudio\easyapi\services\Process;
use runwildstudio\easyapi\services\Service;
use runwildstudio\easyapi\web\twig\Extension;
use runwildstudio\easyapi\web\twig\variables\EasyApiVariable;
use yii\base\Event;
use yii\di\Instance;
use yii\queue\Queue;

/**
 * Class EasyApi
 *
 * @property-read EasyApiDataTypes $data
 * @property-read Elements $elements
 * @property-read Apis $apis
 * @property-read Fields $fields
 * @property-read Logs $logs
 * @property-read Process $process
 * @property-read void $settingsResponse
 * @property-read mixed $pluginName
 * @property-read mixed $cpNavItem
 * @property-read Service $service
 * @property-read Settings $settings
 * @method Settings getSettings()
 */
class EasyApi extends \craft\base\Plugin
{
    use PluginTrait;

    /**
     * @inheritdoc
     */
    public static function config(): array
    {
        return [
            'components' => [
                'auth' => ['class' => EasyApiAuthTypes::class],
                'data' => ['class' => EasyApiDataTypes::class],
                'elements' => ['class' => Elements::class],
                'apis' => ['class' => Apis::class],
                'fields' => ['class' => Fields::class],
                'logs' => ['class' => Logs::class],
                'process' => ['class' => Process::class],
                'service' => ['class' => Service::class],
                'feedme' => ['class' => FeedMeEvents::class],
            ],
        ];
    }

    public string $minVersionRequired = '4.4.0';
    public string $schemaVersion = '5.1.1.0';
    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;

    /**
     * @var Queue|array|string
     */
    public $queue = 'queue';

    /**
     * @inheritDoc
     */
    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        $this->queue = Instance::ensure($this->queue, Queue::class);

        $this->_registerCpRoutes();
        $this->_registerTwigExtensions();
        $this->_registerVariables();
        $this->_registerFeedMeEvents();
        $this->_registerFeedMeDefaults();
    }

    /**
     * @inheritDoc
     */
    public function afterInstall(): void
    {
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            return;
        }

        Craft::$app->controller->redirect(UrlHelper::cpUrl('easyapi/welcome'))->send();
    }

    /**
     * @inheritDoc
     */
    public function getSettingsResponse(): mixed
    {
        return Craft::$app->controller->redirect(UrlHelper::cpUrl('easyapi/settings'));
    }

    public function getPluginName(): string
    {
        return Craft::t('easyapi', $this->getSettings()->pluginName);
    }

    /**
     * Pushes a Feed Me import job to the queue without triggering a feed fetch.
     *
     * Queue::push() calls the job's getDescription(), and BaseBatchedJob::getDescription()
     * calls totalBatches() -> loadData(), which fetches the entire feed (including the
     * EasyApi parent loop) synchronously in whatever process pushes the job. Pre-seeding
     * the job's private _data batcher keeps push() cheap; _data is excluded from
     * serialization (BaseBatchedJob::__sleep() covers public props only), so the queue
     * worker still fetches the real data in execute().
     *
     * @param FeedModel $feed
     * @param array|null $processedElementIds
     * @param bool $continueOnError
     * @return string|null The queued job ID
     */
    public function pushFeedImport(FeedModel $feed, ?array $processedElementIds = null, bool $continueOnError = true): ?string
    {
        $job = new FeedImport([
            'feed' => $feed,
            'limit' => null,
            'offset' => null,
            'processedElementIds' => $processedElementIds,
            'continueOnError' => $continueOnError,
        ]);

        $dataProp = new \ReflectionProperty(BaseBatchedJob::class, '_data');
        $dataProp->setValue($job, new DataBatcher([]));

        // Prioritise the job (lower values reserve first) so it isn't stuck
        // behind routine backlog - e.g. revision pruning - when the queue drains
        self::$plugin->queue->priority(10);

        return self::$plugin->queue->push($job);
    }

    /**
     * @inheritDoc
     */
    public function getCpNavItem(): ?array
    {
        $navItem = parent::getCpNavItem();
        $navItem['label'] = $this->getPluginName();

        return $navItem;
    }

    /**
     * @inheritDoc
     */
    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    /**
     *
     */
    private function _registerTwigExtensions(): void
    {
        Craft::$app->view->registerTwigExtension(new Extension());
    }

    /**
     *
     */
    private function _registerCpRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules = array_merge($event->rules, [
                'easyapi' => '',
                'easyapi/apis' => 'easyapi/apis/apis-index',
                'easyapi/apis/new' => 'easyapi/apis/edit-api',
                'easyapi/apis/<apiId:\d+>' => 'easyapi/apis/edit-api',
                'easyapi/apis/run/<apiId:\d+>' => 'easyapi/apis/run-api',
                'easyapi/apis/status/<apiId:\d+>' => 'easyapi/apis/status-api',
                'easyapi/logs' => 'easyapi/logs/logs',
                'easyapi/settings' => 'easyapi/base/settings',
            ]);
        });
    }

    /**
     *
     */
    private function _registerVariables(): void
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function(Event $event) {
            $event->sender->set('easyApi', EasyApiVariable::class);
        });
    }

    private function _registerFeedMeEvents(): void
    {
        Event::on(DataTypes::class, DataTypes::EVENT_BEFORE_FETCH_FEED, function(FeedDataEvent $event) {
            // This will set the feed's data
            $this->feedme->getDataForFeedMe($event);
        });
    }

    /**
     * Applies EasyApi runtime defaults to Feed Me. Deferred until all plugins are
     * loaded so FeedMePlugin::$plugin is populated.
     */
    private function _registerFeedMeDefaults(): void
    {
        Event::on(Plugins::class, Plugins::EVENT_AFTER_LOAD_PLUGINS, function() {
            if (FeedMePlugin::$plugin === null || !($settings = FeedMePlugin::$plugin->getSettings())) {
                return;
            }

            // Parent-element imports can run for hours - don't let the queue job's
            // TTR expire mid-run and get re-reserved by another worker. A
            // feed-me.php config or per-feed feedOptions value still wins.
            if ($settings->queueTtr === null) {
                $settings->queueTtr = 14400;
            }
        });
    }
}
