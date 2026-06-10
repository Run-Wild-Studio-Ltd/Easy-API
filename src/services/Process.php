<?php

namespace runwildstudio\easyapi\services;

use Cake\Utility\Hash;
use Craft;
use craft\base\Component;
use craft\elements\User;
use craft\errors\ShellCommandException;
use runwildstudio\easyapi\base\ElementInterface;
use runwildstudio\easyapi\events\ApiProcessEvent;
use runwildstudio\easyapi\helpers\DataHelper;
use runwildstudio\easyapi\helpers\DuplicateHelper;
use runwildstudio\easyapi\models\ApiModel;
use runwildstudio\easyapi\EasyApi;
use craft\helpers\App;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use yii\base\Exception;

class Process extends Component
{
    // Constants
    // =========================================================================

    public const EVENT_BEFORE_PROCESS_API = 'onBeforeProcessApi';
    public const EVENT_STEP_BEFORE_ELEMENT_MATCH = 'onStepBeforeElementMatch';
    public const EVENT_STEP_BEFORE_PARSE_CONTENT = 'onStepBeforeParseContent';
    public const EVENT_STEP_BEFORE_ELEMENT_SAVE = 'onStepBeforeElementSave';
    public const EVENT_STEP_AFTER_ELEMENT_SAVE = 'onStepElementSave';
    public const EVENT_AFTER_PROCESS_API = 'onAfterProcessApi';


    // Properties
    // =========================================================================

    /**
     * @var
     */
    private mixed $_time_start = null;

    /**
     * @var ElementInterface
     */
    private ElementInterface $_service;

    /**
     * @var array
     */
    private array $_data;


    // Public Methods
    // =========================================================================

    /**
     * @param ApiModel $api
     * @param array $apiData
     * @return array|void
     * @throws \Exception
     */
    // public function beforeProcessApi(ApiModel $api, array $apiData)
    // {
    //     EasyApi::$apiName = $api->name;

    //     EasyApi::info('Preparing for api processing.');

    //     if (!$apiData) {
    //         throw new \Exception(Craft::t('easyapi', 'No data to import.'));
    //     }

    //     $runGcBeforeApi = EasyApi::$plugin->service->getConfig('runGcBeforeApi', $api['id']);

    //     if ($runGcBeforeApi) {
    //         $gc = Craft::$app->getGc();
    //         $gc->deleteAllTrashed = true;
    //         $gc->run(true);
    //     }

    //     $this->_data = $apiData;
    //     $this->_service = $api->element;

    //     $return = $api->attributes;

    //     // Set our start time to track api processing time
    //     $this->_time_start = microtime(true);

    //     App::maxPowerCaptain();

    //     // Add some additional information to our ApiModel - for ease of use in processing
    //     // $return['fields'] = [];
    //     $return['existingElements'] = [];

    //     // Clear out Api Mapping and Field Uniques - we need to do some filtering
    //     $return['fieldMapping'] = $this->_filterUnmappedFields($api['fieldMapping']);
    //     $return['fieldUnique'] = [];

    //     if (!$return['fieldMapping']) {
    //         throw new \Exception(Craft::t('easyapi', 'Field mapping not setup.'));
    //     }

    //     // Ditch all fields we aren't checking for uniques on. Just simplifies each run (we don't have to check)
    //     if (!empty($api['fieldUnique'])) {
    //         foreach ($api['fieldUnique'] as $key => $value) {
    //             if ((int)$value === 1) {
    //                 $return['fieldUnique'][$key] = $value;
    //             }
    //         }
    //     }

    //     if (empty($return['fieldUnique'])) {
    //         throw new \Exception(Craft::t('easyapi', 'No unique fields checked.'));
    //     }

    //     // Get the service for the Element Type we're dealing with
    //     if (!$api->element) {
    //         throw new \Exception(Craft::t('easyapi', 'Unknown Element Type Service called.'));
    //     }

