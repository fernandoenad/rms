@extends('adminlte::page')
@section('title','New Assessment Group')
@section('content_header')<h1>New Assessment Group</h1>@stop

@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<form method="post" action="{{ route('admin.assessment_groups.store') }}">@csrf
<div class="card">
    <div class="card-header"><strong>Assessment Structure</strong></div>
    <div class="card-body">
        <div class="form-group">
            <label>Position</label>
            <select name="vacancy_id" class="form-control" required>
                <option value="">Select position</option>
                @foreach($vacancies as $v)
                    <option value="{{ $v->id }}" {{ old('vacancy_id')==$v->id?'selected':'' }}>
                        {{ $v->position_title }} ({{ $v->cycle }})
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-row">
            <div class="form-group col-md-6">
                <label>Assessment title</label>
                <input name="title" value="{{ old('title') }}" class="form-control" required
                       placeholder="e.g. Administrative Officer II Written Assessment">
            </div>
            <div class="form-group col-md-3">
                <label>Group code</label>
                <input name="code" value="{{ old('code') }}" class="form-control" placeholder="e.g. AOII-WRITTEN-2026">
            </div>
            <div class="form-group col-md-3">
                <label>Number of equivalent sets</label>
                <input type="number" min="1" max="26" name="expected_sets" value="{{ old('expected_sets',1) }}" class="form-control" required>
                <small class="text-muted">RMS creates Set A, B, C... as drafts.</small>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-md-4">
                <label>Default duration (minutes)</label>
                <input type="number" min="1" max="480" name="default_duration" value="{{ old('default_duration',60) }}" class="form-control">
            </div>
            <div class="form-group col-md-4">
                <label>Score release</label>
                <select name="score_release_policy" class="form-control" required>
                    <option value="manual" {{ old('score_release_policy','manual')==='manual'?'selected':'' }}>Manual release after validation</option>
                    <option value="after_close" {{ old('score_release_policy')==='after_close'?'selected':'' }}>After all set schedules close</option>
                    <option value="immediate" {{ old('score_release_policy')==='immediate'?'selected':'' }}>Immediately after submission</option>
                    <option value="hidden" {{ old('score_release_policy')==='hidden'?'selected':'' }}>Always hidden</option>
                </select>
            </div>
            <div class="form-group col-md-4">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="1" {{ old('status','1')==='1'?'selected':'' }}>Active group</option>
                    <option value="0" {{ old('status')==='0'?'selected':'' }}>Inactive group</option>
                </select>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>Shared Blueprint / TOS</strong></div>
    <div class="card-body">
        <div class="alert alert-light border small">
            Every equivalent set is checked against this blueprint before it can be published.
            Leave Item Count blank if you do not want blueprint enforcement yet.
        </div>

        <div class="form-group col-md-3 pl-0">
            <label>Items per set</label>
            <input type="number" min="1" max="300" name="blueprint_item_count" value="{{ old('blueprint_item_count') }}" class="form-control">
        </div>

        <label>SOLO Distribution (%)</label>
        <div class="form-row">
            <div class="form-group col-md-3"><input type="number" min="0" max="100" name="solo_unistructural" value="{{ old('solo_unistructural',10) }}" class="form-control" placeholder="Unistructural"></div>
            <div class="form-group col-md-3"><input type="number" min="0" max="100" name="solo_multistructural" value="{{ old('solo_multistructural',20) }}" class="form-control" placeholder="Multistructural"></div>
            <div class="form-group col-md-3"><input type="number" min="0" max="100" name="solo_relational" value="{{ old('solo_relational',45) }}" class="form-control" placeholder="Relational"></div>
            <div class="form-group col-md-3"><input type="number" min="0" max="100" name="solo_extended_abstract" value="{{ old('solo_extended_abstract',25) }}" class="form-control" placeholder="Extended Abstract"></div>
        </div>

        <label>Difficulty Distribution (%)</label>
        <div class="form-row">
            <div class="form-group col-md-4"><input type="number" min="0" max="100" name="difficulty_easy" value="{{ old('difficulty_easy',20) }}" class="form-control" placeholder="Easy"></div>
            <div class="form-group col-md-4"><input type="number" min="0" max="100" name="difficulty_moderate" value="{{ old('difficulty_moderate',60) }}" class="form-control" placeholder="Moderate"></div>
            <div class="form-group col-md-4"><input type="number" min="0" max="100" name="difficulty_difficult" value="{{ old('difficulty_difficult',20) }}" class="form-control" placeholder="Difficult"></div>
        </div>

        <div class="form-group">
            <label>Competencies / constructs <span class="text-muted font-weight-normal">(optional)</span></label>
            <textarea name="blueprint_competencies" rows="5" class="form-control"
                      placeholder="One per line. Optional target count after |&#10;Records Management | 10&#10;Written Communication | 8">{{ old('blueprint_competencies') }}</textarea>
        </div>
    </div>
    <div class="card-footer">
        <button class="btn btn-primary">Create Assessment Group & Draft Sets</button>
    </div>
</div>
</form>
@stop
