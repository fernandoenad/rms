@extends('adminlte::page')

@section('title','System Health')

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">System Health</h1>
        <small class="text-muted">Operational status, queue health, scheduler checks, storage, and recent application errors</small>
    </div>
    <div class="mt-2 mt-md-0">
        <a href="{{ route('admin.system_health.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-sync-alt mr-1"></i> Refresh
        </a>
    </div>
</div>
@stop

@section('content')
@if(session('status'))
<div class="alert alert-info">{{ session('status') }}</div>
@endif

@php
    $statusClass = fn($status) => $status === 'HEALTHY' ? 'success' : ($status === 'WARNING' ? 'warning' : 'danger');
    $bytes = function($value) {
        if ($value === null) return 'N/A';
        $units = ['B','KB','MB','GB','TB'];
        $n = (float) $value; $i = 0;
        while ($n >= 1024 && $i < count($units)-1) { $n /= 1024; $i++; }
        return number_format($n, $i ? 1 : 0).' '.$units[$i];
    };
@endphp

<div class="alert alert-{{ $overall === 'HEALTHY' ? 'success' : ($overall === 'WARNING' ? 'warning' : 'danger') }}">
    <strong>Overall status: {{ $overall }}</strong>
    @if($overall !== 'HEALTHY')
        <div class="small mt-1">One or more checks need attention. Review the cards and recent errors below.</div>
    @else
        <div class="small mt-1">Core services are responding normally.</div>
    @endif
</div>

<div class="row">
    @foreach($checks as $check)
    <div class="col-md-6 col-xl-4">
        <div class="card h-75">
            <div class="card-body">
                <span class="badge badge-{{ $statusClass($check['status']) }} float-right">{{ $check['status'] }}</span>
                <h6 class="font-weight-bold">{{ $check['name'] }}</h6>
                <p class="small text-muted mb-0">{{ $check['detail'] }}</p>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="row">
    <div class="col-sm-6 col-lg-2">
        <div class="small-box bg-white border"><div class="inner"><h4>{{ $metrics['queued_jobs'] ?? 'N/A' }}</h4><p>Queued jobs</p></div><div class="icon"><i class="fas fa-clock"></i></div></div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="small-box bg-white border"><div class="inner"><h4>{{ $metrics['failed_jobs'] ?? 'N/A' }}</h4><p>Failed jobs</p></div><div class="icon"><i class="fas fa-exclamation-triangle"></i></div></div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="small-box bg-white border"><div class="inner"><h4>{{ $metrics['recent_errors'] }}</h4><p>Recent errors</p></div><div class="icon"><i class="fas fa-bug"></i></div></div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="small-box bg-white border"><div class="inner"><h4>{{ $bytes($metrics['log_size']) }}</h4><p>Laravel log</p></div><div class="icon"><i class="fas fa-file-alt"></i></div></div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="small-box bg-white border"><div class="inner"><h4>{{ $bytes($metrics['disk_free']) }}</h4><p>Disk free</p></div><div class="icon"><i class="fas fa-hdd"></i></div></div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="small-box bg-white border"><div class="inner"><h4>{{ $heartbeat ? $heartbeat->diffForHumans() : 'Never' }}</h4><p>Scheduler heartbeat</p></div><div class="icon"><i class="fas fa-heartbeat"></i></div></div>
    </div>
</div>

<div class="row">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><strong>Queue Status</strong></div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Queue</th><th>Waiting</th><th>Oldest</th></tr></thead>
                    <tbody>
                    @forelse($queueByName as $queue)
                        <tr>
                            <td><code>{{ $queue->queue }}</code></td>
                            <td>{{ number_format($queue->total) }}</td>
                            <td>
                                {{ $queue->oldest_created_at
                                    ? CarbonCarbon::createFromTimestamp((int)$queue->oldest_created_at)->diffForHumans()
                                    : '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-3">No queued jobs.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Recent Failed Queue Jobs</strong>
                @if(($metrics['failed_jobs'] ?? 0) > 0)
                    <a href="{{ route('admin.assessment_center.index') }}" class="btn btn-xs btn-outline-secondary">Assessment Queue Controls</a>
                @endif
            </div>
            <div class="table-responsive" style="max-height:360px">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Queue</th><th>Failure</th><th>Failed</th><th></th></tr></thead>
                    <tbody>
                    @forelse($failedJobs as $job)
                        <tr>
                            <td><code>{{ $job->queue }}</code></td>
                            <td class="small text-danger text-break">{{ $job->exception_summary }}</td>
                            <td class="text-nowrap small">{{ CarbonCarbon::parse($job->failed_at)->diffForHumans() }}</td>
                            <td class="text-nowrap">
                                <form method="post" action="{{ route('admin.system_health.failed_jobs.retry',$job->uuid) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-xs btn-outline-primary" title="Retry this failed job"><i class="fas fa-redo"></i></button>
                                </form>
                                <form method="post" action="{{ route('admin.system_health.failed_jobs.forget',$job->uuid) }}" class="d-inline"
                                      onsubmit="return confirm('Clear this failed-job record?');">
                                    @csrf @method('delete')
                                    <button class="btn btn-xs btn-outline-danger" title="Clear failed-job record"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No failed queue jobs.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card card-outline {{ count($log['errors']) ? 'card-danger' : 'card-success' }}">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <strong>Recent Laravel Errors</strong>
            <div class="small text-muted">Latest ERROR/CRITICAL/ALERT/EMERGENCY entries from the last 1 MB of <code>storage/logs/laravel.log</code>.</div>
        </div>
        <span class="badge badge-{{ count($log['errors']) ? 'danger' : 'success' }}">{{ count($log['errors']) }} shown</span>
    </div>
    <div class="table-responsive" style="max-height:520px">
        <table class="table table-sm table-hover mb-0">
            <thead><tr><th style="width:170px">Time</th><th style="width:90px">Level</th><th>Error</th></tr></thead>
            <tbody>
            @if(!$log['available'])
                <tr><td colspan="3" class="text-center text-muted py-4">Laravel log is not available or cannot be read.</td></tr>
            @else
                @forelse($log['errors'] as $error)
                    <tr>
                        <td class="text-nowrap small">{{ $error['time'] }}</td>
                        <td><span class="badge badge-danger">{{ $error['level'] }}</span></td>
                        <td class="small text-break">{{ $error['message'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center text-success py-4"><i class="fas fa-check-circle mr-1"></i>No recent Laravel error entries found.</td></tr>
                @endforelse
            @endif
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>Machine-readable Status</strong></div>
    <div class="card-body">
        <p class="text-muted mb-2">Authenticated JSON endpoint for a quick administrative or uptime check.</p>
        <code>{{ route('admin.system_health.status') }}</code>
    </div>
</div>
@stop