    //     // If our duplication handling is to delete - we delete all elements
    //     // If our duplication handling is to disable - we disable all elements
    //     if (
    //         DuplicateHelper::isDelete($api) ||
    //         DuplicateHelper::isDisable($api) ||
    //         DuplicateHelper::isDisableForSite($api)) {
    //         $query = $api->element->getQuery($api);
    //         $return['existingElements'] = $query->ids();
    //     }

    //     // Our main data-parsing function. Handles the actual data values, defaults and field options
    //     foreach ($apiData as $key => $nodeData) {
    //         if (!is_array($nodeData)) {
    //             $nodeData = [$nodeData];
    //         }

    //         $this->_data[$key] = Hash::flatten($nodeData, '/');
    //     }

    //     $this->_data = array_values($this->_data);

    //     // Fire an 'onBeforeProcessApi' event
    //     $event = new ApiProcessEvent([
    //         'api' => $api,
    //         'apiData' => $this->_data,
    //     ]);
        
    //     $this->trigger(self::EVENT_BEFORE_PROCESS_API, $event);

    //     if (!$event->isValid) {
    //         return;
    //     }

    //     // Allow event to modify the api data
    //     $this->_data = $event->apiData;

    //     // Return the api data
    //     $return['apiData'] = $this->_data;

    //     EasyApi::info('Finished preparing for api processing.');

    //     return $return;
    // }

    // /**
    //  * @param $step
    //  * @param $api
    //  * @param $processedElementIds
    //  * @param $apiData
    //  * @return mixed|void
    //  * @throws \Exception
    //  */
    // public function processApi($step, $api, &$processedElementIds, $apiData = null)
    // {
    //     $attributeData = [];
    //     $fieldData = [];

    //     // We can opt-out of updating certain elements if a field is switched on
    //     $skipUpdateFieldHandle = EasyApi::$plugin->service->getConfig('skipUpdateFieldHandle', $api['id']);

    //     //
    //     // Let's get started!
    //     //

    //     $logKey = StringHelper::randomString(20);

    //     // Save this to session, so we don't have to pass it around everywhere.
    //     EasyApi::$stepKey = $logKey;

    //     // Try to fix an elusive bug...
    //     if (!is_numeric($step)) {
    //         EasyApi::error('Error `{i}`.', ['i' => Json::encode($step)]);
    //     }

    //     if (!is_array($this->_data) || empty($this->_data[0])) {
    //         EasyApi::info('There is no data in the api to process.');
    //         return;
    //     }

    //     EasyApi::info('Starting processing of node `#{i}`.', ['i' => ($step + 1)]);

    //     // Set up a model for this Element Type
    //     $element = $this->_service->setModel($api);        

    //     // From the raw data in our api, we need to fix it up so its Craft-ready for the element and fields
    //     $apiData = $apiData ?? $this->_data[$step];

    //     // We need to first find a potentially existing element, and to do that, we need to prep just the fields
    //     // that are selected as the unique identifier. We prep everything else later on.
    //     $matchExistingElementData = [];

    //     foreach ($api['fieldUnique'] as $fieldHandle => $value) {
    //         $mappingInfo = Hash::get($api['fieldMapping'], $fieldHandle);

    //         if (!$mappingInfo) {
    //             continue;
    //         }

    //         if (Hash::get($mappingInfo, 'attribute')) {
    //             $attributeValue = $this->_service->parseAttribute($apiData, $fieldHandle, $mappingInfo);

    //             if ($attributeValue !== null) {
    //                 $matchExistingElementData[$fieldHandle] = $attributeValue;
    //             }
    //         }

    //         if (Hash::get($mappingInfo, 'field')) {
    //             $fieldValue = EasyApi::$plugin->fields->parseField($api, $element, $apiData, $fieldHandle, $mappingInfo);

    //             if ($fieldValue !== null) {
    //                 $matchExistingElementData[$fieldHandle] = $fieldValue;
    //             }
    //         }
    //     }

    //     EasyApi::info('Match existing element with data `{i}`.', ['i' => Json::encode($matchExistingElementData)]);

    //     //
    //     // Check for Add/Update/Delete for existing elements
    //     //

