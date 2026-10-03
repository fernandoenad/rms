@extends('adminlte::page')
@section('title','New Skills Test')
@section('content_header')<h1>New Skills Test</h1>@stop

@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<form method="post" action="{{ route('admin.skills.store') }}">@csrf
<div class="card">
    <div class="card-header"><strong>Task & Schedule</strong></div>
    <div class="card-body">
        <div class="form-group">
            <label>Position</label>
            <select name="vacancy_id" class="form-control" required>
                <option value="">Select</option>
                @foreach($vacancies as $v)
                    <option value="{{ $v->id }}" {{ old('vacancy_id')==$v->id?'selected':'' }}>
                        {{ $v->position_title }} ({{ $v->cycle }})
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-row">
            <div class="form-group col-md-8">
                <label>Title</label>
                <input name="title" value="{{ old('title') }}" class="form-control" required>
            </div>
            <div class="form-group col-md-4">
                <label>Code</label>
                <input name="code" value="{{ old('code') }}" class="form-control" placeholder="Auto if blank">
            </div>
        </div>

        <div class="form-group">
            <label>Instructions</label>
            <textarea name="instructions" class="form-control" rows="7" required>{{ old('instructions') }}</textarea>
        </div>

        <div class="form-group">
            <label>Expected output</label>
            <textarea name="expected_output" class="form-control" rows="3">{{ old('expected_output') }}</textarea>
        </div>

        <div class="form-row">
            <div class="form-group col-md-4">
                <label>Opens</label>
                <input type="datetime-local" name="start_date" value="{{ old('start_date') }}" class="form-control" required>
            </div>
            <div class="form-group col-md-4">
                <label>Closes</label>
                <input type="datetime-local" name="end_date" value="{{ old('end_date') }}" class="form-control" required>
            </div>
            <div class="form-group col-md-4">
                <label>Duration (minutes)</label>
                <input type="number" min="1" max="480" name="duration" value="{{ old('duration',60) }}" class="form-control" required>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>Access, Submission & Scoring</strong></div>
    <div class="card-body">
        <div class="form-group">
            <label>Who may take it?</label>
            <select name="access_mode" class="form-control">
                <option value="all_taken_in" {{ old('access_mode','all_taken_in')==='all_taken_in'?'selected':'' }}>All taken-in applicants for the position</option>
                <option value="selected_applicants" {{ old('access_mode')==='selected_applicants'?'selected':'' }}>Selected applicants only</option>
            </select>
        </div>

        <div class="form-group">
            <label>Submission modes</label>
            <div>
                <label class="mr-3">
                    <input type="checkbox" name="submission_modes[]" value="inline" {{ in_array('inline',old('submission_modes',['inline']),true)?'checked':'' }}>
                    Inline response
                </label>
                <label>
                    <input type="checkbox" name="submission_modes[]" value="file" {{ in_array('file',old('submission_modes',[]),true)?'checked':'' }}>
                    File upload
                </label>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-md-4">
                <label>Allowed extensions</label>
                <input name="allowed_extensions" value="{{ old('allowed_extensions','docx') }}" class="form-control">
                <small class="text-muted">Comma-separated, e.g. docx,pdf,xlsx</small>
            </div>
            <div class="form-group col-md-4">
                <label>Max upload size (KB)</label>
                <input type="number" name="max_file_size_kb" value="{{ old('max_file_size_kb',10240) }}" class="form-control">
            </div>
            <div class="form-group col-md-4">
                <label>Score release</label>
                <select name="score_release_policy" class="form-control" required>
                    <option value="manual" {{ old('score_release_policy','manual')==='manual'?'selected':'' }}>Manual after validation</option>
                    <option value="after_close" {{ old('score_release_policy')==='after_close'?'selected':'' }}>After schedule closes</option>
                    <option value="immediate" {{ old('score_release_policy')==='immediate'?'selected':'' }}>Immediately after human finalization</option>
                    <option value="hidden" {{ old('score_release_policy')==='hidden'?'selected':'' }}>Always hidden</option>
                </select>
            </div>
        </div>

        <input type="hidden" name="ai_scoring" value="0">
        <label>
            <input type="checkbox" name="ai_scoring" value="1" {{ old('ai_scoring','1') ? 'checked' : '' }}>
            Enable AI proposed scoring
        </label>

        <input type="hidden" name="status" value="0">
        <div class="alert alert-light border mt-3 mb-0 small">
            New skills tests are created as <strong>Draft</strong>. Add/review the rubric and pass readiness checks before publishing.
        </div>
    </div>

    <div class="card-footer">
        <button class="btn btn-primary">Create Skills Test</button>
    </div>
</div>
</form>
@stop
