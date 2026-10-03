@extends('adminlte::page')
@section('title','Skills Test Preview')
@section('content_header')
<div class="d-flex justify-content-between align-items-center">
    <div><h1 class="mb-0">{{ $skillTest->title }} — Preview</h1><small class="text-muted">Applicant-facing content preview and deployment dry-run</small></div>
    <a href="{{ route('admin.skills.edit',$skillTest) }}" class="btn btn-outline-secondary">Back</a>
</div>
@stop
@section('content')
<div class="row">
    <div class="col-md-6">
        <div class="card card-outline {{ $readiness['ready']?'card-success':'card-warning' }}">
            <div class="card-header"><strong>Assessment Readiness</strong></div>
            <div class="card-body">
                @if($readiness['ready'])<span class="badge badge-success">Task and rubric ready</span>
                @else<ul class="mb-0">@foreach($readiness['issues'] as $issue)<li>{{ $issue }}</li>@endforeach</ul>@endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card card-outline {{ $infrastructure['ready']?'card-success':'card-danger' }}">
            <div class="card-header"><strong>Infrastructure Dry Run</strong></div>
            <div class="card-body">
                <div>Queue: <strong>{{ $infrastructure['queue_connection'] }}</strong></div>
                <div>Scheduler: <strong>{{ optional($infrastructure['scheduler_heartbeat'])->diffForHumans() ?: 'No heartbeat' }}</strong></div>
                @foreach($infrastructure['issues'] as $issue)<div class="text-danger small">• {{ $issue }}</div>@endforeach
                @foreach($infrastructure['warnings'] as $warning)<div class="text-warning small">• {{ $warning }}</div>@endforeach
            </div>
        </div>
    </div>
</div>
<div class="card">
    <div class="card-header"><strong>Applicant Preview</strong><span class="float-right">{{ $skillTest->duration }} minutes</span></div>
    <div class="card-body">
        <h5>Task</h5><div style="white-space:pre-wrap">{{ $skillTest->instructions }}</div>
        @if($skillTest->expected_output)<hr><strong>Expected output</strong><div style="white-space:pre-wrap">{{ $skillTest->expected_output }}</div>@endif
    </div>
</div>
<div class="card">
    <div class="card-header"><strong>Evaluator Rubric Preview</strong></div>
    <div class="card-body table-responsive p-0">
        <table class="table mb-0"><thead><tr><th>Criterion</th><th>Description</th><th>Points</th></tr></thead>
        <tbody>@foreach($skillTest->rubricCriteria as $criterion)<tr><td>{{ $criterion->criterion }}</td><td>{{ $criterion->description }}</td><td>{{ $criterion->max_points }}</td></tr>@endforeach</tbody></table>
    </div>
</div>
@stop
