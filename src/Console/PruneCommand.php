<?php

namespace WildQueue\Console;

use Illuminate\Console\Command;
use WildQueue\WildQueueManager;

class PruneCommand extends Command
{
    protected $signature = 'wildqueue:prune';
    protected $description = 'Kills all workers idle for longer than the configured timeout.';
    
    protected WildQueueManager $manager;

    public function __construct(WildQueueManager $manager)
    {
        parent::__construct();
        $this->manager = $manager;
    }

    public function handle()
    {
        $this->info('Pruning idle WildQueue workers...');

        $prunedCount = $this->manager->pruneIdleWorkers();

        if ($prunedCount > 0) {
            $this->info("Successfully pruned {$prunedCount} idle workers.");
        } else {
            $this->info('No idle workers to prune.');
        }
    }
} 