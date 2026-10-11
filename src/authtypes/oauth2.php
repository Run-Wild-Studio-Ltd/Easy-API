<?php

namespace runwildstudio\easyapi\authtypes;

use Craft;
use runwildstudio\easyapi\base\AuthType;
use runwildstudio\easyapi\services\Apis;
use Exception;

class oauth2 extends AuthType
{
    public static string $name = 'OAuth2';

    /**
     * In-request token cache keyed by api/credentials hash
     */
    private static array $_tokens = [];

    /**
     * @return string Cache key for the api's token
     */
    private function _cacheKey($api): string
    {
        return 'easyapi-oauth2-' . ($api->id ?? 0) . '-' . md5(
            ($api->authorizationUrl ?? '') .
            ($api->authorizationAppId ?? '') .
            ($api->authorizationAppSecret ?? '') .
            ($api->authorizationScope ?? '')
        );
    }

    /**
     * Perform OAuth2 token request
     */
    private function requestToken($api): array
    {
        try {
            Craft::info('OAuth2 requestToken START', __METHOD__);

            $curl = curl_init();

            // Base body params for OAuth2
            $postData = [
                'client_id' => $api->authorizationAppId,
                'client_secret' => $api->authorizationAppSecret,
                'grant_type' => $api->authorizationGrantType,
            ];

            if (!empty($api->authorizationScope)) {
                $postData['scope'] = $api->authorizationScope;
            } elseif ($api->authorizationGrantType === 'client_credentials') {
                $postData['scope'] = 'https://graph.microsoft.com/.default';
            }

            switch ($api->authorizationGrantType) {
                case 'authorization_code':
                    if ($api->authorizationCode) $postData['code'] = $api->authorizationCode;
                    if ($api->authorizationRedirect) $postData['redirect_uri'] = $api->authorizationRedirect;
                    break;
                case 'password':
                    if ($api->authorizationUsername) $postData['username'] = $api->authorizationUsername;
                    if ($api->authorizationPassword) $postData['password'] = $api->authorizationPassword;
                    break;
                case 'refresh_token':
                    if ($api->authorizationRefreshToken) $postData['refresh_token'] = $api->authorizationRefreshToken;
                    break;
                case 'client_credentials':
                    break;
                default:
                    throw new Exception("Unsupported OAuth2 grant type: {$api->authorizationGrantType}");
            }

            if (!empty($api->authorizationCustomParameters)) {
                foreach (explode(',', $api->authorizationCustomParameters) as $param) {
                    [$key, $value] = array_map('trim', explode('=', $param));
                    $postData[$key] = $value;
                }
            }

            Craft::info('OAuth2 token request URL: ' . $api->authorizationUrl, __METHOD__);

            curl_setopt_array($curl, [
                CURLOPT_URL            => $api->authorizationUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query($postData),
                CURLOPT_TIMEOUT        => 30,
            ]);

            $responseRaw = curl_exec($curl);

            if ($responseRaw === false) {
                $errorMsg = "cURL error: " . curl_error($curl);
                Craft::error($errorMsg, __METHOD__);
                throw new Exception($errorMsg);
            }

            $httpInfo = curl_getinfo($curl);
            curl_close($curl);

            Craft::info('OAuth2 token request HTTP code: ' . ($httpInfo['http_code'] ?? 'unknown'), __METHOD__);

            $json = json_decode($responseRaw, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Craft::error('OAuth2 JSON decode error: ' . json_last_error_msg() . ' RAW RESPONSE: ' . $responseRaw, __METHOD__);
            }

            if (!isset($json['access_token'])) {
                $errorDesc = $json['error_description'] ?? $json['error'] ?? 'no access_token in response';
                throw new Exception("OAuth2 token request failed: " . $errorDesc);
            }

            if (isset($json['refresh_token'])) {
                $api->authorizationRefreshToken = $json['refresh_token'];
                (new Apis())->saveApi($api);
            }

            Craft::info('OAuth2 requestToken END - SUCCESS', __METHOD__);

            return [
                'success' => true,
                'access_token' => $json['access_token'],
                'expires_in' => $json['expires_in'] ?? null,
            ];

        } catch (Exception $e) {
            Craft::$app->getErrorHandler()->logException($e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Craft EasyAPI will call this to get the header value
     */
    public function getAuthValue($api): array
    {
        $cacheKey = $this->_cacheKey($api);

        // In-request cache first, then persistent cache
        if (isset(self::$_tokens[$cacheKey])) {
            return ['success' => true, 'value' => 'Bearer ' . self::$_tokens[$cacheKey]];
        }

        $cached = Craft::$app->getCache()->get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            self::$_tokens[$cacheKey] = $cached;
            return ['success' => true, 'value' => 'Bearer ' . $cached];
        }

        Craft::info('OAuth2 getAuthValue: requesting new token', __METHOD__);

        $token = $this->requestToken($api);

        if (!$token['success']) {
            Craft::error('OAuth2 getAuthValue error: ' . ($token['error'] ?? 'NO ERROR KEY'), __METHOD__);
            return [
                'success' => false,
                'error' => $token['error'] ?? 'Unknown error'
            ];
        }

        // Cache for the token's lifetime, minus a 60s safety buffer
        $ttl = $token['expires_in'] ? max(60, (int)$token['expires_in'] - 60) : 3540;
        self::$_tokens[$cacheKey] = $token['access_token'];
        Craft::$app->getCache()->set($cacheKey, $token['access_token'], $ttl);

        Craft::info('OAuth2 getAuthValue END - SUCCESS', __METHOD__);

        return [
            'success' => true,
            'value' => 'Bearer ' . $token['access_token']
        ];
    }

    public function getFieldsTemplate(): string
    {
        return 'easyapi/_includes/authtypes/oauth2/fields';
    }
}
