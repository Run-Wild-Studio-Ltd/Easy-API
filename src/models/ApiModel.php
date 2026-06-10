<?php

namespace runwildstudio\easyapi\models;

use ArrayAccess;
use Cake\Utility\Hash;
use Craft;
use craft\base\Model;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\GlobalSet;
use craft\elements\Tag;
use runwildstudio\easyapi\base\Element;
use runwildstudio\easyapi\base\ElementInterface;
use runwildstudio\easyapi\helpers\DuplicateHelper;
use runwildstudio\easyapi\EasyApi;
use DateTime;

/**
 * Class ApiModel
 *
 * @property-read mixed $dataType
 * @property-read bool $nextPagination
 * @property-read ElementInterface|Element|null $element
 */
class ApiModel extends Model
{
    // Properties
    // =========================================================================

    public ?int $id = null;
    public string $name = '';
    public ?string $apiUrl = null;
    public ?string $contentType = null;
    public ?string $authorizationType = null;
    public ?string $authorizationUrl = null;
    public ?string $authorizationAppId = null;
    public ?string $authorizationAppSecret = null;
    public ?string $authorizationScope = null;
    public ?string $authorizationGrantType = null;
    public ?string $authorizationUsername = null;
    public ?string $authorizationPassword = null;
    public ?string $authorizationRedirect = null;
    public ?string $authorizationCode = null;
    public ?string $authorizationRefreshToken = null;
    public ?string $authorizationCustomParameters = null;
    public ?string $authorization = null;
    public ?string $httpAction = null;
    public mixed $updateElementIdField = null;
    public ?string $direction = null;
    public ?string $requestHeader = null;
    public ?string $requestBody = null;
    public ?string $parentElementType = null;
    public ?array $parentElementGroup = null;
    public ?string $parentElementIdField = null;
    public ?string $parentFilter = null;
    public ?string $offsetField = null;
    public ?string $offsetUpateURL = null;
    public ?string $offsetTermination = null;
    public ?bool $queueRequest = false;
    public ?int $queueOrder = null;
    public ?bool $useLive = false;
    public ?int $feedId = null;
    public ?int $siteId = null;
    public ?int $sortOrder = null;
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    public ?string $uid = null;

    // Feed fields required to create a new feed
    public ?string $elementType = null;
    public ?array $elementGroup = null;
    public ?array $duplicateHandle = null;
    public ?string $passkey = null;

    //Module to run after processing
    public ?string $postImportHandler = null;

    // Public Methods
    // =========================================================================

    public function __toString()
    {
        return Craft::t('easyapi', $this->name);
    }

    public function getAuthType(): mixed
    {
        return EasyApi::$plugin->auth->getRegisteredApiAuthType($this->authorizationType);
    }

    public function getDataType(): mixed
    {
        return EasyApi::$plugin->data->getRegisteredApiDataType($this->contentType);
    }

    public function getParentElement(): ElementInterface|Element|null
    {
        if ($this->parentElementType == null) {
            return null;
        }

        $element = EasyApi::$plugin->elements->getRegisteredElement($this->parentElementType);

        if ($element) {
            /** @var Element $element */
            $element->api = $this;
        }

        return $element;
    }

    public function getApiData(bool $usePrimaryElement = true): mixed
    {
        $apiDataResponse = EasyApi::$plugin->data->getApiData($this, $usePrimaryElement);
        return Hash::get($apiDataResponse, 'data');
    }

    /**
     * Validation rules
     *
     * @return array
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [['name', 'apiUrl', 'contentType', 'authorizationType'], 'required'],

            // OAuth2 Scope validation
            ['authorizationScope', 'string'],
            ['authorizationScope', 'default', 'value' => null],
            ['postImportHandler', 'string', 'max' => 255],
            ['postImportHandler', 'default', 'value' => null],
        ]);
    }
}
