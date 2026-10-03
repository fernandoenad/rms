@extends('adminlte::page')
@section('title','Skills Tests')
@section('content_header')
<div class="d-flex justify-content-between align-items-center">
    <h1>Skills Tests</h1>
    <a href="{{ route('admin.skills.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> New Skills Test</a>
</div>
@stop
@section('content')
@if(session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif
<div class="card"><div class="card-body table-responsive p-0">
<table class="table table-hover">
<thead><tr><th>Title</th><th>Position</th><th>Schedule</th><th>Access</th><th>Attempts</th><th>Readiness</th><th>Status</th><th></th></tr></thead>
<tbody>
@forelse($tests as $test)
<tr>
<td>{{ $test->title }}<div class="small text-muted">{{ $test->code }}</div></td>
<td>{{ optional($test->vacancy)->position_title }}</td>
<td>{{ $test->start_date }}<br><span class="small">to {{ $test->end_date }}</span></td>
<td>{{ $test->access_mode === 'all_taken_in' ? 'All taken-in applicants' : 'Selected applicants' }}</td>
<td>{{ $test->attempts_count }}</td>
@php $ready=$readiness[$test->id] ?? ['ready'=>false,'issues'=>[]]; @endphp
<td>
@if($ready['ready'])
<span class="badge badge-success">Ready</span>
@else
<span class="badge badge-warning">Needs review</span>
<details class="small mt-1"><summary>{{ count($ready['issues']) }} issue(s)</summary><ul class="pl-3 mb-0">@foreach(array_slice($ready['issues'],0,6) as $issue)<li>{{ $issue }}</li>@endforeach</ul></details>
@endif
</td>
<td>{{ $test->status ? 'Published' : 'Draft' }}</td>
<td class="text-nowrap">
<a class="btn btn-sm btn-warning" href="{{ route('admin.skills.edit',$test) }}"><i class="fas fa-edit"></i></a>
<a class="btn btn-sm btn-info" href="{{ route('admin.skills.results',$test) }}"><i class="fas fa-chart-bar"></i></a>
</td>
</tr>
@empty<tr><td colspan="8">No skills tests yet.</td></tr>@endforelse
</tbody></table></div></div>
@stop
