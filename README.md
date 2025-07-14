# WildQueue - Dynamic Laravel Queue Worker Management

A Laravel package that automatically manages workers for dynamically named queues without Redis or Horizon.

## Features

- 🧠 **Dynamic Worker Spawning**: Automatically spawns workers when jobs are dispatched to new queues
- 📊 **Database Tracking**: Tracks all workers and their status in the database
- ⚡ **Optimized Performance**: Uses caching to avoid repetitive database/process checks
- 🔄 **Automatic Pruning**: Uses Laravel's scheduler to automatically clean up idle workers
- 📈 **Activity Tracking**: Monitors job processing to determine worker idle time
- ⚙️ **Rich CLI Commands**: Complete set of management commands
- 🎯 **Regex Pattern Matching**: Filter which queues should have workers spawned using regex patterns

## Installation

1. Add the package to your project:
```bash
composer require wildqueue/wildqueue
```

2. Publish the configuration:
```bash
php artisan vendor:publish --provider="WildQueue\WildQueueServiceProvider" --tag="config"
```

3. **Migration Setup**: 

   **Option A (Automatic)** - The migration will be automatically available after installation:
   ```bash
   php artisan migrate
   ```

   **Option B (Manual)** - If the migration doesn't appear, publish it manually:
   ```bash
   php artisan vendor:publish --provider="WildQueue\WildQueueServiceProvider" --tag="migrations"
   php artisan migrate
   ```

## Configuration

Edit `config/wildqueue.php`:

```php
return [
    'enabled' => true,           // Enable/disable dynamic worker spawning
    'idle_timeout' => 300,       // Seconds before a worker is considered idle (5 minutes)
    'auto_prune' => true,        // Automatically register pruning with Laravel scheduler
    'cache_duration' => 30,      // How long to cache worker status (seconds)
    
    // Define regex patterns for queues that should have workers spawned
    'queue_patterns' => [
        '/^emails:.*/',          // Match all queues starting with 'emails:'
        '/^notifications:.*/',   // Match all queues starting with 'notifications:'
        '/^tenant:\w+:.*/',      // Match tenant-specific queues
    ],
    
    // Define regex patterns for queues that should NOT have workers spawned
    'excluded_patterns' => [
        '/^system:.*/',          // Exclude system queues
        '/.*:internal$/',        // Exclude internal queues
    ],
];
```

### Cache Duration

The `cache_duration` setting controls how long WildQueue caches the status of workers to avoid repetitive database queries and process checks. This significantly improves performance during job bursts but may delay detection of dead workers.

**Configuration Options:**

```php
// In config/wildqueue.php
'cache_duration' => 30,  // Cache for 30 seconds (default)
```

```bash
# Or via environment variable in .env
WILDQUEUE_CACHE_DURATION=15
```

**Use Cases:**

- **Default (30 seconds)**: Good balance for most applications
- **High-frequency jobs (60 seconds)**: Better performance for applications with many rapid job dispatches
- **Critical responsiveness (10-15 seconds)**: Faster worker respawn detection for time-sensitive applications
- **Development/testing (0 seconds)**: Disables caching for immediate feedback (not recommended for production)

**Example:**
```bash
# For faster worker recovery in production
WILDQUEUE_CACHE_DURATION=15

# For high-throughput systems
WILDQUEUE_CACHE_DURATION=60

# For development (immediate worker detection)
WILDQUEUE_CACHE_DURATION=0
```

### Pattern Matching Examples

```php
// Match all email queues
'/^emails:.*/' → matches: emails:user-123, emails:newsletter, emails:welcome

// Match tenant-specific queues
'/^tenant:\w+:.*/' → matches: tenant:abc:notifications, tenant:xyz:reports

// Match user-specific queues
'/.*:user-\d+$/' → matches: emails:user-123, notifications:user-456

// Exclude system queues
'/^system:.*/' → excludes: system:cleanup, system:maintenance
```

## Usage

### Assigning Queue Names

You can assign queue names to your jobs using any standard Laravel method. WildQueue will automatically detect the resolved queue name and manage workers accordingly. Supported methods include:

- **Using `onQueue()` method** (most common):
  ```php
  dispatch(new SendEmailJob($user))->onQueue('emails:user-' . $user->id);
  ```
