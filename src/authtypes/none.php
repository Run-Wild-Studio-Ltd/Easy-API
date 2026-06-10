<?php

namespace runwildstudio\easyapi\authtypes;

use Craft;
use runwildstudio\easyapi\base\AuthType;
use Exception;

class none extends AuthType
{
    /**
     * @var string
     */
    public static string $name = 'None';

    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function getAuthValue($api): array
    {
        try {
            // In "None" auth, authorization should be empty/null.
            // Use null-coalesce in case property isn't set.
            $auth = $api->authorization ?? null;

            // If anything has been provided, treat it as misconfigured
            if (!($auth === null || $auth === '')) {
                return [
                    'success' => false,
                    'error' => 'Authorization value has been specified incorrectly.'
                ];
            }

            // Match oauth behaviour: return a string "value"
            // For no-auth, this should be an empty string.
            return [
                'success' => true,
                'value' => ''
            ];
        } catch (Exception $e) {
            Craft::$app->getErrorHandler()->logException($e);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Templates
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function getFieldsTemplate(): string
    {
        return 'easyapi/_includes/authtypes/none/fields';
    }
}
