<?php

namespace runwildstudio\easyapi;

use Craft;
use craft\base\Model;
use craft\events\RegisterUrlRulesEvent;
use craft\feedme\events\FeedDataEvent;
use craft\feedme\events\FeedEvent;
use craft\feedme\services\DataTypes;
use craft\feedme\services\Feeds;
use craft\feedme\services\Process as FeedMeProcess;
use craft\helpers\UrlHelper;
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
}
