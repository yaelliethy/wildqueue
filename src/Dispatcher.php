<?php

namespace WildQueue;

use Illuminate\Contracts\Bus\QueueingDispatcher;
use Illuminate\Contracts\Container\Container;
use Illuminate\Bus\Dispatcher as LaravelDispatcher;

class Dispatcher extends LaravelDispatcher implements QueueingDispatcher
{
    protected LaravelDispatcher $dispatcher;
    protected WildQueueManager $manager;

    public function __construct(Container $container, LaravelDispatcher $dispatcher, WildQueueManager $manager)
    {
        parent::__construct($container);
        $this->dispatcher = $dispatcher;
        $this->manager = $manager;
    }

    public function dispatchToQueue($command)
    {
        $queueName = $command->queue ?? config('queue.default');
        
        if (config('wildqueue.enabled')) {
            $this->manager->ensureWorkerIsRunning($queueName);
        }

        return parent::dispatchToQueue($command);
    }
} 