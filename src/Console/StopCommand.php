<?php

namespace WildQueue\Console;

use Illuminate\Console\Command;
use WildQueue\WildQueueManager;

class StopCommand extends Command
{
    protected $signature = 'wildqueue:stop {queue}';
    protected $description = 'Stops a running worker and updates the database.';
    
    protected WildQueueManager $manager;

    public function __construct(WildQueueManager $manager)
    {
        parent::__construct();
        $this->manager = $manager;
    }

    public function handle()
    {
        $queueName = $this->argument('queue');

        $this->info("Attempting to stop worker for queue: {$queueName}");

        if ($this->manager->stopWorker($queueName)) {
            $this->info("Successfully stopped worker for queue: {$queueName}");
        } else {
            $this->error("Failed to stop worker for queue: {$queueName}. It might not be running.");
        }
    }
} 