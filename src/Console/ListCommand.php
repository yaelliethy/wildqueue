<?php

namespace WildQueue\Console;

use Illuminate\Console\Command;
use WildQueue\Models\WildQueueWorker;
use Illuminate\Support\Carbon;

class ListCommand extends Command
{
    protected $signature = 'wildqueue:list';
    protected $description = 'Shows all known queues, their PIDs, statuses, and idle durations.';

    public function handle()
    {
        $workers = WildQueueWorker::all();

        if ($workers->isEmpty()) {
            $this->info('No WildQueue workers are being tracked.');
            return;
        }

        $tableData = $workers->map(function ($worker) {
            $idleDuration = 'N/A';
            if ($worker->status === 'running') {
                $lastActivity = $worker->last_job_at ?? $worker->started_at;
                if ($lastActivity) {
                    $idleDuration = $lastActivity->diffForHumans(Carbon::now(), true);
                }
            }
            
            return [
                'Queue' => $worker->queue,
                'PID' => $worker->pid ?? 'N/A',
                'Status' => $worker->status,
                'Idle For' => $idleDuration,
                'Started At' => $worker->started_at ? $worker->started_at->toDateTimeString() : 'N/A',
            ];
        });

        $this->table(
            ['Queue', 'PID', 'Status', 'Idle For', 'Started At'],
            $tableData
        );
    }
} 