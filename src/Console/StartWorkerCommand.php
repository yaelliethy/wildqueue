<?php

namespace WildQueue\Console;

use Illuminate\Console\Command;
use WildQueue\Models\WildQueueWorker;
use WildQueue\WildQueueManager;
use Illuminate\Support\Carbon;

class StartWorkerCommand extends Command
{
    protected $signature = 'wildqueue:work {queue}';
    protected $description = 'Manually starts a tracked worker and logs its PID.';

    protected WildQueueManager $manager;

    public function __construct(WildQueueManager $manager)
    {
        parent::__construct();
        $this->manager = $manager;
    }

    public function handle()
    {
        $queueName = $this->argument('queue');
        $pid = getmypid();

        WildQueueWorker::updateOrCreate(
            ['queue' => $queueName],
            [
                'pid' => $pid,
                'status' => 'running',
                'started_at' => Carbon::now(),
                'last_job_at' => null,
            ]
        );

        $this->info("Starting tracked worker for queue: {$queueName} (PID: {$pid})");

        return $this->call('queue:work', [
            '--queue' => $queueName,
        ]);
    }
} 