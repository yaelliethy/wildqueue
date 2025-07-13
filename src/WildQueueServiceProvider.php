<?php

namespace WildQueue;

use Illuminate\Contracts\Bus\Dispatcher as DispatcherContract;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use WildQueue\Console\ListCommand;
use WildQueue\Console\PruneCommand;
use WildQueue\Console\StartWorkerCommand;
use WildQueue\Console\StopCommand;
use WildQueue\Console\Debug\DispatchCommand;
use WildQueue\Listeners\JobProcessedListener;
use Illuminate\Queue\Events\JobProcessed;

class WildQueueServiceProvider extends ServiceProvider
{
    public function boot(EventsDispatcher $events)
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/wildqueue.php' => config_path('wildqueue.php'),
            ], 'config');

            $this->loadMigrationsFrom(__DIR__.'/database/migrations');

            $this->commands([
                ListCommand::class,
                PruneCommand::class,
                StartWorkerCommand::class,
                StopCommand::class,
                DispatchCommand::class,
            ]);

            // Register auto-pruning with scheduler if enabled
            if (config('wildqueue.auto_prune', true)) {
                $this->app->booted(function () {
                    $schedule = $this->app->make(Schedule::class);
                    $schedule->command('wildqueue:prune')
                        ->everyMinute()
                        ->withoutOverlapping()
                        ->runInBackground();
                });
            }
        }

        $events->listen(JobProcessed::class, JobProcessedListener::class);
        
        // Listen for job queued events to spawn workers
        $events->listen(\Illuminate\Queue\Events\JobQueued::class, function ($event) {
            if (config('wildqueue.enabled')) {
                $manager = $this->app->make(WildQueueManager::class);
                $manager->ensureWorkerIsRunning($event->job->queue ?: config('queue.default'));
            }
        });
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/wildqueue.php',
            'wildqueue'
        );

        $this->app->singleton(WildQueueManager::class, function ($app) {
            return new WildQueueManager($app);
        });
    }
} 