<?php

namespace runwildstudio\easyapi\models;

use craft\base\Model;

class Settings extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var string
     */
    public string $pluginName = 'Easy API';

    /**
     * @var int
     */
    public int $jobQueueInterval = 60;

    /**
     * @var int
     */
    public int $cache = 60;

    /**
     * @var int|null
     */
    public ?int $queueTtr = null;

    /**
     * @var int|null
     */
    public ?int $queueMaxRetry = null;

    /**
     * Whether CP "Run" actions should spawn a detached queue drain process.
     * Disable on environments with a dedicated queue listener (e.g. Docker)
     * so feed imports are only ever processed by that single worker.
     * @var bool
     */
    public bool $spawnQueueRunner = true;
}