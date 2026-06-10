<?php

namespace runwildstudio\easyapi\web\twig\variables;

use Craft;
use craft\elements\User as UserElement;
use runwildstudio\easyapi\EasyApi;
use craft\fields\Checkboxes;
use craft\fields\Color;
use craft\fields\Date;
use craft\fields\Dropdown;
use craft\fields\Email;
use craft\fields\Lightswitch;
use craft\fields\MultiSelect;
use craft\fields\Number;
use craft\fields\PlainText;
use craft\fields\RadioButtons;
use craft\fields\Url;
use craft\helpers\DateTimeHelper;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use craft\models\CategoryGroup;
use craft\models\Section;
use craft\models\TagGroup;
use DateTime;
use yii\di\ServiceLocator;

/**
 *
 * @property-read mixed $pluginName
 * @property-read array[]|array $tabs
 */
class EasyApiVariable extends ServiceLocator
{
    public mixed $config = null;

    public function __construct($config = [])
    {
        $config['components'] = EasyApi::$plugin->getComponents();

        parent::__construct($config);
    }

    public function getPluginName(): string
    {
        return EasyApi::$plugin->getPluginName();
    }

    public function getTabs(): array
    {
        $settings = EasyApi::$plugin->getSettings();
        $enabledTabs = $settings->enabledTabs;

        $tabs = [
            'apis' => ['label' => Craft::t('easyapi', 'Apis'), 'url' => UrlHelper::cpUrl('easyapi/apis')],
            'logs' => ['label' => Craft::t('easyapi', 'Logs'), 'url' => UrlHelper::cpUrl('easyapi/logs')],
            'settings' => ['label' => Craft::t('easyapi', 'Settings'), 'url' => UrlHelper::cpUrl('easyapi/settings')],
        ];

        if (!is_array($enabledTabs)) {
            return $tabs;
        }

        if (empty($enabledTabs)) {
            return [];
        }

        $selectedTabs = [];

        foreach ($enabledTabs as $enabledTab) {
            if (isset($tabs[$enabledTab])) {
                $selectedTabs[$enabledTab] = $tabs[$enabledTab];
            }
        }

        return $selectedTabs;
    }

    public function getSelectOptions($options, $label = 'name', $index = 'id', $includeNone = true): array
    {
        $values = [];

        if ($includeNone) {
            if (is_string($includeNone)) {
                $values[''] = $includeNone;
            } else {
                $values[''] = 'None';
            }
        }

        if (is_array($options)) {
            foreach ($options as $value) {
                if (isset($value['optgroup'])) {
                    continue;
                }

                $values[$value[$index]] = Html::encode($value[$label]);
            }
        }

        return $values;
    }


    //
    // Main template tag
    //

    public function api($options = [])
    {
        return EasyApi::$plugin->data->getApiForTemplate($options);
    }

    public function apiHeaders($options = [])
    {
        $options['headers'] = true;

        return EasyApi::$plugin->data->getApiForTemplate($options);
    }

    public function getApiUrl($apiId): string
    {
        $api = EasyApi::$plugin->apis->getApiById($apiId);
        return $api->apiUrl;
    }

    public function runApi($apiId, $apiUrl = null): string //, $apiRequestHeader = null, $apiRequestBody = null): string
    {
        $api = EasyApi::$plugin->apis->getApiById($apiId);
        if (!$api->useLive)
        {
            return "API " . $api->name . " is not configured for live site processing.";
        }

        if ($apiUrl == null)
        {
            $apiUrl = $api->apiUrl;
        }
        $response = EasyApi::$plugin->data->getRawData($apiUrl, $apiId);
        return $response['data'];
    }


    //
    // Fields + Field Mapping
    //

    public function formatDateTime($dateTime): DateTime|bool
    {
        return DateTimeHelper::toDateTime($dateTime);
    }

    public function getEntrySourcesByField($field): ?array
    {
        $sources = [];

        if (!$field) {
            return null;
        }

        if (is_array($field->sources)) {
            foreach ($field->sources as $source) {
                if ($source == 'singles') {
                    foreach (Craft::$app->getSections()->getAllSections() as $section) {
                        if ($section->type == 'single') {
                            $sources[] = $section;
                        }
                    }
                } else {
                    [, $uid] = explode(':', $source);

                    $section = Craft::$app->getSections()->getSectionByUid($uid);
                    // only add to sources, if this was a section that we were able to retrieve (native section's uid)
                    if ($section) {
                        $sources[] = $section;
                    }
                }
            }
        } elseif ($field->sources === '*') {
            $sources = Craft::$app->getSections()->getAllSections();
        }

        return $sources;
    }

    //
    // Helper functions for element fields in getting their inner-element field layouts
    //

    public function getElementLayoutByField($type, $field): ?array
    {
        $source = null;

        if ($type === 'craft\fields\Assets') {
            $source = $this->getAssetSourcesByField($field)[0] ?? null;
        } elseif ($type === 'craft\fields\Categories') {
            $source = $this->getCategorySourcesByField($field);
        } elseif ($type === 'craft\fields\Entries') {
            /** @var Section $section */
            $section = $this->getEntrySourcesByField($field)[0] ?? null;

            if ($section) {
                $source = Craft::$app->getSections()->getEntryTypeById($section->id);
            }
        } elseif ($type === 'craft\fields\Tags') {
            $source = $this->getTagSourcesByField($field);
        }

        if (!$source || !$source->fieldLayoutId) {
            return null;
        }

        if (($fieldLayout = Craft::$app->getFields()->getLayoutById($source->fieldLayoutId)) !== null) {
            return $fieldLayout->getCustomFields();
        }

        return null;
    }

    public function getUserLayoutByField(): ?array
    {
        $layoutId = Craft::$app->getFields()->getLayoutByType(UserElement::class)->id;

        if (!$layoutId) {
            return null;
        }

        if (($fieldLayout = Craft::$app->getFields()->getLayoutById($layoutId)) !== null) {
            return $fieldLayout->getCustomFields();
        }

        return null;
    }
}
