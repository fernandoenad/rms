@extends('adminlte::page')
@section('title','Assessment Center')
@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">Assessment Center</h1>
        <small class="text-muted">Unified operations for written assessments and skills tests</small>
    </div>
    <div class="mt-2 mt-md-0">
        <a href="{{ route('admin.assessment_groups.index') }}" class="btn btn-outline-primary mr-2">Written Assessments</a>
        <a href="{{ route('admin.skills.index') }}" class="btn btn-outline-info">Skills Tests</a>
    </div>
</div>
@stop

@section('content')
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
    <div class="card-header"><strong>Operations Health</strong></div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col-6 col-md-2"><strong>{{ number_format($written['awaiting_timeout']) }}</strong><br><small class="text-muted">written timeout backlog</small></div>
            <div class="col-6 col-md-2"><strong>{{ number_format($skills['awaiting_timeout']) }}</strong><br><small class="text-muted">skills timeout backlog</small></div>
            <div class="col-6 col-md-2"><strong>{{ number_format($written['pending_item_review']) }}</strong><br><small class="text-muted">written items pending review</small></div>
            <div class="col-6 col-md-2"><strong>{{ number_format($skills['pending_human']) }}</strong><br><small class="text-muted">skills pending human score</small></div>
            <div class="col-6 col-md-2"><strong>{{ $queue['failed']===null ? 'N/A' : number_format($queue['failed']) }}</strong><br><small class="text-muted">failed jobs</small></div>
            <div class="col-6 col-md-2"><strong>{{ $heartbeat ? $heartbeat->diffForHumans() : 'Never' }}</strong><br><small class="text-muted">scheduler heartbeat</small></div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><strong>Written Assessment Groups</strong></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Assessment</th><th>Sets</th><th>State</th><th></th></tr></thead>
                    <tbody>
                    @forelse($groups as $group)
                    <tr>
                        <td>{{ $group->title }}<br><small class="text-muted">{{ optional($group->vacancy)->position_title }}</small></td>
                        <td>{{ $group->exams->count() }}</td>
                        <td>
                            @if($group->is_paused)<span class="badge badge-warning">Paused</span>
                            @elseif($group->status)<span class="badge badge-success">Active</span>
                            @else<span class="badge badge-secondary">Inactive</span>@endif
                        </td>
                        <td><a href="{{ route('admin.assessment_groups.edit',$group) }}" class="btn btn-xs btn-outline-primary">Open</a></td>
                    </tr>
                    @empty<tr><td colspan="4">No assessment groups.</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Standalone Written Tests</strong></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Test</th><th>State</th><th></th></tr></thead>
                    <tbody>
                    @forelse($standaloneExams as $exam)
                    <tr>
                        <td>{{ $exam->title }}<br><small class="text-muted">{{ optional($exam->vacancy)->position_title }}</small></td>
                        <td>
                            @if($exam->is_paused)<span class="badge badge-warning">Paused</span>
                            @elseif($exam->status)<span class="badge badge-success">Published</span>
                            @else<span class="badge badge-secondary">Draft</span>@endif
                        </td>
                        <td><a href="{{ route('admin.assessments.edit',$exam) }}" class="btn btn-xs btn-outline-primary">Open</a></td>
                    </tr>
                    @empty<tr><td colspan="3">No standalone written tests.</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><strong>Skills Tests</strong></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Test</th><th>State</th><th></th></tr></thead>
                    <tbody>
                    @forelse($skillTests as $test)
                    <tr>
                        <td>{{ $test->title }}<br><small class="text-muted">{{ optional($test->vacancy)->position_title }}</small></td>
                        <td>
                            @if($test->is_paused)<span class="badge badge-warning">Paused</span>
                            @elseif($test->status)<span class="badge badge-success">Published</span>
                            @else<span class="badge badge-secondary">Draft</span>@endif
                        </td>
                        <td><a href="{{ route('admin.skills.edit',$test) }}" class="btn btn-xs btn-outline-info">Open</a></td>
                    </tr>
                    @empty<tr><td colspan="3">No skills tests.</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="alert alert-light border">
            <strong>AI throughput:</strong>
            {{ $queue['assessment_ai']===null ? 'Queue table unavailable.' : number_format($queue['assessment_ai']).' assessment-AI job(s) waiting.' }}
            Use dedicated workers for the <code>assessment-ai</code> queue so AI work cannot block email and normal application jobs.
        </div>
    </div>
</div>
@stop
