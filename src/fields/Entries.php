<?php

namespace runwildstudio\easyapi\fields;

use Cake\Utility\Hash;
use Craft;
use craft\base\Element as BaseElement;
use craft\elements\Entry as EntryElement;
use craft\errors\ElementNotFoundException;
use runwildstudio\easyapi\base\Field;
use runwildstudio\easyapi\base\FieldInterface;
use runwildstudio\easyapi\helpers\DataHelper;
use runwildstudio\easyapi\EasyApi;
use craft\fields\Entries as EntriesField;
use craft\helpers\Db;
use craft\helpers\Json;
use Throwable;
use yii\base\Exception;

/**
 *
 * @property-read string $mappingTemplate
 */
class Entries extends Field implements FieldInterface
{
    // Properties
    // =========================================================================

    /**
     * @var string
     */
    public static string $name = 'Entries';

    /**
     * @var string
     */
    public static string $class = EntriesField::class;

    /**
     * @var string
     */
    public static string $elementType = EntryElement::class;

    // Templates
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function getMappingTemplate(): string
    {
        return 'easyapi/_includes/fields/entries';
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

        $sources = Hash::get($this->field, 'settings.sources');
        $limit = Hash::get($this->field, 'settings.limit');
        $targetSiteId = Hash::get($this->field, 'settings.targetSiteId');
        $apiSiteId = Hash::get($this->api, 'siteId');
        $create = Hash::get($this->fieldInfo, 'options.create');
        $fields = Hash::get($this->fieldInfo, 'fields');
        $node = Hash::get($this->fieldInfo, 'node');
        $nodeKey = null;

        $sectionIds = [];

        if (is_array($sources)) {
            foreach ($sources as $source) {
                // When singles is selected as the only option to search in, it doesn't contain any ids...
                if ($source == 'singles') {

                    if (version_compare(Craft::$app->getVersion(), '5.0', '>=')) {
                        foreach (Craft::$app->getEntries()->getAllSections() as $section) {
                            $sectionIds[] = ($section->type == 'single') ? $section->id : '';
                        }
                    } else {
                        foreach (Craft::$app->getSections()->getAllSections() as $section) {
                            $sectionIds[] = ($section->type == 'single') ? $section->id : '';
                        }
                    }
                } else {
                    [, $uid] = explode(':', $source);
                    $sectionIds[] = Db::idByUid('{{%sections}}', $uid);
                }
            }
        } elseif ($sources === '*') {
            $sectionIds = null;
        }

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
            if (trim($dataValue) === '') {
                $foundElements = $default;
                break;
            }

            $query = EntryElement::find();

            // In multi-site, there's currently no way to query across all sites - we use the current site
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
            $criteria['sectionId'] = $sectionIds;
            $criteria['limit'] = $limit;
            $criteria['where'] = ['=', $columnName, $dataValue];

            Craft::configure($query, $criteria);

            EasyApi::info('Search for existing entry with query `{i}`', ['i' => Json::encode($criteria)]);

            $ids = $query->ids();

            $foundElements = array_merge($foundElements, $ids);

            EasyApi::info('Found `{i}` existing entries: `{j}`', ['i' => count($foundElements), 'j' => Json::encode($foundElements)]);

            // Check if we should create the element. But only if title is provided (for the moment)
            if ((count($ids) == 0) && $create && $match === 'title') {
                $foundElements[] = $this->_createElement($dataValue);
            }

            $nodeKey = $this->getArrayKeyFromNode($node);
        }

        // Check for field limit - only return the specified amount
        if ($foundElements && $limit) {
            $foundElements = array_chunk($foundElements, $limit)[0];
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

    /**
     * @param $dataValue
     * @return int|null
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws Exception
     */
    private function _createElement($dataValue): ?int
    {
        $sectionId = Hash::get($this->fieldInfo, 'options.group.sectionId');
        $typeId = Hash::get($this->fieldInfo, 'options.group.typeId');

        // Bit of backwards-compatibility here, if not explicitly set, grab the first globally
        if (!$sectionId) {
            $sectionId = Craft::$app->getSections()->getAllSectionIds()[0];
        }

        if (!$typeId) {
            $typeId = Craft::$app->getSections()->getEntryTypesBySectionId($sectionId)[0]->id;
        }

        $element = new EntryElement();
        $element->title = $dataValue;
        $element->sectionId = $sectionId;
        $element->typeId = $typeId;

        $siteId = Hash::get($this->api, 'siteId');
        $section = Craft::$app->getSections()->getSectionById($element->sectionId);

        if ($siteId) {
            $element->siteId = $siteId;

            // Set the default site status based on the section's settings
            foreach ($section->getSiteSettings() as $siteSettings) {
                if ($siteSettings->siteId == $siteId) {
                    $element->enabledForSite = $siteSettings->enabledByDefault;
                    break;
                }
            }
        } else {
            // Set the default entry status based on the section's settings
            foreach ($section->getSiteSettings() as $siteSettings) {
                if (!$siteSettings->enabledByDefault) {
                    $element->enabled = false;
                }

                break;
            }
        }

        $element->setScenario(BaseElement::SCENARIO_ESSENTIALS);

        if (!Craft::$app->getElements()->saveElement($element, true, true, Hash::get($this->api, 'updateSearchIndexes'))) {
            EasyApi::error('`{handle}` - Entry error: Could not create - `{e}`.', ['e' => Json::encode($element->getErrors()), 'handle' => $this->field->handle]);
        } else {
            EasyApi::info('`{handle}` - Entry `#{id}` added.', ['id' => $element->id, 'handle' => $this->field->handle]);
        }

        return $element->id;
    }
}
