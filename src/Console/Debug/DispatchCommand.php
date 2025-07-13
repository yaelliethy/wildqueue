<?php

namespace WildQueue\Console\Debug;

use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class DebugJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        // This is a dummy job for testing purposes.
        sleep(5);
    }
}

class DispatchCommand extends Command
{
    protected $signature = 'wildqueue:debug:dispatch';
    protected $description = 'Dispatches a test job to a random queue name.';

    public function handle()
    {
        $queueName = 'emails:user-' . rand(100, 999);
        $this->info("Dispatching a debug job to queue: {$queueName}");
        
        dispatch(new DebugJob())->onQueue($queueName);

        $this->info("Job dispatched. A worker should be spawned shortly if one isn't running.");
    }
} 