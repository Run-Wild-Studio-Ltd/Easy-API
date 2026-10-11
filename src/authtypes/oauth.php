<?php

namespace runwildstudio\easyapi\authtypes;

use Craft;
use runwildstudio\easyapi\base\AuthType;
use runwildstudio\easyapi\services\Apis;
use runwildstudio\easyapi\EasyApi;
use Exception;

class oauth extends AuthType
{
    // Properties
    // =========================================================================

    /**
     * @var string
     */
    public static string $name = 'OAuth';

    /**
     * In-request token cache keyed by api/credentials hash
     */
    private static array $_tokens = [];

    /**
     * @return string Cache key for the api's token
     */
    private function _cacheKey($api): string
    {
        return 'easyapi-oauth-' . ($api->id ?? 0) . '-' . md5(
            ($api->authorizationUrl ?? '') .
            ($api->authorizationAppId ?? '') .
            ($api->authorizationAppSecret ?? '') .
            ($api->authorizationScope ?? '')
        );
    }

    private function getAuthToken($api): array
    {
        try {
            $curl = curl_init();
            // POST data
            $postData = [
                'client_id' => $api->authorizationAppId,
                'client_secret' => $api->authorizationAppSecret,
                'grant_type' => $api->authorizationGrantType
            ];
            // Add additional fields based on grant_type
            switch ($api->authorizationGrantType) {
                case 'authorization_code':
                    if (!empty($api->authorizationCode)) {
                        $postData['code'] = $api->authorizationCode;
                    }
                    if (!empty($api->authorizationRedirect)) {
                        $postData['redirect_uri'] = $api->authorizationRedirect;
                    }
                    break;

                case 'password':
                    if (!empty($api->authorizationUsername)) {
                        $postData['username'] = $api->authorizationUsername;
                    }
                    if (!empty($api->authorizationPassword)) {
                        $postData['password'] = $api->authorizationPassword;
                    }
                    break;

                case 'refresh_token':
                    if (!empty($api->authorizationRefreshToken)) {
                        $postData['refresh_token'] = $api->authorizationRefreshToken;
                    }
                    break;

                case 'client_credentials':
                    // No additional fields required
                    break;

                default:
                    // Handle unsupported grant_type if needed
                    break;
            }

            // Parse custom parameters
            if (!empty($api->authorizationCustomParameters)) {
                $authorizationCustomParameters = explode(',', $api->authorizationCustomParameters);
                foreach ($authorizationCustomParameters as $param) {
                    list($key, $value) = explode('=', trim($param));
                    $postData[trim($key)] = trim($value);
                }
            }

            curl_setopt_array($curl, array(
                CURLOPT_URL => $api->authorizationUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => 1,
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CUSTOMREQUEST => 'POST'
            ));

            $data = curl_exec($curl);
            curl_close($curl);
            $data_decode = json_decode($data);
            $api->authorizationRefreshToken = $data_decode->refresh_token;
            $apiService = new Apis();
            $apiService->saveApi($api);

            $response = ['success' => true, 'value' => $data_decode->access_token];
        } catch (Exception $e) {
            $response = ['success' => false, 'error' => $e->getMessage()];
            Craft::$app->getErrorHandler()->logException($e);
        }

        return $response;
    }


    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function getAuthValue($api): array
    {
        $cacheKey = $this->_cacheKey($api);

        // In-request cache first, then persistent cache
        if (isset(self::$_tokens[$cacheKey])) {
            return ['success' => true, 'value' => 'oauth-token: ' . self::$_tokens[$cacheKey]];
        }

        $cached = Craft::$app->getCache()->get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            self::$_tokens[$cacheKey] = $cached;
            return ['success' => true, 'value' => 'oauth-token: ' . $cached];
        }

        $token = $this->getAuthToken($api);
        if ($token['success']) {
            self::$_tokens[$cacheKey] = $token['value'];
            Craft::$app->getCache()->set($cacheKey, $token['value'], 3540);
            return ['success' => true, 'value' => 'oauth-token: ' . $token['value']];
        } else {
            return ['success' => false, 'error' => $token['error']];
        }
    }

    // Templates
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function getFieldsTemplate(): string
    {
        return 'easyapi/_includes/authtypes/oauth/fields';
    }
}