    //     // Fire an 'onStepBeforeElementMatch' event
    //     if ($this->hasEventHandlers(self::EVENT_STEP_BEFORE_ELEMENT_MATCH)) {
    //         $event = new ApiProcessEvent([
    //             'api' => $api,
    //             'apiData' => $apiData,
    //             'contentData' => $matchExistingElementData,
    //         ]);

    //         $this->trigger(self::EVENT_STEP_BEFORE_ELEMENT_MATCH, $event);

    //         if (!$event->isValid) {
    //             return;
    //         }

    //         // Allow event to modify variables
    //         $api = $event->api;
    //         $apiData = $event->apiData;
    //         $matchExistingElementData = $event->contentData;
    //     }

    //     // Check to see if an element already exists
    //     $existingElement = $this->_service->matchExistingElement($matchExistingElementData, $api);

    //     // If there's an existing matching element
    //     if ($existingElement) {
    //         EasyApi::info('Existing element [`#{id}`]({url}) found.', ['id' => $existingElement->id, 'url' => $existingElement->cpEditUrl]);

    //         // If we're deleting or updating an existing element, we want to focus on that one
    //         if (DuplicateHelper::isUpdate($api)) {
    //             if (method_exists($this->_service, 'checkPropagation')) {
    //                 $existingElement = $this->_service->checkPropagation($existingElement, $api);
    //             }

    //             $element = clone $existingElement;

    //             // Update our service with the correct element
    //             $this->_service->element = $element;
    //         }

    //         // There's also a config settings for a field to opt-out of updating. Check against that
    //         if ($skipUpdateFieldHandle) {
    //             $updateField = $element->$skipUpdateFieldHandle ?? '';

    //             // We've got our special field on this element, and it's switched on
    //             if ($updateField === '1') {
    //                 EasyApi::info('Skipped due to config setting.');

    //                 return;
    //             }
    //         }

    //         // If we're adding only, and there's an existing element - quit now
    //         if (DuplicateHelper::isAdd($api, true)) {
    //             EasyApi::info('Skipped due to an existing element found, and elements are set to add only.');

    //             return;
    //         }
    //     } else {
    //         // Have we set to update-only? There are no existing elements, so skip
    //         if (DuplicateHelper::isUpdate($api, true)) {
    //             EasyApi::info('Skipped due to an existing element not found, and elements are set to update only.');

    //             return;
    //         }

    //         // If this variable is explicitly false, this means there's no data in the api for mapping
    //         // existing elements - that's a problem no matter which option is selected, so don't proceed.
    //         // Even if Add is selected, we'll end up with duplicates because it can't find existing elements to skip over
    //         if ($existingElement === false) {
    //             EasyApi::info('No existing element mapping data found. Have you ensured you\'ve supplied all correct data in your api?');

    //             return;
    //         }
    //     }

    //     // Are we only disabling/deleting only, we need to quit right here
    //     if (
    //         DuplicateHelper::isDisable($api, true) ||
    //         DuplicateHelper::isDisableForSite($api, true) ||
    //         DuplicateHelper::isDelete($api, true) ||
    //         (!DuplicateHelper::isUpdate($api) && $existingElement)
    //     ) {
    //         // If there's an existing element, we want to keep it, otherwise remove it
    //         if ($existingElement) {
    //             $processedElementIds[] = $existingElement->id;
    //         }

    //         return;
    //     }

    //     //
    //     // Now, parse all element attributes and custom fields
    //     //

    //     // Fire an 'onStepBeforeParseContent' event
    //     if ($this->hasEventHandlers(self::EVENT_STEP_BEFORE_PARSE_CONTENT)) {
    //         $event = new ApiProcessEvent([
    //             'api' => $api,
    //             'apiData' => $apiData,
    //             'element' => $element,
    //         ]);

    //         $this->trigger(self::EVENT_STEP_BEFORE_PARSE_CONTENT, $event);

    //         if (!$event->isValid) {
    //             return;
    //         }

    //         // Allow event to modify variables
    //         $api = $event->api;
    //         $apiData = $event->apiData;
    //         $element = $event->element;
    //     }

