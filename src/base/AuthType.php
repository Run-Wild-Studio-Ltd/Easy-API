<?php

namespace runwildstudio\easyapi\base;

use Cake\Utility\Hash;
use Craft;
use craft\base\Component;
use craft\helpers\UrlHelper;

/**
 *
 * @property-read mixed $name
 * @property-read mixed $class
 */
abstract class AuthType extends Component implements AuthTypeInterface
{
    // Public
    // =========================================================================

    /**
     * @return mixed
     */
    public function getName(): string
    {
        /** @phpstan-ignore-next-line */
        return static::$name;
    }

    /**
     * @return string
     */
    public function getClass(): string
    {
        return get_class($this);
    }
}
