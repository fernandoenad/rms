@extends('adminlte::page')
@section('title','Assessment Center')
@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <h1>Assessment Center</h1>
    <div>
        <a href="{{ route('admin.assessment_groups.index') }}" class="btn btn-outline-secondary mr-2"><i class="fas fa-layer-group"></i> Assessment Groups</a>
        <a href="{{ route('admin.skills.index') }}" class="btn btn-outline-primary mr-2"><i class="fas fa-tools"></i> Skills Tests</a>
        <a href="{{ route('admin.assessments.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> New Written Exam</a>
    </div>
</div>
@stop
@section('content')
@if(session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif
<div class="card"><div class="card-body table-responsive p-0">
<table class="table table-hover">
<thead><tr><th>Exam</th><th>Group / Set</th><th>Position</th><th>Schedule</th><th>Access</th><th>Shuffle</th><th>Items</th><th>Attempts</th><th>Status</th><th></th></tr></thead>
<tbody>
@forelse($exams as $exam)
<tr>
<td><strong>{{ $exam->title }}</strong><div class="small text-muted">{{ $exam->code }}</div></td>
<td>
@if($exam->assessmentGroup)
<strong>{{ $exam->assessmentGroup->title }}</strong><br>
<span class="badge badge-primary">Set {{ $exam->set_code }}</span>
@else
<span class="text-muted">Standalone</span>
@endif
</td>
<td>{{ optional($exam->vacancy)->position_title }}</td>
<td>{{ optional($exam->start_date)->format('M d, Y h:i A') }}<br><small>to {{ optional($exam->end_date)->format('M d, Y h:i A') }} · {{ $exam->duration }} min</small></td>
<td>{{ $exam->access_mode === 'all_taken_in' ? 'All taken-in' : 'Selected only' }}</td>
<td>Q: {{ $exam->shuffle_items ? 'Yes':'No' }}<br>Options: {{ $exam->shuffle_options ? 'Yes':'No' }}</td>
<td>{{ $exam->written_exams_count }}</td>
<td>{{ $exam->attempts_count }}</td>
<td>{{ $exam->getStatus() }}</td>
<td class="text-nowrap">
<a href="{{ route('admin.assessments.edit',$exam) }}" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
<a href="{{ route('admin.assessments.items.index',$exam) }}" class="btn btn-sm btn-info"><i class="fas fa-list"></i></a>
<a href="{{ route('admin.assessments.results',$exam) }}" class="btn btn-sm btn-secondary"><i class="fas fa-chart-bar"></i></a>
<form method="post" action="{{ route('admin.assessments.duplicate',$exam) }}" class="d-inline">@csrf
<button class="btn btn-sm btn-outline-primary" title="Duplicate as new set"><i class="fas fa-copy"></i></button></form>
</td>
</tr>
@empty<tr><td colspan="10">No written exams yet.</td></tr>@endforelse
</tbody>
</table></div></div>
@stop
