<?php

namespace runwildstudio\easyapi\elements;

use Cake\Utility\Hash;
use Carbon\Carbon;
use Craft;
use craft\base\ElementInterface;
use craft\elements\Entry as EntryElement;
use craft\elements\User as UserElement;
use craft\errors\ElementNotFoundException;
use runwildstudio\easyapi\base\Element;
use runwildstudio\easyapi\helpers\DataHelper;
use runwildstudio\easyapi\models\ElementGroup;
use runwildstudio\easyapi\EasyApi;
use craft\helpers\ElementHelper;
use craft\helpers\Json;
use craft\models\Section;
use DateTime;
use Throwable;
use yii\base\Exception;

/**
 *
 * @property-read string $mappingTemplate
 * @property-read array $groups
 * @property-write mixed $model
 * @property-read string $groupsTemplate
 * @property-read string $columnTemplate
 */
class Entry extends Element
{
    // Properties
    // =========================================================================

    /**
     * @var string
     */
    public static string $name = 'Entry';

    /**
     * @var string
     */
    public static string $class = EntryElement::class;

    // Templates
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function getGroupsTemplate(): string
    {
        return 'easyapi/_includes/elements/entries/groups';
    }

    /**
     * @inheritDoc
     */
    public function getColumnTemplate(): string
    {
        return 'easyapi/_includes/elements/entries/column';
    }

    /**
     * @inheritDoc
     */
    public function getMappingTemplate(): string
    {
        return 'easyapi/_includes/elements/entries/map';
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function getGroups(): array
    {
        if (version_compare(Craft::$app->getVersion(), '5.0', '>=')) {
            // Code specific to Craft 5
            $editable = Craft::$app->getEntries()->getEditableSections();
        } else {
            // Code specific to Craft 4
            $editable = Craft::$app->getSections()->getEditableSections();
        }
        $groups = [];

        foreach ($editable as $section) {
            $groups[] = new ElementGroup([
                'id' => $section->id,
                'model' => $section,
            ]);
        }

        return $groups;
    }

    /**
     * @inheritDoc
     */
    public function getQuery($settings, array $params = []): mixed
    {
        $targetSiteId = Hash::get($settings, 'siteId') ?: Craft::$app->getSites()->getPrimarySite()->id;
        if ($this->element !== null) {
            $section = $this->element->getSection();
        }

        $query = EntryElement::find()
            ->status(null)
            ->sectionId($settings['elementGroup'][EntryElement::class]['section'])
            ->typeId($settings['elementGroup'][EntryElement::class]['entryType']);

        if (isset($section) && $section->propagationMethod === Section::PROPAGATION_METHOD_CUSTOM) {
            $query->site('*')
                ->preferSites([$targetSiteId])
                ->unique();
        } else {
            $query->siteId($targetSiteId);
        }

        Craft::configure($query, $params);
        return $query;
    }

    /**
     * @inheritDoc
     */
    public function setModel($settings): ElementInterface
    {
        $this->element = new EntryElement();
        $this->element->sectionId = $settings['elementGroup'][EntryElement::class]['section'];
        $this->element->typeId = $settings['elementGroup'][EntryElement::class]['entryType'];

        
        if (version_compare(Craft::$app->getVersion(), '5.0', '>=')) {
            // Code specific to Craft 5
            $section = Craft::$app->getEntries()->getSectionById($this->element->sectionId);
        } else {
            // Code specific to Craft 4
            $section = Craft::$app->getSections()->getSectionById($this->element->sectionId);
        }
        $siteId = Hash::get($settings, 'siteId');

        if ($siteId) {
            $this->element->siteId = $siteId;
        }

        // Set the default site status based on the section's settings
        $enabledForSite = [];
        foreach ($section->getSiteSettings() as $siteSettings) {
            if (
                $section->propagationMethod !== Section::PROPAGATION_METHOD_CUSTOM ||
                $siteSettings->siteId == $siteId
            ) {
                $enabledForSite[$siteSettings->siteId] = $siteSettings->enabledByDefault;
            }
        }
        $this->element->setEnabledForSite($enabledForSite);

        return $this->element;
    }
}
