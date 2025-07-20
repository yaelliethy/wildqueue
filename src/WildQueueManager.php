<?php

namespace WildQueue;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;
use WildQueue\Models\WildQueueWorker;

class WildQueueManager
{
    protected Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function ensureWorkerIsRunning(string $queueName): void
    {
        if (!config('wildqueue.enabled', true)) {
            return;
        }

        // Check if queue matches patterns
        if (!$this->shouldSpawnWorkerForQueue($queueName)) {
            return;
        }

        // Check cache first to avoid database queries
        $cacheKey = "wildqueue:worker:{$queueName}";
        if (Cache::get($cacheKey)) {
            return; // Worker is cached as active
        }
        //Check if worker is already running
        $worker = WildQueueWorker::where('queue', $queueName)->where('status', 'running')->first();
        if ($worker) {
            return;
        }
        $worker = WildQueueWorker::firstOrCreate(
            ['queue' => $queueName],
            ['status' => 'stopped']
        );

        if (!$this->isProcessRunning($worker->pid)) {
            $this->spawnWorker($queueName);
        } else {
            // Worker is running, cache it
            Cache::put($cacheKey, true, config('wildqueue.cache_duration', 30));
        }
    }

    public function spawnWorker(string $queueName): WildQueueWorker
    {
        $logPath = storage_path("logs/queue-worker-{$queueName}.log");

        // Ensure logs directory exists
        if (!is_dir(dirname($logPath))) {
            mkdir(dirname($logPath), 0755, true);
        }

        $command = "php artisan queue:work --queue={$queueName} >> {$logPath} 2>&1";

        $process = Process::fromShellCommandline(
            $command,
            base_path(),
            [
                'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
                'PHP_BINARY' => PHP_BINARY
            ]
        );

        $process->start();

        $worker = WildQueueWorker::updateOrCreate(
            ['queue' => $queueName],
            [
                'pid' => $process->getPid(),
                'status' => 'running',
                'started_at' => Carbon::now(),
                'last_job_at' => null,
            ]
        );

        // Cache the worker as active
        Cache::put("wildqueue:worker:{$queueName}", true, config('wildqueue.cache_duration', 30));

        return $worker;
    }

    public function stopWorker(string $queueName): bool
    {
        $worker = WildQueueWorker::where('queue', $queueName)->first();

        if (!$worker || !$this->isProcessRunning($worker->pid)) {
            if ($worker) {
                $worker->update(['status' => 'stopped', 'pid' => null]);
            }
            // Remove from cache
            Cache::forget("wildqueue:worker:{$queueName}");
            return false;
        }

        $process = new Process(['kill', $worker->pid]);
        $process->run();

        if ($process->isSuccessful()) {
            $worker->update(['status' => 'stopped', 'pid' => null]);
            Cache::forget("wildqueue:worker:{$queueName}");
            return true;
        }

        return false;
    }

    public function updateLastJobAt(string $queueName): void
    {
        WildQueueWorker::where('queue', $queueName)
            ->where('status', 'running')
            ->update(['last_job_at' => Carbon::now()]);
    }

    public function pruneIdleWorkers(): int
    {
        $prunedCount = 0;
        $idleTimeout = config('wildqueue.idle_timeout', 300);

        $workersToPrune = WildQueueWorker::where('status', 'running')
            ->where(function ($query) use ($idleTimeout) {
                $query->where('last_job_at', '<', Carbon::now()->subSeconds($idleTimeout))
                      ->orWhere(function ($q) use ($idleTimeout) {
                          $q->whereNull('last_job_at')
                            ->where('started_at', '<', Carbon::now()->subSeconds($idleTimeout));
                      });
            })
            ->get();

        foreach ($workersToPrune as $worker) {
            if ($this->stopWorker($worker->queue)) {
                $prunedCount++;
            }
        }

        return $prunedCount;
    }

    protected function isProcessRunning(?int $pid): bool
    {
        if ($pid === null) {
            return false;
        }

        $process = Process::fromShellCommandline("ps -p {$pid}");
        $process->run();

        return $process->isSuccessful() && str_contains($process->getOutput(), (string) $pid);
    }

    /**
     * Check if a worker should be spawned for the given queue based on patterns
     */
    protected function shouldSpawnWorkerForQueue(string $queueName): bool
    {
        $queuePatterns = config('wildqueue.queue_patterns', []);
        $excludedPatterns = config('wildqueue.excluded_patterns', []);

        // If no patterns defined, spawn workers for all queues
        if (empty($queuePatterns)) {
            $shouldSpawn = true;
        } else {
            // Check if queue matches any of the allowed patterns
            $shouldSpawn = false;
            foreach ($queuePatterns as $pattern) {
                if (preg_match($pattern, $queueName)) {
                    $shouldSpawn = true;
                    break;
                }
            }
        }

        // Check if queue matches any excluded patterns
        if ($shouldSpawn && !empty($excludedPatterns)) {
            foreach ($excludedPatterns as $pattern) {
                if (preg_match($pattern, $queueName)) {
                    $shouldSpawn = false;
                    break;
                }
            }
        }

        return $shouldSpawn;
    }
}
