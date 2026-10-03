@extends('adminlte::page')
@section('title','Skills Test Results')
@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">{{ $skillTest->title }} — Live Results</h1>
        <small class="text-muted">{{ optional($skillTest->vacancy)->position_title }}</small>
    </div>
    <div class="mt-2 mt-md-0">
        <a href="{{ route('admin.skills.export',$skillTest) }}" class="btn btn-outline-success mr-2"><i class="fas fa-file-csv"></i> Export CSV</a>
        <a href="{{ route('admin.skills.edit',$skillTest) }}" class="btn btn-outline-secondary">Back to Skills Test</a>
    </div>
</div>
@stop

@section('content')
@if(session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner"><h3>{{ number_format((int)$dashboard->attempted) }}</h3><p>Attempted</p></div>
            <div class="icon"><i class="fas fa-users"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner"><h3>{{ number_format((int)$dashboard->taking_now) }}</h3><p>Taking Now</p></div>
            <div class="icon"><i class="fas fa-user-clock"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner"><h3>{{ number_format((int)$dashboard->submitted) }}</h3><p>Submitted</p></div>
            <div class="icon"><i class="fas fa-check-circle"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-light">
            <div class="inner">
                <h3>{{ number_format((int)$dashboard->evaluated) }}</h3>
                <p>Human Evaluated</p>
            </div>
            <div class="icon"><i class="fas fa-clipboard-check"></i></div>
        </div>
    </div>
</div>

@if((int)$dashboard->awaiting_timeout > 0)
<div class="alert alert-warning">
    <i class="fas fa-clock mr-1"></i>
    {{ number_format((int)$dashboard->awaiting_timeout) }} expired attempt(s) are waiting for the scheduled finalizer.
</div>
@endif

<div class="card card-outline card-secondary">
    <div class="card-header"><strong>Operational Health</strong></div>
    <div class="card-body py-2">
        <div class="row text-center">
            <div class="col-4"><strong>{{ $health['queue_jobs'] === null ? 'N/A' : number_format((int)$health['queue_jobs']) }}</strong><br><small class="text-muted">queued jobs</small></div>
            <div class="col-4"><strong>{{ $health['failed_jobs'] === null ? 'N/A' : number_format((int)$health['failed_jobs']) }}</strong><br><small class="text-muted">failed jobs</small></div>
            <div class="col-4"><strong>{{ number_format((int)$health['open_incidents']) }}</strong><br><small class="text-muted">open incidents</small></div>
        </div>
    </div>
</div>

@if($dashboard->mean_final_score !== null)
<div class="alert alert-light border">
    Mean finalized human score: <strong>{{ number_format((float)$dashboard->mean_final_score,2) }}/100</strong>.
    AI values shown below are proposals only; the rubric-level human evaluation remains authoritative.
</div>
@endif

<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
    <div class="small text-muted">Applicants appear as soon as they start. Submitted attempts remain available for rubric-level human evaluation.</div>
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
                    <th>Applicant</th>
                    <th>Status</th>
                    <th>Started / Submitted</th>
                    <th>Submission</th>
                    <th>AI Proposed</th>
                    <th>Human Final</th>
                    <th style="min-width:340px">Evaluation / Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($attempts as $attempt)
                @php
                    $latestAi = $attempt->aiEvaluations->sortByDesc('id')->first();
                    $latestSubmission = $attempt->submissions->sortByDesc('version')->first();
                    $humanByCriterion = $attempt->humanScores->keyBy('skill_test_rubric_criterion_id');
                @endphp
                <tr>
                    <td>
                        {{ optional($attempt->application)->application_code }}<br>
                        <small>{{ optional($attempt->application)->getFullname() }}</small>
                    </td>
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
                    <td>
                        {{ optional($attempt->started_at)->format('M d, Y h:i A') ?: '-' }}
                        @if($attempt->submitted_at)<br><small>Submitted {{ $attempt->submitted_at->format('M d, Y h:i A') }}</small>@endif
                    </td>
                    <td>
                        @if($latestSubmission)
                            <span class="badge badge-light">v{{ $latestSubmission->version }}</span>
                            @if($latestSubmission->inline_response)<div class="small text-success">Inline response</div>@endif
                            @if($latestSubmission->original_filename)<div class="small text-success">{{ $latestSubmission->original_filename }}</div>@endif
                        @else
                            <span class="text-muted">No saved output yet</span>
                        @endif
                    </td>
                    <td>
                        {{ $attempt->ai_proposed_score !== null ? number_format((float)$attempt->ai_proposed_score,2) : '-' }}
                        <div class="small text-muted">{{ optional($latestAi)->status ?? 'Not queued' }}</div>
                        @if($latestAi && $latestAi->criterion_scores)
                            <details class="mt-1"><summary class="small">AI rubric evidence</summary><pre class="small text-wrap">{{ json_encode($latestAi->criterion_scores, JSON_PRETTY_PRINT) }}</pre></details>
                        @endif
                    </td>
                    <td>
                        @if($attempt->final_score !== null)
                            <strong>{{ number_format((float)$attempt->final_score,2) }}/100</strong>
                            <div class="small text-muted">{{ optional($attempt->evaluated_at)->format('M d, Y h:i A') }}</div>
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        <details>
                            <summary class="btn btn-sm btn-outline-primary">Manage / Evaluate</summary>
                            <div class="border rounded p-2 mt-2">
                                @if((int)$attempt->status === 2)
                                <form method="post" action="{{ route('admin.skills.final_score',[$skillTest,$attempt]) }}" class="mb-3">@csrf
                                    <strong>Human Rubric Evaluation</strong>
                                    <div class="small text-muted mb-2">Enter criterion scores. RMS derives the final total; AI scores remain advisory.</div>

                                    @foreach($skillTest->rubricCriteria as $criterion)
                                        @php $existing=$humanByCriterion->get($criterion->id); @endphp
                                        <div class="border rounded p-2 mb-2">
                                            <div class="d-flex justify-content-between">
                                                <strong>{{ $criterion->criterion }}</strong>
                                                <span>/ {{ number_format((float)$criterion->max_points,2) }}</span>
                                            </div>
                                            @if($criterion->description)<div class="small text-muted mb-1">{{ $criterion->description }}</div>@endif
                                            <input type="number" step=".01" min="0" max="{{ $criterion->max_points }}"
                                                   name="scores[{{ $criterion->id }}]"
                                                   value="{{ old('scores.'.$criterion->id, optional($existing)->score) }}"
                                                   class="form-control form-control-sm mb-1" required>
                                            <input name="notes[{{ $criterion->id }}]"
                                                   value="{{ old('notes.'.$criterion->id, optional($existing)->notes) }}"
                                                   class="form-control form-control-sm"
                                                   placeholder="Evaluator note (optional)">
                                        </div>
                                    @endforeach

                                    <button class="btn btn-sm btn-success">Save Human Rubric Scores</button>
                                </form>
                                @endif

                                <form method="post" action="{{ route('admin.skills.incidents.store',$skillTest) }}" class="mb-3">@csrf
                                    <input type="hidden" name="skill_test_attempt_id" value="{{ $attempt->id }}">
                                    <strong>Incident</strong>
                                    <select name="type" class="form-control form-control-sm mt-1 mb-1" required>
                                        <option value="connectivity">Connectivity</option>
                                        <option value="device">Device</option>
                                        <option value="power">Power</option>
                                        <option value="proctoring">Proctoring</option>
                                        <option value="administrative">Administrative</option>
                                        <option value="other">Other</option>
                                    </select>
                                    <textarea name="notes" class="form-control form-control-sm mb-1" rows="2" required placeholder="Incident notes"></textarea>
                                    <button class="btn btn-sm btn-outline-info">Record Incident</button>
                                </form>

                                @if((int)$attempt->status !== 3 && $retakeTests->isNotEmpty())
                                <form method="post" action="{{ route('admin.skills.attempts.void_retake',[$skillTest,$attempt]) }}"
                                      onsubmit="return confirm('Void this skills attempt and authorize the selected retake task? The original record will remain in the audit trail.');">@csrf
                                    <strong>Controlled Retake</strong>
                                    <select name="retake_skill_test_id" class="form-control form-control-sm mt-1 mb-1" required>
                                        <option value="">Select published retake task</option>
                                        @foreach($retakeTests as $retake)
                                            <option value="{{ $retake->id }}">{{ $retake->title }} ({{ $retake->code }})</option>
                                        @endforeach
                                    </select>
                                    <textarea name="reason" class="form-control form-control-sm mb-1" rows="2" required placeholder="Reason for void/retake"></textarea>
                                    <button class="btn btn-sm btn-outline-danger">Void & Authorize Retake</button>
                                </form>
                                @endif

                                @if($attempt->void_reason)
                                    <div class="small text-muted"><strong>Void reason:</strong> {{ $attempt->void_reason }}</div>
                                @endif
                            </div>
                        </details>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">No skills-test attempts yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($attempts->hasPages())
        <div class="card-footer">{{ $attempts->links('pagination::bootstrap-4') }}</div>
    @endif
</div>

@if($incidents->isNotEmpty())
<div class="card">
    <div class="card-header"><strong>Open Incidents</strong></div>
    <div class="card-body">
        @foreach($incidents as $incident)
            <div class="border-bottom pb-2 mb-2">
                <strong>{{ ucfirst($incident->type) }}</strong>
                <div class="small">{{ $incident->notes }}</div>
                <form method="post" action="{{ route('admin.skills.incidents.resolve',[$skillTest,$incident]) }}" class="mt-1">@csrf @method('put')
                    <button class="btn btn-xs btn-outline-success">Mark Resolved</button>
                </form>
            </div>
        @endforeach
    </div>
</div>
@endif
@stop

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkbox = document.getElementById('autoRefresh');
    const key = 'rms_skill_test_{{ $skillTest->id }}_auto_refresh';
    checkbox.checked = localStorage.getItem(key) === '1';

    checkbox.addEventListener('change', function () {
        localStorage.setItem(key, checkbox.checked ? '1' : '0');
    });

    setInterval(function () {
        if (checkbox.checked && !document.querySelector('details[open]')) {
            window.location.reload();
        }
    }, 60000);
});
</script>
@stop
