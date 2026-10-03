@extends('adminlte::page')
@section('title','Skills Test Groups')
@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">Skills Test Groups</h1>
        <small class="text-muted">Equivalent skills-task sets for the same position</small>
    </div>
    <div>
        <a href="{{ route('admin.skills.index') }}" class="btn btn-outline-secondary mr-2">All Skills Tests</a>
        <a href="{{ route('admin.skill_groups.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> New Skills Test Group</a>
    </div>
</div>
@stop
@section('content')
@if(session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif
<div class="card">
<div class="card-body table-responsive p-0">
<table class="table table-hover mb-0">
<thead><tr><th>Group</th><th>Position</th><th>Sets</th><th>Locked Applicants</th><th>Release</th><th>Status</th><th></th></tr></thead>
<tbody>
@forelse($groups as $group)
<tr>
<td><strong>{{ $group->title }}</strong><br><small class="text-muted">{{ $group->code }}</small></td>
<td>{{ optional($group->vacancy)->position_title }}</td>
<td>{{ $group->skill_tests_count }} / {{ $group->expected_sets }}</td>
<td>{{ $group->attempt_locks_count }}</td>
<td>{{ str_replace('_',' ',$group->score_release_policy) }}</td>
<td>
@if($group->archived_at)<span class="badge badge-secondary">Archived</span>
@elseif($group->is_paused)<span class="badge badge-warning">Paused</span>
@elseif($group->status)<span class="badge badge-success">Active</span>
@else<span class="badge badge-secondary">Inactive</span>@endif
</td>
<td><a href="{{ route('admin.skill_groups.edit',$group) }}" class="btn btn-sm btn-info">Manage Sets</a></td>
</tr>
@empty<tr><td colspan="7">No Skills Test groups yet.</td></tr>@endforelse
</tbody>
</table>
</div>
</div>
@stop