- **Setting the `$queue` property in the constructor**:
  ```php
  class SendEmailJob implements ShouldQueue {
      use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
      public function __construct($user) {
          $this->queue = 'emails:user-' . $user->id;
      }
  }
  ```
- **Modifying the `$queue` property after instantiation**:
  ```php
  $job = new SendEmailJob($user);
  $job->queue = 'emails:user-' . $user->id;
  dispatch($job);
  ```
- **Using `onQueue()` in the constructor**:
  ```php
  class SendEmailJob implements ShouldQueue {
      public function __construct($user) {
          $this->onQueue('emails:user-' . $user->id);
      }
  }
  ```
- **Dynamic assignment with a method**:
  ```php
  class SendEmailJob implements ShouldQueue {
      public function __construct($user) {
          $this->queue = $this->generateQueueName($user);
      }
      private function generateQueueName($user) {
          return 'emails:user-' . $user->id;
      }
  }
  ```

**WildQueue will work with all of these methods.**

#### Priority
- The `onQueue()` method always takes precedence over property assignments.
- Laravel resolves the final queue name before the `JobQueued` event, which WildQueue listens to.

### Pattern-Based Worker Spawning

With the configuration above:
- ✅ `emails:user-123` - Worker spawned (matches `/^emails:.*/`)
- ✅ `notifications:welcome` - Worker spawned (matches `/^notifications:.*/`)
- ❌ `system:cleanup` - No worker spawned (excluded by `/^system:.*/`)

### Automatic Pruning

The package automatically registers a scheduled task to prune idle workers every minute when `auto_prune` is enabled (default).

Make sure your Laravel scheduler is running:

```bash
php artisan schedule:run
```

Or add this to your crontab:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

### Custom Pruning Schedule

If you want to customize the pruning schedule, set `auto_prune` to `false` and add your own schedule in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('wildqueue:prune')
        ->everyFiveMinutes()
        ->withoutOverlapping();
}
```

### CLI Commands

- `wildqueue:list` - Show all tracked workers and their status
- `wildqueue:stop {queue}` - Stop a specific worker
- `wildqueue:prune` - Manually prune idle workers
- `wildqueue:work {queue}` - Start a tracked worker for a specific queue
- `wildqueue:debug:dispatch` - Dispatch a test job to a random queue

## How It Works

1. **Job Dispatch**: When you dispatch a job with `->onQueue('queue-name')`, the `JobQueued` event is triggered
2. **Pattern Matching**: The package checks if the queue matches any configured patterns
3. **Cache Check**: If patterns match, checks if a worker for that queue is cached as active (30-second cache)
4. **Worker Spawn**: If no cached worker exists, checks the database and spawns a new worker if needed
5. **Activity Tracking**: The `JobProcessed` event updates the `last_job_at` timestamp for the queue
6. **Automatic Pruning**: Laravel's scheduler runs `wildqueue:prune` every minute to clean up idle workers

## Performance Optimizations

- **Caching**: Active workers are cached for 30 seconds to avoid repetitive database queries (time is configurable)
- **Persistent Workers**: Workers don't exit after processing jobs, they continue running
- **Scheduler-Based Pruning**: Uses Laravel's native scheduler for reliable, efficient cleanup
- **Pattern Filtering**: Only spawn workers for queues that match your business logic

## Testing

Test pattern matching:
```bash
# This should spawn a worker (if emails pattern is enabled)
php artisan tinker --execute="dispatch(new App\Jobs\TestJob())->onQueue('emails:user-123');"

# This should NOT spawn a worker (if system pattern is excluded)
php artisan tinker --execute="dispatch(new App\Jobs\TestJob())->onQueue('system:cleanup');"
```

View active workers:
```bash
php artisan wildqueue:list
```

Manually prune idle workers:
```bash
php artisan wildqueue:prune
```
## Best Practices

- Use `onQueue()` for one-off queue assignments
- Use constructor property assignment for consistent queue logic
- Use dynamic methods when queue names depend on model properties or complex logic
- Ensure queue names match your configured patterns in `wildqueue.php` 

## ☕  Support

To support this project (and my studies) please consider buying a coffee:


[![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20A%20Coffee-%23FFDD00?style=for-the-badge&logo=buy-me-a-coffee&logoColor=black)](https://www.buymeacoffee.com/yaelliethy)

## License

MIT 

