@extends('adminlte::page')
@section('title','Assessment Group Results')
@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">{{ $assessmentGroup->title }}</h1>
        <small class="text-muted">Live monitoring · {{ optional($assessmentGroup->vacancy)->position_title }}</small>
    </div>
    <div class="mt-2 mt-md-0">
        <a href="{{ route('admin.assessment_groups.analytics',$assessmentGroup) }}" class="btn btn-outline-info mr-2"><i class="fas fa-chart-line"></i> Analytics</a>
        <a href="{{ route('admin.assessment_groups.export',$assessmentGroup) }}" class="btn btn-outline-success mr-2"><i class="fas fa-file-csv"></i> Export CSV</a>
        <a href="{{ route('admin.assessment_groups.edit',$assessmentGroup) }}" class="btn btn-outline-secondary">Back to Group</a>
    </div>
</div>
@stop

@section('content')
@if(session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif

<div class="row">
    <div class="col-lg-3 col-6"><div class="small-box bg-info"><div class="inner"><h3>{{ number_format((int)$dashboard->attempted) }}</h3><p>Attempted</p></div><div class="icon"><i class="fas fa-users"></i></div></div></div>
    <div class="col-lg-3 col-6"><div class="small-box bg-warning"><div class="inner"><h3>{{ number_format((int)$dashboard->taking_now) }}</h3><p>Taking Now</p></div><div class="icon"><i class="fas fa-user-clock"></i></div></div></div>
    <div class="col-lg-3 col-6"><div class="small-box bg-success"><div class="inner"><h3>{{ number_format((int)$dashboard->submitted) }}</h3><p>Submitted</p></div><div class="icon"><i class="fas fa-check-circle"></i></div></div></div>
    <div class="col-lg-3 col-6"><div class="small-box bg-light"><div class="inner"><h3>{{ number_format((float)$dashboard->completion_rate,1) }}%</h3><p>Completion Rate</p></div><div class="icon"><i class="fas fa-chart-pie"></i></div></div></div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Per-Set Monitoring</strong>
        <div class="small text-muted">Descriptive only; compare equivalent sets cautiously until enough submissions exist.</div>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-sm table-hover mb-0">
            <thead><tr><th>Set</th><th>Attempted</th><th>Taking</th><th>Submitted</th><th>Mean</th><th>SD</th></tr></thead>
            <tbody>
            @foreach($setSummary as $set)
                <tr>
                    <td><span class="badge badge-primary">{{ $set['set_code'] ?: $set['title'] }}</span></td>
                    <td>{{ number_format($set['attempted']) }}</td>
                    <td>{{ number_format($set['in_progress']) }}</td>
                    <td>{{ number_format($set['submitted']) }}</td>
                    <td>{{ $set['mean_score'] !== null ? number_format($set['mean_score'],2).'%' : '-' }}</td>
                    <td>{{ $set['score_sd'] !== null ? number_format($set['score_sd'],2) : '-' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

@if($comparabilityWarnings)
<div class="alert alert-warning">
    <strong>Set comparability review:</strong>
    <ul class="mb-0">@foreach($comparabilityWarnings as $warning)<li>{{ $warning }}</li>@endforeach</ul>
</div>
@endif

@if((int)$dashboard->awaiting_timeout_finalization > 0)
<div class="alert alert-warning">
    <i class="fas fa-clock mr-1"></i>
    {{ number_format((int)$dashboard->awaiting_timeout_finalization) }} expired attempt(s) are waiting for the scheduled finalizer.
</div>
@endif

<div class="card card-outline card-secondary">
    <div class="card-header"><strong>Operational Health</strong></div>
    <div class="card-body py-2">
        <div class="row text-center">
            <div class="col-6 col-md-3"><strong>{{ number_format((int)$health['recent_answer_activity']) }}</strong><br><small class="text-muted">answers updated in last minute</small></div>
            <div class="col-6 col-md-3"><strong>{{ $health['queue_jobs'] === null ? 'N/A' : number_format((int)$health['queue_jobs']) }}</strong><br><small class="text-muted">queued jobs</small></div>
            <div class="col-6 col-md-3"><strong>{{ $health['failed_jobs'] === null ? 'N/A' : number_format((int)$health['failed_jobs']) }}</strong><br><small class="text-muted">failed jobs</small></div>
            <div class="col-6 col-md-3"><strong>{{ number_format((int)$health['open_incidents']) }}</strong><br><small class="text-muted">open incidents</small></div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
    <div class="small text-muted">
        Applicants appear as soon as they start. Started attempts are never hard-deleted; controlled retakes preserve the original record.
    </div>
    <div>
        <label class="small mb-0 mr-2"><input type="checkbox" id="autoRefresh"> Auto-refresh every 60 seconds</label>
        <a href="{{ request()->fullUrl() }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-sync-alt"></i> Refresh</a>
    </div>
</div>

<div class="card">
    <div class="card-body table-responsive p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Applicant</th><th>Set</th><th>Status</th><th>Started</th><th>Time</th><th>Score</th><th>Submitted</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($attempts as $attempt)
                <tr>
                    <td>
                        {{ optional($attempt->application)->getFullname() }}<br>
                        <small class="text-muted">{{ optional($attempt->application)->application_code }}</small>
                    </td>
                    <td><span class="badge badge-primary">{{ optional($attempt->exam)->set_code ?: optional($attempt->exam)->title }}</span></td>
                    <td>
                        @if((int)$attempt->status === 1)
                            @if($attempt->expires_at && now()->gte($attempt->expires_at))
                                <span class="badge badge-warning">Awaiting finalization</span>
                            @else
                                <span class="badge badge-info">In progress</span>
                            @endif
                        @elseif((int)$attempt->status === 2)
                            <span class="badge badge-success">Submitted</span>
                        @elseif((int)$attempt->status === 3)
                            <span class="badge badge-secondary">Voided</span>
                        @endif
                    </td>
                    <td>{{ optional($attempt->started_at)->format('M d, Y h:i A') }}</td>
                    <td>
                        @if((int)$attempt->status === 1 && $attempt->expires_at)
                            {{ now()->lt($attempt->expires_at) ? now()->diffForHumans($attempt->expires_at, ['parts'=>2,'short'=>true]).' left' : 'Expired' }}
                        @elseif((int)$attempt->status === 2)
                            Completed
                        @elseif((int)$attempt->status === 3)
                            Voided
                        @endif
                    </td>
                    <td>
                        @if((int)$attempt->status === 2)
                            {{ $attempt->correct_answers ?? '-' }} / {{ $attempt->total_items ?? '-' }}
                            @if($attempt->percentage !== null)<br><strong>{{ number_format((float)$attempt->percentage,2) }}%</strong>@endif
                        @else -
                        @endif
                    </td>
                    <td>{{ optional($attempt->ended_at)->format('M d, Y h:i A') ?: '-' }}</td>
                    <td style="min-width:220px">
                        <details>
                            <summary class="btn btn-sm btn-outline-secondary">Manage</summary>
                            <div class="border rounded p-2 mt-2">
                                <form method="post" action="{{ route('admin.assessment_groups.incidents.store',$assessmentGroup) }}" class="mb-3">@csrf
                                    <input type="hidden" name="exam_attempt_id" value="{{ $attempt->id }}">
                                    <label class="small">Incident</label>
                                    <select name="type" class="form-control form-control-sm mb-1" required>
                                        <option value="connectivity">Connectivity</option><option value="device">Device</option><option value="power">Power</option><option value="proctoring">Proctoring</option><option value="administrative">Administrative</option><option value="other">Other</option>
                                    </select>
                                    <textarea name="notes" class="form-control form-control-sm mb-1" rows="2" required placeholder="Incident notes"></textarea>
                                    <button class="btn btn-sm btn-outline-info">Record Incident</button>
                                </form>

                                @if((int)$attempt->status===1)
                                <form method="post" action="{{ route('admin.assessment_groups.attempts.extend',[$assessmentGroup,$attempt]) }}" class="mb-3">@csrf
                                    <label class="small">Approved time extension</label>
                                    <div class="form-row">
                                        <div class="col-4"><input type="number" min="1" max="240" name="minutes" class="form-control form-control-sm" placeholder="Minutes" required></div>
                                        <div class="col-8"><input name="reason" class="form-control form-control-sm" placeholder="Reason / accommodation" required></div>
                                    </div>
                                    <button class="btn btn-sm btn-outline-primary mt-1">Add Time</button>
                                </form>
                                @endif

                                <details class="mb-3">
                                    <summary class="small font-weight-bold">Attempt timeline</summary>
                                    <div class="small mt-2">
                                        <div><strong>Started:</strong> {{ optional($attempt->started_at)->format('M d, Y h:i:s A') ?: '-' }}</div>
                                        @foreach($attempt->timeExtensions as $extension)
                                            <div class="text-primary">+{{ $extension->minutes }} min · {{ $extension->reason }} · {{ $extension->created_at->format('M d, h:i A') }}</div>
                                        @endforeach
                                        @foreach($attempt->events as $event)
                                            <div>{{ $event->event_at->format('M d, h:i:s A') }} · {{ str_replace('_',' ',$event->event_type) }}</div>
                                        @endforeach
                                        @if($attempt->ended_at)<div><strong>Ended:</strong> {{ $attempt->ended_at->format('M d, Y h:i:s A') }}</div>@endif
                                    </div>
                                </details>

                                @if((int)$attempt->status !== 3)
                                <form method="post" action="{{ route('admin.assessment_groups.attempts.void_retake',[$assessmentGroup,$attempt]) }}" onsubmit="return confirm('Void this attempt and authorize a retake on another set? The original attempt will remain in the audit trail.');">@csrf
                                    <label class="small">Controlled retake</label>
                                    <select name="retake_exam_id" class="form-control form-control-sm mb-1" required>
                                        <option value="">Select another set</option>
                                        @foreach($assessmentGroup->exams as $retakeExam)
                                            @if((int)$retakeExam->id !== (int)$attempt->exam_id)
                                                <option value="{{ $retakeExam->id }}">Set {{ $retakeExam->set_code }} — {{ $retakeExam->getStatus() }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                    <textarea name="reason" class="form-control form-control-sm mb-1" rows="2" required placeholder="Reason for void/retake"></textarea>
                                    <button class="btn btn-sm btn-outline-danger">Void & Authorize Retake</button>
                                </form>
                                @endif

                                @if($attempt->void_reason)
                                    <div class="small text-muted mt-2"><strong>Void reason:</strong> {{ $attempt->void_reason }}</div>
                                @endif
                            </div>
                        </details>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8">No attempts yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($attempts->hasPages())<div class="card-footer">{{ $attempts->links() }}</div>@endif
</div>
@stop

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkbox = document.getElementById('autoRefresh');
    const storageKey = 'rms_assessment_group_{{ $assessmentGroup->id }}_auto_refresh';
    checkbox.checked = localStorage.getItem(storageKey) === '1';

    checkbox.addEventListener('change', function () {
        localStorage.setItem(storageKey, checkbox.checked ? '1' : '0');
    });

    setInterval(function () {
        if (checkbox.checked && !document.querySelector('details[open]')) {
            window.location.reload();
        }
    }, 60000);
});
</script>
@stop