    //     // Parse just the element attributes first. We use these in our field contexts, and need a fully-prepped element
    //     foreach ($api['fieldMapping'] as $fieldHandle => $fieldInfo) {
    //         if (Hash::get($fieldInfo, 'attribute')) {
    //             $attributeValue = $this->_service->parseAttribute($apiData, $fieldHandle, $fieldInfo);

    //             if ($attributeValue !== null) {
    //                 $attributeData[$fieldHandle] = $attributeValue;
    //             }
    //         }
    //     }

    //     $contentData = [];
    //     if (isset($attributeData['enabled'])) {
    //         // Set the site-specific status as well, but retain all other site statuses
    //         $enabledForSite = [];
    //         foreach (Craft::$app->getSites()->getAllSiteIds(true) as $siteId) {
    //             $status = $element->getEnabledForSite($siteId);
    //             if ($status !== null) {
    //                 $enabledForSite[$siteId] = $status;
    //             }
    //         }

    //         $enabledForSite[$element->siteId] = $attributeData['enabled'];

    //         // Set the global status to true if it's enabled for *any* sites, or if already enabled.
    //         $element->enabled = in_array(true, $enabledForSite) || $element->enabled;
    //         $element->setEnabledForSite($enabledForSite);
    //         $contentData['enabled'] = $element->enabled;
    //         $contentData['enabledForSite'] = $element->getEnabledForSite($element->siteId);

    //         unset($attributeData['enabled']);
    //     }
    //     // Set the attributes for the element
    //     $element->setAttributes($attributeData, false);

    //     // Then, do the same for custom fields. Again, this should be done after populating the element attributes
    //     foreach ($api['fieldMapping'] as $fieldHandle => $fieldInfo) {
    //         if (Hash::get($fieldInfo, 'field')) {
    //             $fieldValue = EasyApi::$plugin->fields->parseField($api, $element, $apiData, $fieldHandle, $fieldInfo);

    //             if ($fieldValue !== null) {
    //                 if ((!empty($fieldValue) || is_numeric($fieldValue) || is_bool($fieldValue))
    //                 ) {
    //                     $fieldData[$fieldHandle] = $fieldValue;
    //                 }
    //             }
    //         }
    //     }

    //     // Do the same with our custom field data
    //     $element->setFieldValues($fieldData);

    //     // Now we've fully prepped our element, one last final check each attribute and field for Twig shorthand to parse
    //     // We have to do this at the end, separately so we've got full access to the prepped element content
    //     $parseTwig = EasyApi::$plugin->service->getConfig('parseTwig', $api['id']);

    //     if ($parseTwig) {
    //         foreach ($attributeData as $key => $value) {
    //             $attributeData[$key] = DataHelper::parseFieldDataForElement($value, $element);
    //         }

    //         foreach ($fieldData as $key => $value) {
    //             $fieldData[$key] = DataHelper::parseFieldDataForElement($value, $element);
    //         }

    //         // Set the attributes and fields again
    //         $element->setAttributes($attributeData, false);
    //         $element->setFieldValues($fieldData);
    //     }

    //     // We need to keep these separate to apply to the element but required when matching against existing elements
    //     $contentData += $attributeData + $fieldData;

    //     //
    //     // It's time to actually save the element!
    //     //

    //     // Fire an 'onStepBeforeElementSave' event
    //     if ($this->hasEventHandlers(self::EVENT_STEP_BEFORE_ELEMENT_SAVE)) {
    //         $event = new ApiProcessEvent([
    //             'api' => $api,
    //             'apiData' => $apiData,
    //             'contentData' => $contentData,
    //             'element' => $element,
    //         ]);

    //         $this->trigger(self::EVENT_STEP_BEFORE_ELEMENT_SAVE, $event);

    //         if (!$event->isValid) {
    //             return;
    //         }

    //         // Allow event to modify variables
    //         $api = $event->api;
    //         $apiData = $event->apiData;
    //         $contentData = $event->contentData;
    //         $element = $event->element;
    //     }

    //     // If we want to check the existing element's content against this new one, let's do it.
    //     if (EasyApi::$plugin->service->getConfig('compareContent', $api['id'])) {
    //         $unchangedContent = DataHelper::compareElementContent($contentData, $existingElement);

