<?php

namespace runwildstudio\easyapi\fields;

use Cake\Utility\Hash;
use runwildstudio\easyapi\base\Field;
use runwildstudio\easyapi\base\FieldInterface;
use runwildstudio\easyapi\helpers\DataHelper;
use runwildstudio\easyapi\EasyApi;
use craft\fields\Matrix as MatrixField;

/**
 *
 * @property-read string $mappingTemplate
 */
class Matrix extends Field implements FieldInterface
{
    // Properties
    // =========================================================================

    /**
     * @var string
     */
    public static string $name = 'Matrix';

    /**
     * @var string
     */
    public static string $class = MatrixField::class;

    // Templates
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function getMappingTemplate(): string
    {
        return 'easyapi/_includes/fields/matrix';
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function parseField(): mixed
    {
        $preppedData = [];
        $fieldData = [];
        $complexFields = [];

        $blocks = Hash::get($this->fieldInfo, 'blocks');

        // Before we do anything, we need to extract the data from our api and normalise it. This is especially
        // complex due to sub-fields, which each can be a variety of fields and formats, compounded by multiple or
        // Matrix blocks - we don't know! We also need to be careful of the order data is in the api to be
        // reflected in the field - phew!
        //
        // So, in order to keep data in the order provided in our api, we start there (as opposed to looping through blocks)

        foreach ($this->apiData as $nodePath => $value) {
            // Get the field mapping info for this node in the api
            $fieldInfo = $this->_getFieldMappingInfoForNodePath($nodePath, $blocks);
            $nodePathSegments = explode('/', $nodePath);

            // If this is data concerning our Matrix field and blocks
            if ($fieldInfo) {
                $blockHandle = $fieldInfo['blockHandle'];
                $subFieldHandle = $fieldInfo['subFieldHandle'];
                $subFieldInfo = $fieldInfo['subFieldInfo'];
                $isComplexField = $fieldInfo['isComplexField'];

                $key = $this->_getBlockKey($nodePathSegments, $blockHandle, $subFieldHandle);

                // Check for complex fields (think Table, Super Table, etc), essentially anything that has
                // sub-fields, and doesn't have data directly mapped to the field itself. It needs to be
                // accumulated here (so its in the right order), but grouped based on the field and block
                // its in. A bit annoying, but no better ideas...
                if ($isComplexField) {
                    $complexFields[$key]['info'] = $subFieldInfo;
                    $complexFields[$key]['data'][$nodePath] = $value;
                    continue;
                }

                // Swap out the node-path stored in the field-mapping info, because
                // it'll be generic MatrixBlock/Images not MatrixBlock/0/Images/0 like we need
                $subFieldInfo['node'] = $nodePath;

                // Parse each field via their own fieldtype service
                $parsedValue = $this->_parseSubField($this->apiData, $subFieldHandle, $subFieldInfo);

                // Finish up with the content, also sort out cases where there's array content
                if (isset($fieldData[$key]) && is_array($fieldData[$key])) {
                    $fieldData[$key] = is_array($parsedValue) ? array_merge_recursive($fieldData[$key], $parsedValue) : $fieldData[$key];
                } else {
                    $fieldData[$key] = $parsedValue;
                }
            }

            foreach ($blocks as $blockHandle => $fields) {
                foreach ($fields['fields'] as $fieldHandle => $fieldInfo) {
                    $node = Hash::get($fieldInfo, 'node');
                    if ($node === 'usedefault') {
                        $key = $this->_getBlockKey($nodePathSegments, $blockHandle, $fieldHandle);

                        $parsedValue = DataHelper::fetchSimpleValue($this->apiData, $fieldInfo);
                        $fieldData[$key] = $parsedValue;
                    }
                }
            }
        }

        // Handle some complex fields that don't directly have nodes, but instead have nested properties mapped.
        // They have their mapping setup on sub-fields, and need to be processed all together, which we've already prepared.
        // Additionally, we only want to supply each field with a sub-set of data related to that specific block and field
        // otherwise, we get the field class processing all blocks in one go - not what we want.
        foreach ($complexFields as $key => $complexInfo) {
            $parts = explode('.', $key);
            $subFieldHandle = $parts[2];

            $subFieldInfo = Hash::get($complexInfo, 'info');
            $nodePaths = Hash::get($complexInfo, 'data');

            $parsedValue = $this->_parseSubField($nodePaths, $subFieldHandle, $subFieldInfo);

            if ($parsedValue !== null) {
                if (isset($fieldData[$key])) {
                    $fieldData[$key] = array_merge_recursive($fieldData[$key], $parsedValue);
                } else {
                    $fieldData[$key] = $parsedValue;
                }
            }
        }

        ksort($fieldData, SORT_NUMERIC);

        // $order = 0;

        // New, we've got a collection of prepared data, but its formatted a little rough, due to catering for
        // sub-field data that could be arrays or single values. Let's build our Matrix-ready data
        foreach ($fieldData as $blockSubFieldHandle => $value) {
            $handles = explode('.', $blockSubFieldHandle);
            $blockHandle = $handles[1];
            // Inclusion of block handle here prevents blocks of different types from being merged together
            $blockIndex = 'new' . $blockHandle . ((int)$handles[0] + 1);
            $subFieldHandle = $handles[2];

            $disabled = Hash::get($this->fieldInfo, 'blocks.' . $blockHandle . '.disabled', false);

            // Prepare an array that's ready for Matrix to import it
            $preppedData[$blockIndex . '.type'] = $blockHandle;
            $preppedData[$blockIndex . '.enabled'] = !$disabled;
            // because of PR 1284 and enhanced matrix data comparison in PR 1291,
            // we need to add "collapsed => false" to the matrix data from the api;
            // otherwise enhanced matrix data comparison will always think the data has changed
            $preppedData[$blockIndex . '.collapsed'] = false;
            $preppedData[$blockIndex . '.fields.' . $subFieldHandle] = $value;
        }

        // if there's nothing in the prepped data, return null, as if mapping doesn't exist
        if (empty($preppedData)) {
            return null;
        }

        $expanded = Hash::expand($preppedData);

        // Although it seems to work with block handles in keys, it's better to keep things clean
        $index = 1;
        $resultBlocks = [];
        foreach ($expanded as $blockData) {
            // all the fields are empty, ignore the block
            if (
                !empty(array_filter(
                    $blockData['fields'],
                    fn($value) => (is_string($value) && !empty($value)) || (is_array($value) && !empty(array_filter($value)))
                ))
            ) {
                $resultBlocks['new' . $index++] = $blockData;
            }
        }

        return $resultBlocks;
    }


    // Private Methods
    // =========================================================================

    /**
     * Get block's key
     *
     * @param array $nodePathSegments
     * @param string $blockHandle
     * @param string $fieldHandle
     * @return string
     */
    private function _getBlockKey(array $nodePathSegments, string $blockHandle, string $fieldHandle): string
    {
        $blockIndex = Hash::get($nodePathSegments, 1);

        if (!is_numeric($blockIndex)) {
            // Try to check if its only one-level deep (only importing one block type)
            // which is particularly common for JSON.
            $blockIndex = Hash::get($nodePathSegments, 2);

            if (!is_numeric($blockIndex)) {
                $blockIndex = 0;
            }
        }

        return $blockIndex . '.' . $blockHandle . '.' . $fieldHandle;
    }

    /**
     * @param $nodePath
     * @param $blocks
     * @return array|null|string
     */
    private function _getFieldMappingInfoForNodePath($nodePath, $blocks): ?array
    {
        foreach ($blocks as $blockHandle => $blockInfo) {
            $fields = Hash::get($blockInfo, 'fields');

            $apiPath = preg_replace('/(\/\d+\/)/', '/', $nodePath);
            $apiPath = preg_replace('/^(\d+\/)|(\/\d+)/', '', $apiPath);

            foreach ($fields as $subFieldHandle => $subFieldInfo) {
                $node = Hash::get($subFieldInfo, 'node');

                $nestedFieldNodes = Hash::extract($subFieldInfo, 'fields.{*}.node');

                if ($nestedFieldNodes) {
                    foreach ($nestedFieldNodes as $nestedFieldNode) {
                        if ($apiPath == $nestedFieldNode) {
                            return [
                                'blockHandle' => $blockHandle,
                                'subFieldHandle' => $subFieldHandle,
                                'subFieldInfo' => $subFieldInfo,
                                'nodePath' => $nodePath,
                                'isComplexField' => true,
                            ];
                        }
                    }
                }

                if ($apiPath == $node) {
                    return [
                        'blockHandle' => $blockHandle,
                        'subFieldHandle' => $subFieldHandle,
                        'subFieldInfo' => $subFieldInfo,
                        'nodePath' => $nodePath,
                        'isComplexField' => false,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param $apiData
     * @param $subFieldHandle
     * @param $subFieldInfo
     * @return mixed
     */
    private function _parseSubField($apiData, $subFieldHandle, $subFieldInfo): mixed
    {
        $subFieldClassHandle = Hash::get($subFieldInfo, 'field');

        $subField = Hash::extract($this->field->getBlockTypeFields(), '{n}[handle=' . $subFieldHandle . ']')[0];

        if (!$subField instanceof $subFieldClassHandle) {
            $subFieldClassHandle = \craft\fields\Entries::class;
        }

        $class = EasyApi::$plugin->fields->getRegisteredField($subFieldClassHandle);
        $class->apiData = $apiData;
        $class->fieldHandle = $subFieldHandle;
        $class->fieldInfo = $subFieldInfo;
        $class->field = $subField;
        $class->element = $this->element;
        $class->api = $this->api;

        // Get our content, parsed by this fields service function
        return $class->parseField();
    }
}
