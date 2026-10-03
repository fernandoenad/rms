<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class SystemHealthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    protected function ensureAdmin(): void
    {
        abort_unless((int) optional(optional(auth()->user())->role)->level === 1, 403);
    }

    public function index()
    {
        $this->ensureAdmin();

        $checks = [];
        $dbHealthy = false;
        try {
            DB::select('select 1');
            $dbHealthy = true;
            $checks[] = $this->check('Database', 'HEALTHY', 'Database connection is responding.');
        } catch (Throwable $e) {
            $checks[] = $this->check('Database', 'ERROR', Str::limit($e->getMessage(), 180));
        }

        try {
            $key = 'system-health:probe:'.Str::random(8);
            Cache::put($key, 'ok', 30);
            $cacheHealthy = Cache::get($key) === 'ok';
            Cache::forget($key);
            $checks[] = $this->check(
                'Cache',
                $cacheHealthy ? 'HEALTHY' : 'ERROR',
                $cacheHealthy ? 'Cache read/write probe passed ('.config('cache.default').').' : 'Cache probe did not return the expected value.'
            );
        } catch (Throwable $e) {
            $checks[] = $this->check('Cache', 'ERROR', Str::limit($e->getMessage(), 180));
        }

        $queueConnection = (string) config('queue.default');
        $jobsAvailable = Schema::hasTable('jobs');
        $failedAvailable = Schema::hasTable('failed_jobs');

        $queuedTotal = $jobsAvailable ? DB::table('jobs')->count() : null;
        $failedTotal = $failedAvailable ? DB::table('failed_jobs')->count() : null;
        $oldestQueuedAt = $jobsAvailable
            ? DB::table('jobs')->min('created_at')
            : null;

        if ($queueConnection === 'sync') {
            $checks[] = $this->check('Queue', 'WARNING', 'QUEUE_CONNECTION is sync; background work runs inside web requests.');
        } elseif ($queueConnection === 'database' && !$jobsAvailable) {
            $checks[] = $this->check('Queue', 'ERROR', 'Database queue is configured but the jobs table is missing.');
        } else {
            $queueDetail = $queuedTotal === null
                ? 'Queue connection: '.$queueConnection.'.'
                : number_format($queuedTotal).' queued job(s) on '.$queueConnection.'.';
            if ($oldestQueuedAt) {
                $queueDetail .= ' Oldest queued job: '.\Carbon\Carbon::createFromTimestamp((int) $oldestQueuedAt)->diffForHumans().'.';
            }
            $checks[] = $this->check('Queue', ($failedTotal ?? 0) > 0 ? 'WARNING' : 'HEALTHY', $queueDetail);
        }

        $heartbeatRaw = Cache::get('assessment-center:scheduler-heartbeat');
        $heartbeat = $heartbeatRaw ? now()->parse($heartbeatRaw) : null;
        $schedulerHealthy = $heartbeat && $heartbeat->gte(now()->subMinutes(3));
        $checks[] = $this->check(
            'Scheduler',
            $schedulerHealthy ? 'HEALTHY' : 'ERROR',
            $heartbeat ? 'Last heartbeat '.$heartbeat->diffForHumans().'.' : 'No scheduler heartbeat has been recorded.'
        );

        $storageWritable = is_writable(storage_path()) && is_writable(storage_path('logs'));
        $checks[] = $this->check(
            'Storage',
            $storageWritable ? 'HEALTHY' : 'ERROR',
            $storageWritable ? 'Storage and log directories are writable.' : 'Storage or log directory is not writable.'
        );

        $diskFree = @disk_free_space(storage_path());
        $diskTotal = @disk_total_space(storage_path());
        $diskPercentFree = ($diskFree !== false && $diskTotal) ? round(($diskFree / $diskTotal) * 100, 1) : null;
        if ($diskPercentFree !== null && $diskPercentFree < 10) {
            $checks[] = $this->check('Disk Space', 'WARNING', $diskPercentFree.'% free space remaining.');
        } else {
            $checks[] = $this->check('Disk Space', 'HEALTHY', $diskPercentFree === null ? 'Disk space could not be measured.' : $diskPercentFree.'% free space remaining.');
        }

        $queueByName = collect();
        if ($jobsAvailable) {
            $queueByName = DB::table('jobs')
                ->select('queue', DB::raw('COUNT(*) as total'), DB::raw('MIN(created_at) as oldest_created_at'))
                ->groupBy('queue')
                ->orderByDesc('total')
                ->get();
        }

        $failedJobs = collect();
        if ($failedAvailable) {
            $failedJobs = DB::table('failed_jobs')
                ->orderByDesc('failed_at')
                ->limit(25)
                ->get()
                ->map(function ($job) {
                    $job->exception_summary = Str::limit(strtok((string) $job->exception, "\n") ?: 'Unknown failure', 300);
                    return $job;
                });
        }

        $log = $this->readRecentLaravelErrors();
        $overall = collect($checks)->contains(fn ($check) => $check['status'] === 'ERROR')
            ? 'ERROR'
            : (collect($checks)->contains(fn ($check) => $check['status'] === 'WARNING') ? 'WARNING' : 'HEALTHY');

        $metrics = [
            'queued_jobs' => $queuedTotal,
            'failed_jobs' => $failedTotal,
            'recent_errors' => count($log['errors']),
            'log_size' => $log['size'],
            'disk_free' => $diskFree === false ? null : $diskFree,
            'disk_total' => $diskTotal === false ? null : $diskTotal,
        ];

        return view('admin.system_health.index', compact(
            'overall',
            'checks',
            'metrics',
            'queueByName',
            'failedJobs',
            'heartbeat',
            'schedulerHealthy',
            'log'
        ));
    }

    public function status()
    {
        $this->ensureAdmin();

        $database = true;
        try {
            DB::select('select 1');
        } catch (Throwable $e) {
            $database = false;
        }

        $heartbeatRaw = Cache::get('assessment-center:scheduler-heartbeat');
        $heartbeat = $heartbeatRaw ? now()->parse($heartbeatRaw) : null;
        $scheduler = (bool) ($heartbeat && $heartbeat->gte(now()->subMinutes(3)));

        $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null;
        $queued = Schema::hasTable('jobs') ? DB::table('jobs')->count() : null;

        $healthy = $database && $scheduler && (($failed ?? 0) === 0);

        return response()->json([
            'status' => $healthy ? 'HEALTHY' : 'CHECK',
            'generated_at' => now()->toIso8601String(),
            'database' => $database,
            'scheduler' => $scheduler,
            'queue_connection' => config('queue.default'),
            'queued_jobs' => $queued,
            'failed_jobs' => $failed,
        ], $healthy ? 200 : 503);
    }

    public function retryFailedJob(string $uuid)
    {
        $this->ensureAdmin();
        abort_unless(Schema::hasTable('failed_jobs'), 404);

        $job = DB::table('failed_jobs')->where('uuid', $uuid)->first();
        abort_unless($job, 404);

        Artisan::call('queue:retry', ['id' => [$uuid]]);

        return back()->with('status', 'Failed job was returned to its original queue.');
    }

    public function forgetFailedJob(string $uuid)
    {
        $this->ensureAdmin();
        abort_unless(Schema::hasTable('failed_jobs'), 404);

        $deleted = DB::table('failed_jobs')->where('uuid', $uuid)->delete();

        return back()->with('status', $deleted ? 'Failed-job record cleared.' : 'Failed job was no longer present.');
    }

    protected function check(string $name, string $status, string $detail): array
    {
        return compact('name', 'status', 'detail');
    }

    protected function readRecentLaravelErrors(): array
    {
        $path = storage_path('logs/laravel.log');

        if (!File::exists($path)) {
            $dailyLogs = collect(File::glob(storage_path('logs/laravel-*.log')))
                ->filter(fn ($candidate) => File::isFile($candidate))
                ->sortByDesc(fn ($candidate) => File::lastModified($candidate))
                ->values();

            $path = $dailyLogs->first();
        }

        if (!$path || !File::exists($path)) {
            return ['path' => storage_path('logs'), 'size' => 0, 'errors' => [], 'available' => false];
        }

        $size = File::size($path);
        $maxBytes = 1024 * 1024;
        $start = max(0, $size - $maxBytes);

        $handle = @fopen($path, 'rb');
        if (!$handle) {
            return ['path' => $path, 'size' => $size, 'errors' => [], 'available' => false];
        }

        if ($start > 0) {
            fseek($handle, $start);
            fgets($handle);
        }

        $content = stream_get_contents($handle) ?: '';
        fclose($handle);

        $lines = preg_split('/\R/', $content) ?: [];
        $errors = [];

        foreach ($lines as $line) {
            if (!preg_match('/^\[(?<time>[^\]]+)\]\s+(?<env>[^.]+)\.(?<level>ERROR|CRITICAL|ALERT|EMERGENCY):\s+(?<message>.*)$/', $line, $match)) {
                continue;
            }

            $message = trim($match['message']);
            $contextPos = strpos($message, ' {');
            if ($contextPos !== false) {
                $message = substr($message, 0, $contextPos);
            }

            $errors[] = [
                'time' => $match['time'],
                'level' => $match['level'],
                'message' => Str::limit($message, 700),
            ];
        }

        $errors = array_slice(array_reverse($errors), 0, 50);

        return [
            'path' => $path,
            'size' => $size,
            'errors' => $errors,
            'available' => true,
        ];
    }
}