    //         if ($unchangedContent) {
    //             $info = Craft::t('easyapi', 'Node `#{i}` skipped. No content has changed.', ['i' => ($step + 1)]);

    //             EasyApi::info($info);
    //             EasyApi::debug($info);
    //             EasyApi::debug($contentData);

    //             $processedElementIds[] = $element->id;

    //             return;
    //         }
    //     }

    //     EasyApi::info('Data ready to import `{i}`.', ['i' => Json::encode($contentData)]);
    //     EasyApi::debug($contentData);

    //     // Save the element
    //     if ($this->_service->save($element, $api)) {

    //         // save user's preferences only after user has been successfully saved
    //         if ($element instanceof User && isset($attributeData['preferredLocale'])) {
    //             if (!empty($attributeData['preferredLocale'])) {
    //                 $preferences = ['locale' => $attributeData['preferredLocale']];
    //                 Craft::$app->getUsers()->saveUserPreferences($element, $preferences);
    //             }
    //         }

    //         // Give elements a chance to perform actions after save
    //         $this->_service->afterSave($contentData, $api);

    //         // Fire an 'onStepElementSave' event
    //         $event = new ApiProcessEvent([
    //             'api' => $api,
    //             'apiData' => $apiData,
    //             'contentData' => $contentData,
    //             'element' => $element,
    //         ]);

    //         $this->trigger(self::EVENT_STEP_AFTER_ELEMENT_SAVE, $event);

    //         if ($existingElement) {
    //             EasyApi::info('{name} [`#{id}`]({url}) updated successfully.', ['name' => $this->_service::displayName(), 'id' => $element->id, 'url' => $element->cpEditUrl]);
    //         } else {
    //             EasyApi::info('{name} [`#{id}`]({url}) added successfully.', ['name' => $this->_service::displayName(), 'id' => $element->id, 'url' => $element->cpEditUrl]);
    //         }

    //         // Store our successfully processed element for apiback in logs, but also in case we're deleting
    //         $processedElementIds[] = $element->id;

    //         EasyApi::info('Finished processing of node `#{i}`.', ['i' => ($step + 1)]);

    //         // Sleep if required
    //         $sleepTime = EasyApi::$plugin->service->getConfig('sleepTime', $api['id']);

    //         if ($sleepTime) {
    //             sleep($sleepTime);
    //         }

    //         return $element;
    //     }

    //     if ($element->getErrors()) {
    //         throw new \Exception('Node #' . ($step + 1) . ' - ' . Json::encode($element->getErrors()));
    //     }

    //     throw new \Exception(Craft::t('easyapi', 'Unknown Element saving error occurred.'));
    // }

    // /**
    //  * @param $settings
    //  * @param $api
    //  * @param $processedElementIds
    //  */
    // public function afterProcessApi($settings, $api, $processedElementIds): void
    // {
    //     if ((int)DuplicateHelper::isDelete($api) + (int)DuplicateHelper::isDisable($api) + (int)DuplicateHelper::isDisableForSite($api) > 1) {
    //         EasyApi::info("You can't have Delete and Disabled enabled at the same time as an Import Strategy.");
    //         return;
    //     }

    //     if (DuplicateHelper::isDisableForSite($api) && !$api->siteId) {
    //         EasyApi::info('You can’t choose “Disable missing elements in the target site” for apis without a target site.');
    //         return;
    //     }

    //     if ($processedElementIds) {
    //         $elementsToDeleteDisable = array_diff($settings['existingElements'], $processedElementIds);

    //         if ($elementsToDeleteDisable) {
    //             if (DuplicateHelper::isDisable($api)) {
    //                 $this->_service->disable($elementsToDeleteDisable);
    //                 $message = 'The following elements have been disabled: ' . Json::encode($elementsToDeleteDisable) . '.';
    //             } elseif (DuplicateHelper::isDisableForSite($api)) {
    //                 $this->_service->disableForSite($elementsToDeleteDisable);
    //                 $message = 'The following elements have been disabled for the target site: ' . Json::encode($elementsToDeleteDisable) . '.';
    //             } else {
    //                 $this->_service->delete($elementsToDeleteDisable);
    //                 $message = 'The following elements have been deleted: ' . Json::encode($elementsToDeleteDisable) . '.';
    //             }

