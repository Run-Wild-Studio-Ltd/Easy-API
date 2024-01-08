<?php

namespace runwildstudio\easyapi\fields;

use Cake\Utility\Hash;
use Craft;
use craft\base\Element as BaseElement;
use craft\elements\Category as CategoryElement;
use runwildstudio\easyapi\base\Field;
use runwildstudio\easyapi\base\FieldInterface;
use runwildstudio\easyapi\helpers\DataHelper;
use runwildstudio\easyapi\EasyApi;
use craft\fields\Categories as CategoriesField;
use craft\helpers\Db;
use craft\helpers\Json;

/**
 *
 * @property-read string $mappingTemplate
 */
class Categories extends Field implements FieldInterface
{
    // Properties
    // =========================================================================

    /**
     * @var string
     */
    public static string $name = 'Categories';

    /**
     * @var string
     */
    public static string $class = CategoriesField::class;

    /**
     * @var string
     */
    public static string $elementType = CategoryElement::class;

    // Templates
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function getMappingTemplate(): string
    {
        return 'easyapi/_includes/fields/categories';
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function parseField(): mixed
    {
        $value = $this->fetchArrayValue();
        $default = $this->fetchDefaultArrayValue();

        // if the mapped value is not set in the api
        if ($value === null) {
            return null;
        }

        $match = Hash::get($this->fieldInfo, 'options.match', 'title');
        $specialMatchCase = in_array($match, ['title', 'slug']);

        // if value from the api is empty and default is not set
        // return an empty array; no point bothering further;
        // but we need to allow for zero as a string ("0") value if we're matching by title or slug
        if (empty($default) && DataHelper::isArrayValueEmpty($value, $specialMatchCase)) {
            return [];
        }

        $source = Hash::get($this->field, 'settings.source');
        $branchLimit = Hash::get($this->field, 'settings.branchLimit');
        $targetSiteId = Hash::get($this->field, 'settings.targetSiteId');
        $apiSiteId = Hash::get($this->api, 'siteId');
        $create = Hash::get($this->fieldInfo, 'options.create');
        $fields = Hash::get($this->fieldInfo, 'fields');
        $node = Hash::get($this->fieldInfo, 'node');
        $nodeKey = null;

        // Get source id's for connecting
        [, $groupUid] = explode(':', $source);
        $groupId = Db::idByUid('{{%categorygroups}}', $groupUid);

        $foundElements = [];

        foreach ($value as $dataValue) {
            // Prevent empty or blank values (string or array), which match all elements
            // but sometimes allow for zeros
            if (empty($dataValue) && empty($default) && ($specialMatchCase && !is_numeric($dataValue))) {
                continue;
            }

            // If we're using the default value - skip, we've already got an id array
            if ($node === 'usedefault') {
                $foundElements = $value;
                break;
            }

            // special provision for falling back on default BaseRelationField value
            // https://github.com/runwildstudio/easyapi/issues/1195
            if (trim($dataValue) === '') {
                $foundElements = $default;
                break;
            }

            $query = CategoryElement::find();

            // In multi-site, there's currently no way to query across all sites - we use the current site
            // See https://github.com/runwildstudio/easyapi/issues/2854
            if (Craft::$app->getIsMultiSite()) {
                if ($targetSiteId) {
                    $criteria['siteId'] = Craft::$app->getSites()->getSiteByUid($targetSiteId)->id;
                } elseif ($apiSiteId) {
                    $criteria['siteId'] = $apiSiteId;
                } else {
                    $criteria['siteId'] = Craft::$app->getSites()->getCurrentSite()->id;
                }
            }

            // Because we can match on element attributes and custom fields, AND we're directly using SQL
            // queries in our `where` below, we need to check if we need a prefix for custom fields accessing
            // the content table.
            $columnName = $match;

            if (Craft::$app->getFields()->getFieldByHandle($match)) {
                $columnName = Craft::$app->getFields()->oldFieldColumnPrefix . $match;
            }

            $criteria['status'] = null;
            $criteria['groupId'] = $groupId;
            $criteria['limit'] = $branchLimit;
            $criteria['where'] = ['=', $columnName, $dataValue];

            Craft::configure($query, $criteria);

            EasyApi::info('Search for existing category with query `{i}`', ['i' => Json::encode($criteria)]);

            $ids = $query->ids();

            $foundElements = array_merge($foundElements, $ids);

            EasyApi::info('Found `{i}` existing categories: `{j}`', ['i' => count($foundElements), 'j' => Json::encode($foundElements)]);

            // Check if we should create the element. But only if title is provided (for the moment)
            if ((count($ids) == 0) && $create && $match === 'title') {
                $foundElements[] = $this->_createElement($dataValue, $groupId);
            }

            $nodeKey = $this->getArrayKeyFromNode($node);
        }

        // Check for field limit - only return the specified amount
        if ($foundElements && $branchLimit) {
            $foundElements = array_chunk($foundElements, $branchLimit)[0];
        }

        // Check for any sub-fields for the element
        if ($fields) {
            $this->populateElementFields($foundElements, $nodeKey);
        }

        $foundElements = array_unique($foundElements);

        // Protect against sending an empty array - removing any existing elements
        if (!$foundElements) {
            return null;
        }

        return $foundElements;
    }

    // Private Methods
    // =========================================================================

    private function _createElement($dataValue, $groupId): ?int
    {
        $element = new CategoryElement();
        $element->title = $dataValue;
        $element->groupId = $groupId;

        $siteId = Hash::get($this->api, 'siteId');

        if ($siteId) {
            $element->siteId = $siteId;
        }

        $element->setScenario(BaseElement::SCENARIO_ESSENTIALS);

        if (!Craft::$app->getElements()->saveElement($element, true, true, Hash::get($this->api, 'updateSearchIndexes'))) {
            EasyApi::error('`{handle}` - Category error: Could not create - `{e}`.', ['e' => Json::encode($element->getErrors()), 'handle' => $this->field->handle]);
        } else {
            EasyApi::info('`{handle}` - Category `#{id}` added.', ['id' => $element->id, 'handle' => $this->field->handle]);
        }

        return $element->id;
    }
}
