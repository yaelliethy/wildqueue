<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WildQueue Enabled
    |--------------------------------------------------------------------------
    |
    | This option controls whether the dynamic worker spawning is active.
    | You can disable this in environments where you manage workers manually.
    |
    */
    'enabled' => env('WILDQUEUE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Queue Patterns
    |--------------------------------------------------------------------------
    |
    | Define regex patterns for queues that should have workers automatically
    | spawned. If empty, all queues will have workers spawned.
    |
    */
    'queue_patterns' => [
        '/^emails:.*/',
        // '/^notifications:.*/',
        // '/^tenant:\w+:.*/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Patterns
    |--------------------------------------------------------------------------
    |
    | Define regex patterns for queues that should NOT have workers automatically
    | spawned, even if they match the queue_patterns above.
    |
    */
    'excluded_patterns' => [
        // '/^system:.*/',
        // '/.*:internal$/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Idle Timeout
    |--------------------------------------------------------------------------
    |
    | The number of seconds a worker can be idle before it is considered
    | for pruning. An idle worker is one that has not processed a job
    | recently.
    |
    */
    'idle_timeout' => 300, // 5 minutes

    /*
    |--------------------------------------------------------------------------
    | Auto Prune
    |--------------------------------------------------------------------------
    |
    | Whether to automatically register the pruning task with Laravel's
    | scheduler. When enabled, idle workers will be pruned every minute.
    | You can customize the schedule in your App\Console\Kernel class.
    |
    */
    'auto_prune' => env('WILDQUEUE_AUTO_PRUNE', true),

    /*
    |--------------------------------------------------------------------------
    | Cache Duration
    |--------------------------------------------------------------------------
    |
    | The number of seconds to cache worker status to avoid repetitive
    | database and process checks. This improves performance during job
    | bursts but may delay detection of dead workers.
    |
    */
    'cache_duration' => env('WILDQUEUE_CACHE_DURATION', 30),
]; 