    //             EasyApi::info($message);
    //             EasyApi::debug($message);
    //         }
    //     }

    //     // Log the total time taken to process the api
    //     $time_end = microtime(true);
    //     $execution_time = number_format(($time_end - $this->_time_start), 2);

    //     EasyApi::$stepKey = null;

    //     $message = 'Processing ' . ($processedElementIds ? count($processedElementIds) : 0) . ' elements finished in ' . $execution_time . 's';
    //     EasyApi::info($message);
    //     EasyApi::debug($message);

    //     // Fire an 'onProcessApi' event
    //     $event = new ApiProcessEvent([
    //         'api' => $api,
    //     ]);

    //     $this->trigger(self::EVENT_AFTER_PROCESS_API, $event);
    // }

    // /**
    //  * @param $api
    //  * @param $limit
    //  * @param $offset
    //  * @param $processedElementIds
    //  * @throws \Exception
    //  */
    // public function debugApi($api, $limit, $offset, $processedElementIds): void
    // {
    //     $api->debug = true;

    //     $apiData = $api->getApiData();

    //     if ($offset) {
    //         $apiData = array_slice($apiData, $offset);
    //     }

    //     if ($limit) {
    //         $apiData = array_slice($apiData, 0, $limit);
    //     }

    //     // Do we even have any data to process?
    //     if (!$apiData) {
    //         EasyApi::debug('No api items to process.');
    //         return;
    //     }

    //     $apiSettings = $this->beforeProcessApi($api, $apiData);

    //     foreach ($apiData as $key => $data) {
    //         $this->processApi($key, $apiSettings, $processedElementIds);
    //     }

    //     // Check if we need to paginate the api to run again
    //     if ($api->getNextPagination()) {
    //         $this->debugApi($api, null, null, $processedElementIds);
    //     } else {
    //         $this->afterProcessApi($apiSettings, $api, $processedElementIds);
    //     }
    // }

    // /**
    //  * Function to weed out fields that are set to 'noimport'. More complex than usual by the fact
    //  * that complex fields (Table, Matrix) have multiple fields, some of which aren't mapped. This is why all nested fields
    //  * should be templated through the 'fields' index, and this function will take care of things from there.
    //  *
    //  * @param $fields
    //  * @return array
    //  */
    // private function _filterUnmappedFields($fields): array
    // {
    //     if (!is_array($fields)) {
    //         return [];
    //     }

    //     // Find any items like `[title.node] => noimport` and remove the outer field info. Slightly complicated
    //     // for nested block/fields, and if I was better at recursion, this could be more elegant, but loop through a
    //     // bunch of times, removing stuff as we go, starting at the inner nested level. Each loop will remove more levels
    //     // of un-mapped nodes
    //     for ($i = 0; $i < 5; $i++) {
    //         foreach (Hash::flatten($fields) as $key => $value) {
    //             $explode = explode('.', $key);
    //             $lastIndex = array_pop($explode);
    //             $infoPath = implode('.', $explode);

    //             $node = Hash::get($fields, $infoPath . '.node');

    //             if ($lastIndex === 'node' && $value === 'noimport') {
    //                 $fields = Hash::remove($fields, $infoPath);
    //             }

    //             if ($lastIndex === 'fields' && empty($value)) {
    //                 // Remove any empty field definitions - but only if there's no node mapping.
    //                 // This is the case when mapping a value to entries, but not mapping any of its inner element fields.
    //                 // We want to retain the mapping to the outer field, but ditch any inner fields not mapped
    //                 if ($node) {
    //                     $fields = Hash::remove($fields, $infoPath . '.fields');
    //                 } else {
    //                     $fields = Hash::remove($fields, $infoPath);
    //                 }
    //             }

    //             if ($lastIndex === 'blocks' && empty($value)) {
    //                 $fields = Hash::remove($fields, $infoPath);
    //             }
    //         }
    //     }

    //     return $fields;
    // }
}
