<?php

namespace runwildstudio\easyapi\web\twig;

use Cake\Utility\Hash;
use runwildstudio\easyapi\EasyApi;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigFilter;

class Extension extends AbstractExtension
{
    // Public Methods
    // =========================================================================

    public function getName(): string
    {
        return 'Hash - Get';
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('hash_get', [$this, 'hashGet']),
        ];
    }

    public function hashGet($array, $value)
    {
        if (is_array($array)) {
            return Hash::get($array, $value);
        }

        return null;
    }
    
    //
    // Helper functions for authorization fields
    //

    public function getRegisteredApiAuthType($handle): mixed
    {
        return EasyApi::$plugin->EasyApiAuthTypes->_authTypes[$handle] ?? null;
    }

    public function getFilters()
    {
        return [
            new TwigFilter('version_compare', 'version_compare'),
        ];
    }
}
