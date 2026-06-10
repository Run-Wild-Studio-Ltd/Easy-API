<?php

namespace runwildstudio\easyapi\elements;

use Cake\Utility\Hash;
use Carbon\Carbon;
use Craft;
use craft\base\ElementInterface;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;
use craft\db\Query;
use runwildstudio\easyapi\base\Element;
use runwildstudio\easyapi\events\ApiProcessEvent;
use runwildstudio\easyapi\helpers\BaseHelper;
use runwildstudio\easyapi\helpers\DataHelper;
use runwildstudio\easyapi\EasyApi;
use runwildstudio\easyapi\services\Process;
use craft\fields\Matrix;
use craft\fields\Table;
use craft\helpers\Json;
use DateTime;
use Exception;
use yii\base\Event;

/**
 *
 * @property-read string $mappingTemplate
 * @property-read mixed $groups
 * @property-write mixed $model
 * @property-read string $groupsTemplate
 * @property-read string $columnTemplate
 */
class CommerceOrder extends Element
{
    // Properties
    // =========================================================================

    /**
     * @var string
     */
    public static string $name = 'Commerce Order';

    /**
     * @var string
     */
    public static string $class = Order::class;

    // Templates
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function getGroupsTemplate(): string
    {
        return 'easyapi/_includes/elements/commerce-orders/groups';
    }

    /**
     * @inheritDoc
     */
    public function getColumnTemplate(): string
    {
        return 'easyapi/_includes/elements/commerce-orders/column';
    }

    /**
     * @inheritDoc
     */
    public function getMappingTemplate(): string
    {
        return 'easyapi/_includes/elements/commerce-orders/map';
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function getGroups(): array
    {
        //if (Commerce::getInstance()) {
        //    return Commerce::getInstance()->getProductTypes()->getEditableProductTypes();
        //}

        return [];
    }

    /**
     * @inheritDoc
     */
    public function getQuery($settings, array $params = []): mixed
    {
        $query = Order::find()
            ->status(null)
          //  ->typeId($settings['elementGroup'][ProductElement::class])
            ->siteId(Hash::get($settings, 'siteId') ?: Craft::$app->getSites()->getPrimarySite()->id);
        Craft::configure($query, $params);

        return $query;
    }

    /**
     * @inheritDoc
     */
    public function setModel($settings): ElementInterface
    {
        $this->element = new Order();
        //$this->element->typeId = $settings['elementGroup'][ProductElement::class];

        $siteId = Hash::get($settings, 'siteId');

        if ($siteId) {
            $this->element->siteId = $siteId;
        }

        return $this->element;
    }
}
