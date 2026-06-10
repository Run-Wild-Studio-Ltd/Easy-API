<?php

namespace runwildstudio\easyapi\services;

use ArrayAccess;
use Cake\Utility\Hash;
use Craft;
use craft\base\Component;
use craft\commerce\elements\Product;
use craft\elements\Entry;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Tag;
use craft\elements\GlobalSet;
use craft\feedme\events\FeedDataEvent;
use craft\feedme\Plugin as FeedMePlugin;
use runwildstudio\easyapi\EasyApi;
use runwildstudio\easyapi\helpers\DataHelper;
use runwildstudio\easyapi\services\EasyApiDataTypes;
use craft\helpers\DateTimeHelper;
use DateTime;
use Exception;
use GuzzleHttp\Client;

class FeedMeEvents extends Component
{
    public function getDataForFeedMe(FeedDataEvent $event) {
        $api = EasyApi::$plugin->apis->getApiByFeedId($event->feedId);
        $apiUrl = $event->url;

        if (!$api) {
            Craft::error("EasyApi: API not found for feedId={$event->feedId}", __METHOD__);
            $event->response = [
                'success' => false,
                'error' => 'API not found for feed.',
                'data' => null,
            ];
            return;
        }

        $parentElementTypeSupported = true;
        if (!empty($api->parentElementType)) {
            $parentElementTypeSupported = $this->isFeedMeSupportedElementType($api->parentElementType);
        }

        if ($event->feedId !== null && FeedMePlugin::$plugin !== null) {
            $feed = FeedMePlugin::$plugin->feeds->getFeedById($event->feedId);
            if ($feed && (!$this->isFeedMeSupportedElementType($feed->elementType) || !$parentElementTypeSupported)) {
                Craft::info(
                    "EasyApi: FeedMe destination elementType '{$feed->elementType}' or parent elementType '{$api->parentElementType}' is not supported; skipping EasyApi processing.",
                    __METHOD__
                );
                $responseData = EasyApiDataTypes::getRawData($apiUrl, $api->id);

                if (!empty($api->postImportHandler)) {
                    [$moduleKey, $method] = explode('.', $api->postImportHandler);
                    $module = Craft::$app->getModule($moduleKey);
                    if ($module && method_exists($module, $method)) {
                        $module->$method($responseData['data'] ?? null, $apiUrl, $api->id);
                    } else {
                        Craft::warning("EasyApi: Handler not callable: {$api->postImportHandler}", __METHOD__);
                    }
                }

                $event->response = $responseData;
                return;
            }
        }

        $responseData = null;

        try {
            // Handle parent elements if defined
            if ($api->parentElementType) {
                $originalUrl = $apiUrl;

                // Fetch parent elements
                $parents = [];
                switch ($api->parentElementType) {
                    case 'craft\\elements\\Asset':
                        $assetId = $api->parentElementGroup[$api->parentElementType] ?? null;
                        $parents = Asset::find()
                            ->siteId($api->siteId ?: Craft::$app->sites->getCurrentSite()->id)
                            ->assetId($assetId)
                            ->all();
                        break;

                    case 'craft\\elements\\Category':
                        $groupId = $api->parentElementGroup[$api->parentElementType] ?? null;
                        $parents = Category::find()
                            ->siteId($api->siteId ?: Craft::$app->sites->getCurrentSite()->id)
                            ->groupId($groupId)
                            ->all();
                        break;

                    case 'craft\\elements\\Entry':
                        $type = (string)$api->parentElementType;

                        $decodeIfJson = function ($v) {
                            if (is_string($v) && $v !== '') {
                                $d = json_decode($v, true);
                                if (json_last_error() === JSON_ERROR_NONE) {
                                    return $d;
                                }
                            }
                            return $v;
                        };

                        $findGroup = function ($groups, $type) {
                            if (!is_array($groups)) {
                                return null;
                            }

                            if (isset($groups[$type])) {
                                return $groups[$type];
                            }

                            $tNorm = trim(str_replace('\\\\', '\\', $type));
                            foreach ($groups as $k => $v) {
                                $kNorm = trim(str_replace('\\\\', '\\', (string)$k));
                                if ($kNorm === $tNorm) {
                                    return $v;
                                }
                            }

                            return $groups['Entry'] ?? null;
                        };

                        $peg = $decodeIfJson($api->parentElementGroup ?? null);
                        $entryGroup = $findGroup($peg, $type);

                        if ($entryGroup === null) {
                            $eg = $decodeIfJson($api->elementGroup ?? null);
                            $entryGroup = $findGroup($eg, $type);
                        }

                        $sectionIdRaw   = $entryGroup['section']   ?? ($entryGroup->section   ?? null);
                        $entryTypeIdRaw = $entryGroup['entryType'] ?? ($entryGroup->entryType ?? null);

                        if (!$sectionIdRaw || !$entryTypeIdRaw) {
                            $event->response = [
                                'success' => false,
                                'error' => 'EasyApi config error: Entry parent group missing section + entryType (check EasyApi save/keys).',
                                'data' => null,
                            ];
                            return;
                        }

                        // --- Resolve IDs if they are handles/UIDs/strings ---
                        $sectionId = $sectionIdRaw;
                        $entryTypeId = $entryTypeIdRaw;

                        // If section is not numeric, try resolve by handle
                        if (!is_numeric($sectionId)) {
                            $section = Craft::$app->entries->getSectionByHandle((string)$sectionId);
                            if ($section) {
                                $sectionId = $section->id;
                            }
                        }

                        // --- DROP-IN REPLACEMENT: replace EVERYTHING from
                        // // Build base query ... down to the end of the "Fallback: if typeId mismatched" block
                        // in your `case 'craft\\elements\\Entry':` with THIS. ---

                        $baseQ = Entry::find()
                            ->sectionId($sectionId)
                            ->status(null)
                            ->drafts(null)
                            ->provisionalDrafts(null);

                        // ✅ IMPORTANT: Entries are implicitly scoped to the CURRENT site unless you set siteId().
                        // Multisite-safe: if api->siteId is not explicitly set, search across ALL sites.
                        if (!empty($api->siteId)) {
                            $baseQ->siteId($api->siteId);
                        } else {
                            $baseQ->siteId('*');
                        }

                        // First try section + type (ideal)
                        $q = clone $baseQ;
                        $q->typeId($entryTypeId);
                        $parents = $q->all();

                        // Fallback: if typeId mismatched, try section-only
                        if (empty($parents)) {
                            $q2 = clone $baseQ;
                            $parents = $q2->all();

                            if (!empty($parents)) {
                                Craft::warning(
                                    "EasyApi: Entry parent fetch returned 0 for sectionId={$sectionId} + typeId={$entryTypeId}; falling back to section-only parents (" . count($parents) . "). Check configured entryType for section.",
                                    __METHOD__
                                );
                            }
                        }


                        break;


                    case 'craft\\elements\\Tag':
                        $tagId = $api->parentElementGroup[$api->parentElementType] ?? null;
                        $parents = Tag::find()
                            ->siteId($api->siteId ?: Craft::$app->sites->getCurrentSite()->id)
                            ->tagId($tagId)
                            ->all();
                        break;

                    case 'craft\\commerce\\elements\\Product':
                        $productTypeId = $api->parentElementGroup[$api->parentElementType]['productType'] ?? null;

                        $q = Product::find()->siteId($api->siteId ?: Craft::$app->sites->getCurrentSite()->id);

                        if ($productTypeId) {
                            $q->typeId($productTypeId);
                        }

                        $parents = $q->status(null)->all();
                        break;

                    case 'craft\\elements\\GlobalSet':
                        $globalSetId = $api->parentElementGroup[$api->parentElementType]->globalSet ?? null;
                        $parents = GlobalSet::find()
                            ->siteId($api->siteId ?: Craft::$app->sites->getCurrentSite()->id)
                            ->globalSetId($globalSetId)
                            ->all();
                        break;

                    default:
                        Craft::warning("EasyApi: Unknown parentElementType: {$api->parentElementType}", __METHOD__);
                        break;
                }

                // If parent mode is enabled, but parent selection yields nothing, fail loudly and early.
                if (empty($parents)) {
                    Craft::error(
                        "EasyApi: Parent fetch returned 0. type={$api->parentElementType}, siteId=" . var_export($api->siteId, true),
                        __METHOD__
                    );

                    $event->response = [
                        'success' => false,
                        'error' => "EasyApi: Parent fetch returned 0 elements for {$api->parentElementType}. Check section/entryType + site context.",
                        'data' => null,
                    ];
                    return;
                }

                Craft::info(
                    "EasyApi: Parent fetch. type={$api->parentElementType}, parents=" . count($parents),
                    __METHOD__
                );

                // Pre-check field handle existence
                $handle = $api->parentElementIdField;
                $field = $handle ? Craft::$app->fields->getFieldByHandle($handle) : null;

                if (!$field) {
                    Craft::warning("EasyApi: parentElementIdField '{$handle}' not found as a Craft field.", __METHOD__);
                }

                foreach ($parents as $parent) {
                    try {
                        $dynamicValue = $parent->getFieldValue($api->parentElementIdField);
                    } catch (\Throwable $e) {
                        Craft::error(
                            "EasyApi: FAILED getFieldValue('{$api->parentElementIdField}') on " . get_class($parent) . " id={$parent->id}: " . $e->getMessage(),
                            __METHOD__
                        );
                        throw $e;
                    }

                    if ($dynamicValue === null || $dynamicValue === '') {
                        Craft::warning(
                            "EasyApi: Empty dynamic value for '{$api->parentElementIdField}' on " . get_class($parent) . " id={$parent->id}",
                            __METHOD__
                        );
                        continue;
                    }

                    $modifiedUrl = str_replace('{{ Id }}', (string)$dynamicValue, $originalUrl);
                    $apiData = EasyApiDataTypes::getRawData($modifiedUrl, $api->id);

                    if (!empty($api->postImportHandler)) {
                        [$moduleKey, $method] = explode('.', $api->postImportHandler);

                        $module = Craft::$app->getModule($moduleKey);
                        if ($module && method_exists($module, $method)) {
                            $module->$method($apiData['data'], $modifiedUrl, $api->id);
                        } else {
                            Craft::warning("EasyApi: Handler not callable: {$api->postImportHandler}", __METHOD__);
                        }

                        $responseData = $apiData; // last response wins
                        continue;
                    }

                    if ($responseData) {
                        $array1 = json_decode($responseData['data'], true) ?? [];
                        $array2 = json_decode($apiData['data'], true) ?? [];
                        $responseData['data'] = json_encode(array_merge_recursive($array1, $array2));
                    } else {
                        $responseData = $apiData;
                    }
                }

                $apiUrl = $originalUrl;

                // If we got here but never set responseData (e.g. all parents had empty dynamicValue), return a clear error.
                if ($responseData === null) {
                    $event->response = [
                        'success' => false,
                        'error' => 'EasyApi: No parent requests executed (dynamic value empty for all parents).',
                        'data' => null,
                    ];
                    return;
                }

            } else {
                // No parent element, fetch directly
                $responseData = EasyApiDataTypes::getRawData($apiUrl, $api->id);

                if (!empty($api->postImportHandler)) {
                    [$moduleKey, $method] = explode('.', $api->postImportHandler);

                    $module = Craft::$app->getModule($moduleKey);
                    if ($module && method_exists($module, $method)) {
                        $module->$method($responseData['data'], $apiUrl, $api->id);
                    } else {
                        Craft::warning("EasyApi: Handler not callable: {$api->postImportHandler}", __METHOD__);
                    }
                }
            }

            // Handle success / pagination
            if (!empty($responseData['success'])) {
                $tempArray = json_decode($responseData['data'], true) ?? [];
                $this->updatePaginationNode($api, $tempArray);
                $responseData['data'] = json_encode($tempArray);
            } else {
                $responseData = [
                    'success' => false,
                    'error' => $responseData['data'] ?? 'Unknown error',
                    'data' => null,
                ];
            }

            $event->response = $responseData;

        } catch (\Throwable $e) {
            EasyApi::error('`{e} - {f}: {l}`.', [
                'e' => $e->getMessage(),
                'f' => basename($e->getFile()),
                'l' => $e->getLine()
            ]);
            Craft::$app->getErrorHandler()->logException($e);

            $event->response = [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    public function updatePaginationNode($api, &$data) {
        if ($api) {
            if ($api->offsetField != null && $api->offsetField != "") {
                DataHelper::updateOffsetValue($api, $data);
            }
        }
    }

    public function isFeedMeSupportedElementType(?string $elementType): bool
    {
        if (!$elementType) {
            return false;
        }

        if (FeedMePlugin::$plugin === null) {
            return false;
        }

        return FeedMePlugin::$plugin->elements->getRegisteredElement($elementType) !== null;
    }

}
