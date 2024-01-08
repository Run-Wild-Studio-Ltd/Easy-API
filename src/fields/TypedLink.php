<?php

namespace runwildstudio\easyapi\fields;

use Cake\Utility\Hash;
use runwildstudio\easyapi\base\Field;
use runwildstudio\easyapi\base\FieldInterface;
use runwildstudio\easyapi\helpers\DataHelper;

/**
 *
 * @property-read string $mappingTemplate
 */
class TypedLink extends Field implements FieldInterface
{
    // Properties
    // =========================================================================

    /**
     * @var string
     */
    public static string $name = 'TypedLink';

    /**
     * @var string
     */
    public static string $class = 'typedlinkfield\fields\LinkField';

    // Templates
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function getMappingTemplate(): string
    {
        return 'easyapi/_includes/fields/typed-link';
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function parseField(): mixed
    {
        $preppedData = [];

        $fields = Hash::get($this->fieldInfo, 'fields');

        if (!$fields) {
            return null;
        }

        foreach ($fields as $subFieldHandle => $subFieldInfo) {
            $preppedData[$subFieldHandle] = DataHelper::fetchValue($this->apiData, $subFieldInfo, $this->api);
        }

        if (empty(
        array_filter($preppedData, function($val) {
            return $val !== null;
        })
        )) {
            return null;
        }

        // Protect against sending an empty array
        if (!$preppedData) {
            return null;
        }

        return $preppedData;
    }
}
