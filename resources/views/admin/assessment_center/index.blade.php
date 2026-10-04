@extends('adminlte::page')
@section('title','Assessment Center')
@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">Assessment Center</h1>
        <small class="text-muted">Unified operations for written assessments and skills tests</small>
    </div>
    <div class="mt-2 mt-md-0">
        <a href="{{ route('admin.assessment_groups.index') }}" class="btn btn-outline-primary mr-2">Manage Written</a>
        <a href="{{ route('admin.skill_groups.index') }}" class="btn btn-outline-info mr-2">Manage Skills</a>
        <a href="{{ route('admin.assessment_bank.index') }}" class="btn btn-outline-secondary mr-2">Content Bank</a>
        <a href="{{ route('admin.assessment_snapshots.index') }}" class="btn btn-outline-secondary mr-2">Snapshots</a>
        @if(auth()->user() && auth()->user()->role && (int)auth()->user()->role->level===1)
            <a href="{{ route('admin.assessment_permissions.index') }}" class="btn btn-outline-dark">Access Roles</a>
        @endif
    </div>
</div>
@stop

@section('content')
@if(session('status'))
<div class="alert alert-info py-2">{{ session('status') }}</div>
@endif

@foreach($alerts as $alert)
<div class="alert alert-{{ $alert['level'] }} py-2">
    <i class="fas fa-exclamation-triangle mr-1"></i>{{ $alert['message'] }}
</div>
@endforeach

<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner"><h3>{{ number_format($written['taking_now']) }}</h3><p>Written Taking Now</p></div>
            <div class="icon"><i class="fas fa-pen"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner"><h3>{{ number_format($skills['taking_now']) }}</h3><p>Skills Taking Now</p></div>
            <div class="icon"><i class="fas fa-tools"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-light">
            <div class="inner"><h3>{{ $queue['assessment_ai']===null ? 'N/A' : number_format($queue['assessment_ai']) }}</h3><p>AI Queue</p></div>
            <div class="icon"><i class="fas fa-robot"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box {{ $schedulerHealthy ? 'bg-success' : 'bg-danger' }}">
            <div class="inner"><h3>{{ $schedulerHealthy ? 'OK' : 'CHECK' }}</h3><p>Scheduler</p></div>
            <div class="icon"><i class="fas fa-heartbeat"></i></div>
        </div>
    </div>
</div>

