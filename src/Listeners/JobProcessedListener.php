<?php

namespace WildQueue\Listeners;

use Illuminate\Queue\Events\JobProcessed;
use WildQueue\WildQueueManager;

class JobProcessedListener
{
    protected WildQueueManager $manager;

    public function __construct(WildQueueManager $manager)
    {
        $this->manager = $manager;
    }

    public function handle(JobProcessed $event)
    {
        $queueName = $event->job->getQueue();
        if ($queueName) {
            $this->manager->updateLastJobAt($queueName);
        }
    }
} 