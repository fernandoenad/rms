@extends('adminlte::page')
@section('title','Assessment Groups')
@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">Assessment Groups</h1>
        <small class="text-muted">Equivalent written-test sets where each applicant may take only one set.</small>
    </div>
    <div>
        <a href="{{ route('admin.assessments.index') }}" class="btn btn-outline-secondary mr-2">Written Exams</a>
        <a href="{{ route('admin.assessment_groups.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> New Group</a>
    </div>
</div>
@stop

@section('content')
@if(session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif

<div class="alert alert-light border">
    Use one group for all equivalent sets of the same written assessment, for example Set A on Day 1 through Set E on Day 5.
    Once an applicant starts any set in a group, all sibling sets are locked for that applicant.
</div>

<div class="card">
    <div class="card-body table-responsive p-0">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Assessment Group</th>
                    <th>Position</th>
                    <th>Sets</th>
                    <th>Applicants Locked</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($groups as $group)
                <tr>
                    <td>
                        <strong>{{ $group->title }}</strong>
                        <div class="small text-muted">{{ $group->code ?: 'No group code' }}</div>
                    </td>
                    <td>{{ optional($group->vacancy)->position_title }}</td>
                    <td>{{ $group->exams_count }}</td>
                    <td>{{ $group->attempt_locks_count }}</td>
                    <td>{{ $group->status ? 'Active' : 'Inactive' }}</td>
                    <td>
                        <a href="{{ route('admin.assessment_groups.edit',$group) }}" class="btn btn-sm btn-warning">
                            <i class="fas fa-edit"></i>
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">No assessment groups yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@stop
