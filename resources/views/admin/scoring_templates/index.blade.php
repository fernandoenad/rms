@extends('adminlte::page')

@section('title','Scoring Templates')

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">Scoring Templates</h1>
        <small class="text-muted">Define the 100-point comparative assessment structure assigned to vacancies.</small>
    </div>
    <a href="{{ route('admin.scoring_templates.create') }}" class="btn btn-primary mt-2 mt-md-0">
        <i class="fas fa-plus mr-1"></i> New Scoring Template
    </a>
</div>
@stop

@section('content')
@if(session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif

<div class="card">
    <div class="card-header">
        <form method="get" class="form-inline">
            <input type="text" name="q" value="{{ $search }}" class="form-control form-control-sm mr-2 mb-2" placeholder="Search template">
            <select name="status" class="form-control form-control-sm mr-2 mb-2">
                <option value="all" {{ $status==='all'?'selected':'' }}>All statuses</option>
                <option value="active" {{ $status==='active'?'selected':'' }}>Active</option>
                <option value="inactive" {{ $status==='inactive'?'selected':'' }}>Inactive / Draft</option>
            </select>
            <button class="btn btn-sm btn-secondary mb-2"><i class="fas fa-filter mr-1"></i> Filter</button>
            <a href="{{ route('admin.scoring_templates.index') }}" class="btn btn-sm btn-outline-secondary ml-1 mb-2">Clear</a>
        </form>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover mb-0">
            <thead>
            <tr>
                <th>Template</th>
                <th>Version</th>
                <th>Criteria</th>
                <th>Total</th>
                <th>Vacancies</th>
                <th>Assessments</th>
                <th>Status</th>
                <th style="min-width:220px">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($templates as $template)
                <tr>
                    <td>
                        <strong>{{ $template->type }}</strong>
                        @if($template->description)<br><small class="text-muted">{{ IlluminateSupportStr::limit($template->description,100) }}</small>@endif
                    </td>
                    <td>v{{ $template->version }}</td>
                    <td>
                        {{ $template->criteria_count }}
                        @if($template->criteria->isNotEmpty())
                            <details><summary class="small text-muted">View criteria</summary>
                                <ul class="small mb-0 pl-3">
                                    @foreach($template->criteria as $criterion)
                                        <li>{{ $criterion->label }} — {{ number_format((float)$criterion->max_points,3) }} pts <span class="badge badge-light">{{ ucfirst($criterion->source_type) }}</span></li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </td>
                    <td>
                        <strong class="{{ abs($template->total_points-100) < .001 ? 'text-success' : 'text-danger' }}">
                            {{ number_format($template->total_points,3) }}
                        </strong>
                    </td>
                    <td>{{ number_format($template->vacancy_count) }}</td>
                    <td>{{ number_format($template->assessment_count) }}</td>
                    <td>
                        @if((int)$template->status===1)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-secondary">Draft / Inactive</span>
                        @endif
                    </td>
                    <td class="text-nowrap">
                        <a href="{{ route('admin.scoring_templates.edit',$template) }}" class="btn btn-sm btn-primary">
                            <i class="fas fa-edit mr-1"></i> Manage
                        </a>
                        <form method="post" action="{{ route('admin.scoring_templates.duplicate',$template) }}" class="d-inline">
                            @csrf
                            <button class="btn btn-sm btn-outline-secondary" title="Create new editable version">
                                <i class="fas fa-copy"></i>
                            </button>
                        </form>
                        <form method="post" action="{{ route('admin.scoring_templates.archive',$template) }}" class="d-inline"
                              onsubmit="return confirm('Archive this scoring template? Historical records will remain intact.');">
                            @csrf
                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-archive"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No scoring templates found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $templates->links('pagination::bootstrap-4') }}
@stop
