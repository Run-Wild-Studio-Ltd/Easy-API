<?php

namespace runwildstudio\easyapi\base;

use ArrayAccess;
use Cake\Utility\Hash;
use Craft;
use craft\base\Component;
use craft\errors\ElementNotFoundException;
use runwildstudio\easyapi\helpers\DataHelper;
use runwildstudio\easyapi\EasyApi;
use craft\helpers\Json;
use Throwable;
use yii\base\Exception;

/**
 *
 * @property-read mixed $name
 * @property-read mixed $fieldClass
 * @property-read mixed $class
 * @property-read mixed $elementType
 */
abstract class Field extends Component
{
    // Properties
    // =========================================================================

    /**
     * @var
     */
    public mixed $apiData = null;

    /**
     * @var string|null
     */
    public ?string $fieldHandle = null;

    /**
     * @var
     */
    public mixed $fieldInfo = null;

    /**
     * @var
     */
    public mixed $field = null;

    /**
     * @var
     */
    public mixed $api = null;

    /**
     * @var
     */
    public mixed $element = null;


    // Public Methods
    // =========================================================================

    /**
     * @return mixed
     */
    public function getName(): string
    {
        /** @phpstan-ignore-next-line */
        return static::$name;
    }

    /**
     * @return string
     */
    public function getClass(): string
    {
        return get_class($this);
    }

    /**
     * @return mixed
     */
    public function getFieldClass(): string
    {
        /** @phpstan-ignore-next-line */
        return static::$class;
    }

    /**
     * @return mixed
     */
    public function getElementType(): string
    {
        /** @phpstan-ignore-next-line */
        return static::$elementType;
    }


    // Templates
    // =========================================================================

    // public function getMappingTemplate(): string
    // {
    //     return 'easyapi/_includes/fields/default';
    // }


    // Public Methods
    // =========================================================================

    /**
     * @return array|ArrayAccess|mixed|string|null
     */
    public function fetchSimpleValue(): mixed
    {
        return DataHelper::fetchSimpleValue($this->apiData, $this->fieldInfo);
    }

    /**
     * @return array|ArrayAccess|mixed
     */
    public function fetchArrayValue(): mixed
    {
        return DataHelper::fetchArrayValue($this->apiData, $this->fieldInfo);
    }

    /**
     * @return array|\ArrayAccess|mixed
     */
    public function fetchDefaultArrayValue()
    {
        return DataHelper::fetchDefaultArrayValue($this->fieldInfo);
    }

    /**
     * @return array|ArrayAccess|mixed|null
     */
    public function fetchValue(): mixed
    {
        return DataHelper::fetchValue($this->apiData, $this->fieldInfo, $this->api);
    }

    // Protected Methods
    // =========================================================================

    /**
     * @param $elementIds
     * @param null $nodeKey
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws Exception
     */
    protected function populateElementFields($elementIds, $nodeKey = null): void
    {
        $elementsService = Craft::$app->getElements();
        $fields = Hash::get($this->fieldInfo, 'fields');

        $fieldData = [];

        foreach ($elementIds as $key => $elementId) {
            foreach ($fields as $fieldHandle => $fieldInfo) {
                $default = Hash::get($fieldInfo, 'default');

                // Because we're dealing with an element which always has array content, we need to fetch our content
                // in the same way, as it'll be parsed as an array, despite the actual values being likely a single value
                // Even if it's an array of one size (importing one element), that's fine!
                $fieldValue = DataHelper::fetchArrayValue($this->apiData, $fieldInfo);

                // Arrayed content doesn't provide defaults because it's unable to determine how many items it _should_ return
                // This also checks if there was any data that corresponds on the same array index/level as our element
                $value = Hash::get($fieldValue, $nodeKey, $default);

                if ($value) {
                    $fieldData[$elementId][$fieldHandle] = $value;
                }
            }
        }

        // Now, for each element, we need to save the contents
        foreach ($fieldData as $elementId => $fieldContent) {
            $element = $elementsService->getElementById($elementId, null, Hash::get($this->api, 'siteId'));

            $element->setFieldValues($fieldContent);

            EasyApi::debug([
                $this->fieldHandle => [
                    $elementId => $fieldContent,
                ],
            ]);

            if (!$elementsService->saveElement($element, true, true, Hash::get($this->api, 'updateSearchIndexes'))) {
                EasyApi::error('`{handle}` - Unable to save sub-field: `{e}`.', ['e' => Json::encode($element->getErrors()), 'handle' => $this->fieldHandle]);
            }

            EasyApi::info('`{handle}` - Processed {name} [`#{id}`]({url}) sub-fields with content: `{content}`.', ['name' => $element::displayName(), 'id' => $elementId, 'url' => $element->cpEditUrl, 'handle' => $this->fieldHandle, 'content' => Json::encode($fieldContent)]);
        }
    }

    /**
     * Get numerical node key from node name.
     * E.g. if $nodeName is authors/1/author/name, the $nodeKey should be 1
     *
     * @param $nodeName
     * @return mixed|null
     */
    protected function getArrayKeyFromNode($nodeName)
    {
        $nodeKey = null;

        if (!empty($nodeName)) {
            preg_match('/\/(\d+)\//', $nodeName, $matches);
            if (isset($matches[1])) {
                $nodeKey = $matches[1];
            }
        }

        return $nodeKey;
    }
}
