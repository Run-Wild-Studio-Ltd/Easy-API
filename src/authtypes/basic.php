<?php

namespace runwildstudio\easyapi\authtypes;

use Craft;
use runwildstudio\easyapi\base\AuthType;
use runwildstudio\easyapi\EasyApi;
use Exception;

class basic extends AuthType
{
    // Properties
    // =========================================================================

    /**
     * @var string
     */
    public static string $name = 'Basic';

    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function getAuthValue($api): array
    {
        $auth = [];
        // Parse custom parameters
        if (!empty($api->authorizationCustomParameters)) {
            $authorizationCustomParameters = explode(',', $api->authorizationCustomParameters);
            foreach ($authorizationCustomParameters as $param) {
                list($key, $value) = explode('=', trim($param));
                $auth[] = trim($key) . ': ' . trim($value);
            }
        }

        // Make sure auth has been populated!
        if ($api->authorization != '') {
            $auth[] = 'Authorization: ' . $api->authorization;
        }

        if (count($auth) > 0) {
            return ['success' => true, 'value' => $auth];
        }
        return ['success' => false, 'error' => 'Authorization value not specified'];
    }

    // Templates
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function getFieldsTemplate(): string
    {
        return 'easyapi/_includes/authtypes/basic/fields';
    }
}
