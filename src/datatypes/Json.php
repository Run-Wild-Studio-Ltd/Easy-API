<?php

namespace runwildstudio\easyapi\datatypes;

use Cake\Utility\Hash;
use Craft;
use runwildstudio\easyapi\base\DataType;
use runwildstudio\easyapi\base\DataTypeInterface;
use runwildstudio\easyapi\EasyApi;

class Json extends DataType implements DataTypeInterface
{
    public static string $name = 'JSON';

    /**
     * @inheritDoc
     */
    public function getApi($url, $settings, bool $usePrimaryElement = true): array
    {
        // Fetch raw data from EasyApi
        $response = EasyApi::getRawData($url, $settings->id ?? null);

        // Log the raw API response
        //Craft::info('EasyAPI raw response: ' . print_r($response, true), __METHOD__);

        // Return the response unchanged
        return $response;
    }
}
