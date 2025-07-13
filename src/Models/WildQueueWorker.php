<?php

namespace WildQueue\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $queue
 * @property ?int $pid
 * @property string $status
 * @property ?Carbon $last_job_at
 * @property ?Carbon $started_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class WildQueueWorker extends Model
{
    use HasFactory;

    protected $table = 'wildqueue_workers';

    protected $fillable = [
        'queue',
        'pid',
        'status',
        'last_job_at',
        'started_at',
    ];

    protected $casts = [
        'last_job_at' => 'datetime',
        'started_at' => 'datetime',
    ];

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isIdle(): bool
    {
        if ($this->status !== 'running') {
            return false;
        }

        $idleTimeout = config('wildqueue.idle_timeout', 300);
        
        $lastActivity = $this->last_job_at ?? $this->started_at;

        return $lastActivity->diffInSeconds(Carbon::now()) > $idleTimeout;
    }
} 