<div class="card card-outline card-secondary">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
        <strong>Operations Health</strong>
        @if(auth()->user() && auth()->user()->role && (int)auth()->user()->role->level===1 && ($queue['failed'] ?? 0) > 0)
        <div class="mt-2 mt-md-0">
            <form method="post" action="{{ route('admin.assessment_center.failed_jobs.retry') }}" class="d-inline"
                  onsubmit="return confirm('Retry all failed queue jobs? Jobs will be returned to their original queues.');">
                @csrf
                <button class="btn btn-sm btn-outline-primary mr-1">
                    <i class="fas fa-redo mr-1"></i> Retry Failed Jobs
                </button>
            </form>
            <form method="post" action="{{ route('admin.assessment_center.failed_jobs.clear') }}" class="d-inline"
                  onsubmit="return confirm('Permanently clear all failed-job records? This cannot be undone.');">
                @csrf
                <button class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-trash mr-1"></i> Clear Failed Jobs
                </button>
            </form>
        </div>
        @endif
    </div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col-6 col-md-2"><strong>{{ number_format($written['awaiting_timeout']) }}</strong><br><small class="text-muted">written timeout backlog</small></div>
            <div class="col-6 col-md-2"><strong>{{ number_format($skills['awaiting_timeout']) }}</strong><br><small class="text-muted">skills timeout backlog</small></div>
            <div class="col-6 col-md-2"><strong>{{ number_format($written['pending_item_review']) }}</strong><br><small class="text-muted">written items pending review</small></div>
            <div class="col-6 col-md-2"><strong>{{ number_format($skills['pending_human']) }}</strong><br><small class="text-muted">skills pending human score</small></div>
            <div class="col-6 col-md-2"><strong>{{ $queue['failed']===null ? 'N/A' : number_format($queue['failed']) }}</strong><br><small class="text-muted">failed jobs</small></div>
            <div class="col-6 col-md-2"><strong>{{ $heartbeat ? $heartbeat->diffForHumans() : 'Never' }}</strong><br><small class="text-muted">scheduler heartbeat</small></div>
        </div>
        @if(auth()->user() && auth()->user()->role && (int)auth()->user()->role->level===1 && ($queue['failed'] ?? 0) > 0)
            <div class="alert alert-warning py-2 mt-3 mb-0 small">
                Retry returns failed jobs to their original queue. Clear only removes failed-job records; it does not cancel queued or running jobs.
            </div>
        @endif
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card card-outline card-info">
            <div class="card-header"><strong>Autosave Performance · Last 15 Minutes</strong></div>
            <div class="card-body">
                <div class="row text-center">
                    @php $wp=$performance['written_answer_save']; $sp=$performance['skill_inline_save']; @endphp
                    <div class="col-6">
                        <strong>Written</strong><br>
                        Avg: {{ $wp['avg_ms']===null ? 'N/A' : number_format($wp['avg_ms']).' ms' }}<br>
                        P95: {{ $wp['p95_ms']===null ? 'N/A' : number_format($wp['p95_ms']).' ms' }}<br>
                        <small class="text-muted">{{ $wp['samples'] }} sampled saves</small>
                    </div>
                    <div class="col-6">
                        <strong>Skills</strong><br>
                        Avg: {{ $sp['avg_ms']===null ? 'N/A' : number_format($sp['avg_ms']).' ms' }}<br>
                        P95: {{ $sp['p95_ms']===null ? 'N/A' : number_format($sp['p95_ms']).' ms' }}<br>
                        <small class="text-muted">{{ $sp['samples'] }} sampled saves</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card card-outline card-secondary">
            <div class="card-header"><strong>Content Bank Health</strong></div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4"><strong>{{ number_format($bank['active']) }}</strong><br><small class="text-muted">active</small></div>
                    <div class="col-4"><strong>{{ number_format($bank['retired']) }}</strong><br><small class="text-muted">retired</small></div>
                    <div class="col-4"><strong>{{ number_format($bank['high_exposure']) }}</strong><br><small class="text-muted">used ≥4 times</small></div>
                </div>
                @if($bank['high_exposure']>0)
                    <div class="alert alert-warning py-2 mt-3 mb-0">Some assessment content has high exposure. Review it before another recruitment cycle.</div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card card-outline card-primary">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <strong>Assessments</strong>
            <div class="small text-muted">Written and Skills assessments now follow the same group → set → attempt workflow.</div>
        </div>
        <div class="btn-group btn-group-sm mt-2 mt-md-0" role="group" aria-label="Assessment filters">
            <button type="button" class="btn btn-primary assessment-filter active" data-filter="all">All</button>
            <button type="button" class="btn btn-outline-primary assessment-filter" data-filter="written">Written</button>
            <button type="button" class="btn btn-outline-primary assessment-filter" data-filter="skills">Skills</button>
            <button type="button" class="btn btn-outline-warning assessment-filter" data-filter="attention">Needs Attention</button>
            <button type="button" class="btn btn-outline-success assessment-filter" data-filter="open">Open Now</button>
        </div>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover mb-0" id="assessmentOverviewTable">
            <thead>
                <tr>
                    <th>Assessment</th>
                    <th>Type</th>
                    <th>Position</th>
                    <th>Sets</th>
                    <th>Current / Next</th>
                    <th>Activity</th>
                    <th>Readiness</th>
                    <th>State</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($assessmentRows as $row)
                <tr data-type="{{ $row['type'] }}"
                    data-attention="{{ $row['ready'] ? '0' : '1' }}"
                    data-open="{{ $row['current_set'] ? '1' : '0' }}">
                    <td>
                        <strong>{{ $row['title'] }}</strong>
                        <div class="small text-muted">Equivalent-set assessment</div>
                    </td>
                    <td>
                        <span class="badge {{ $row['type']==='written' ? 'badge-primary' : 'badge-info' }}">
                            {{ $row['type_label'] }}
                        </span>
                    </td>
                    <td>{{ $row['position'] ?: '—' }}</td>
                    <td>{{ $row['sets'] }}</td>
                    <td>
                        @if($row['current_set'])
                            <span class="badge badge-success">Set {{ $row['current_set'] }} open now</span>
                        @elseif($row['next_set'])
                            <strong>Set {{ $row['next_set'] }}</strong>
                            <div class="small text-muted">
                                {{ optional($row['next_at'])->format('M d, Y h:i A') }}
                            </div>
                        @else
                            <span class="text-muted">No scheduled set</span>
                        @endif
                    </td>
                    <td>
                        @if($row['active_attempts'] > 0)
                            <strong>{{ number_format($row['active_attempts']) }}</strong> taking now
                        @else
                            <span class="text-muted">0 taking now</span>
                        @endif
                        <div class="small text-muted">{{ number_format($row['attempts']) }} total attempt(s)</div>
                    </td>
                    <td>
                        @if($row['ready'])
                            <span class="badge badge-success">Ready</span>
                        @else
                            <span class="badge badge-warning">Needs attention</span>
                        @endif
                        <div class="small text-muted mt-1">{{ $row['readiness_detail'] }}</div>
                    </td>
                    <td><span class="badge badge-{{ $row['state_class'] }}">{{ $row['state'] }}</span></td>
                    <td><a href="{{ $row['manage_url'] }}" class="btn btn-sm btn-outline-primary">Manage</a></td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-muted text-center py-4">No grouped assessments yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card card-outline card-secondary collapsed-card">
    <div class="card-header">
        <h3 class="card-title">
            <strong>Standalone / Legacy Assessments</strong>
            <span class="badge badge-light border ml-1">{{ $standaloneExams->count() + $standaloneSkillTests->count() }}</span>
        </h3>
        <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Show standalone assessments">
                <i class="fas fa-plus"></i>
            </button>
        </div>
    </div>
    <div class="card-body p-0" style="display:none;">
        <div class="alert alert-light border-0 border-bottom rounded-0 mb-0 small text-muted">
            Grouped assessments are recommended for scheduled equivalent sets. Keep standalone assessments for exceptional or legacy workflows.
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead><tr><th>Assessment</th><th>Type</th><th>Position</th><th>State</th><th></th></tr></thead>
                <tbody>
                    @foreach($standaloneExams as $exam)
                    <tr>
                        <td>{{ $exam->title }}</td>
                        <td><span class="badge badge-primary">Written</span></td>
                        <td>{{ optional($exam->vacancy)->position_title }}</td>
                        <td>
                            @if($exam->is_paused)<span class="badge badge-warning">Paused</span>
                            @elseif($exam->status)<span class="badge badge-success">Published</span>
                            @else<span class="badge badge-secondary">Draft</span>@endif
                        </td>
                        <td><a href="{{ route('admin.assessments.edit',$exam) }}" class="btn btn-xs btn-outline-primary">Manage</a></td>
                    </tr>
                    @endforeach
                    @foreach($standaloneSkillTests as $test)
                    <tr>
                        <td>{{ $test->title }}</td>
                        <td><span class="badge badge-info">Skills</span></td>
                        <td>{{ optional($test->vacancy)->position_title }}</td>
                        <td>
                            @if($test->is_paused)<span class="badge badge-warning">Paused</span>
                            @elseif($test->status)<span class="badge badge-success">Published</span>
                            @else<span class="badge badge-secondary">Draft</span>@endif
                        </td>
                        <td><a href="{{ route('admin.skills.edit',$test) }}" class="btn btn-xs btn-outline-info">Manage</a></td>
                    </tr>
                    @endforeach
                    @if($standaloneExams->isEmpty() && $standaloneSkillTests->isEmpty())
                    <tr><td colspan="5" class="text-muted text-center py-3">No standalone assessments.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="alert alert-light border">
    <strong>AI throughput:</strong>
    {{ $queue['assessment_ai']===null ? 'Queue table unavailable.' : number_format($queue['assessment_ai']).' assessment-AI job(s) waiting.' }}
    Use dedicated workers for the <code>assessment-ai</code> queue so AI work cannot block email and normal application jobs.
</div>

@section('js')
<script>
$(function () {
    $('.assessment-filter').on('click', function () {
        var filter = $(this).data('filter');

        $('.assessment-filter').removeClass('active btn-primary btn-warning btn-success')
            .addClass(function () {
                return $(this).data('filter') === 'attention'
                    ? 'btn-outline-warning'
                    : ($(this).data('filter') === 'open' ? 'btn-outline-success' : 'btn-outline-primary');
            });

        $(this).removeClass('btn-outline-primary btn-outline-warning btn-outline-success').addClass(
            filter === 'attention' ? 'active btn-warning'
                : (filter === 'open' ? 'active btn-success' : 'active btn-primary')
        );

        $('#assessmentOverviewTable tbody tr[data-type]').each(function () {
            var row = $(this);
            var show = filter === 'all'
                || row.data('type') === filter
                || (filter === 'attention' && String(row.data('attention')) === '1')
                || (filter === 'open' && String(row.data('open')) === '1');

            row.toggle(show);
        });
    });
});
</script>
@stop

@stop
