@extends('adminlte::page')
@section('title','Assessment Snapshots')
@section('content_header')
<div class="d-flex justify-content-between align-items-center">
    <div><h1 class="mb-0">Assessment Snapshots</h1><small class="text-muted">Recovery checkpoints created at publication, score release and archive</small></div>
    <a href="{{ route('admin.assessment_center.index') }}" class="btn btn-outline-secondary">Assessment Center</a>
</div>
@stop
@section('content')
@include('admin.assessment_center._nav')

<div class="card">
<div class="card-body table-responsive p-0">
<table class="table table-hover mb-0">
<thead><tr><th>When</th><th>Type</th><th>Event</th><th>Reference</th><th></th></tr></thead>
<tbody>
@forelse($snapshots as $snapshot)
<tr>
<td>{{ $snapshot->created_at->format('M d, Y h:i A') }}</td>
<td>{{ str_replace('_',' ',$snapshot->target_type) }}</td>
<td>{{ str_replace('_',' ',$snapshot->event) }}</td>
<td>
@if($snapshot->assessment_group_id)Group #{{ $snapshot->assessment_group_id }} @endif
@if($snapshot->exam_id)Exam #{{ $snapshot->exam_id }} @endif
@if($snapshot->skill_test_id)Skills #{{ $snapshot->skill_test_id }} @endif
</td>
<td><a href="{{ route('admin.assessment_snapshots.download',$snapshot) }}" class="btn btn-xs btn-outline-primary">Download JSON</a></td>
</tr>
@empty<tr><td colspan="5">No snapshots yet.</td></tr>@endforelse
</tbody></table>
</div>
@if($snapshots->hasPages())<div class="card-footer">{{ $snapshots->links('pagination::bootstrap-4') }}</div>@endif
</div>
@stop
