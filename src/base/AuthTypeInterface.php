<?php

namespace runwildstudio\easyapi\base;

use craft\base\ComponentInterface;

interface AuthTypeInterface extends ComponentInterface
{
    // Public Methods
    // =========================================================================

    /**
     * @param $url
     * @param $settings
     * @param bool $usePrimaryElement
     * @return mixed
     */
    public function getAuthValue($api): mixed;

    /**
     * @return mixed
     */
    public function getFieldsTemplate(): string;

    /**
     * @return string
     */
    public function getClass(): string;
}
