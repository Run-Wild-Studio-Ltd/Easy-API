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
     * Optional daily run time (HH:MM, 24h). When set, the job queue only
     * processes APIs at/after this time each day, instead of every
     * jobQueueInterval minutes.
     * @var string|null
     */
    public ?string $jobQueueTime = null;

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
     * Seconds to sleep after each feed element is processed. Throttles
     * imports (e.g. feeds that upload many images/assets) so they don't
     * spike server resources.
     * @var int
     */
    public int $sleepTime = 0;

    /**
     * Whether CP "Run" actions should spawn a detached queue drain process.
     * Disable on environments with a dedicated queue listener (e.g. Docker)
     * so feed imports are only ever processed by that single worker.
     * @var bool
     */
    public bool $spawnQueueRunner = true;
}