@extends('adminlte::page')
@section('title','Edit Assessment Group')
@section('content_header')
<div class="d-flex justify-content-between align-items-center">
    <div>
        <h1 class="mb-0">Edit Assessment Group</h1>
        <small class="text-muted">Equivalent written-test sets</small>
    </div>
    <div>
        <a href="{{ route('admin.assessment_groups.results',$assessmentGroup) }}" class="btn btn-outline-secondary mr-2"><i class="fas fa-chart-bar"></i> Combined Results</a>
        <a href="{{ route('admin.assessments.create', ['assessment_group_id' => $assessmentGroup->id]) }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Written Set
        </a>
    </div>
</div>
@stop

@section('content')
@if(session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<form method="post" action="{{ route('admin.assessment_groups.update',$assessmentGroup) }}">@csrf @method('put')
<div class="card">
    <div class="card-body">
        <div class="form-group">
            <label>Position</label>
            <select name="vacancy_id" class="form-control" required {{ $assessmentGroup->exams->count() ? 'disabled' : '' }}>
                @foreach($vacancies as $v)
                    <option value="{{ $v->id }}" {{ old('vacancy_id',$assessmentGroup->vacancy_id)==$v->id?'selected':'' }}>
                        {{ $v->position_title }} ({{ $v->cycle }})
                    </option>
                @endforeach
            </select>
            @if($assessmentGroup->exams->count())
                <input type="hidden" name="vacancy_id" value="{{ $assessmentGroup->vacancy_id }}">
                <small class="text-muted">Position is locked after a set has been added.</small>
            @endif
        </div>

        <div class="form-row">
            <div class="form-group col-md-8">
                <label>Assessment title</label>
                <input name="title" value="{{ old('title',$assessmentGroup->title) }}" class="form-control" required>
            </div>
            <div class="form-group col-md-4">
                <label>Group code</label>
                <input name="code" value="{{ old('code',$assessmentGroup->code) }}" class="form-control">
            </div>
        </div>

        <div class="form-group">
            <label>Status</label>
            <select name="status" class="form-control">
                <option value="1" {{ old('status',(string)(int)$assessmentGroup->status)==='1'?'selected':'' }}>Active</option>
                <option value="0" {{ old('status',(string)(int)$assessmentGroup->status)==='0'?'selected':'' }}>Inactive</option>
            </select>
        </div>
    </div>
    <div class="card-footer"><button class="btn btn-primary">Save Group</button></div>
</div>
</form>

<div class="card">
    <div class="card-header"><strong>Equivalent Sets</strong></div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Set</th><th>Exam</th><th>Schedule</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($assessmentGroup->exams as $exam)
                <tr>
                    <td><span class="badge badge-primary">{{ $exam->set_code ?: 'Unlabelled' }}</span></td>
                    <td>{{ $exam->title }}</td>
                    <td>
                        {{ optional($exam->start_date)->format('M d, Y h:i A') }}
                        @if($exam->end_date)<br><small>to {{ $exam->end_date->format('M d, Y h:i A') }}</small>@endif
                    </td>
                    <td>{{ $exam->getStatus() }}</td>
                    <td><a href="{{ route('admin.assessments.edit',$exam) }}" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="5">No sets yet. Add Set A, Set B, and so on.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@stop
