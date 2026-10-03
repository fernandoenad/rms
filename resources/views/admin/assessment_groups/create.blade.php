@extends('adminlte::page')
@section('title','New Assessment Group')
@section('content_header')<h1>New Assessment Group</h1>@stop

@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<form method="post" action="{{ route('admin.assessment_groups.store') }}">@csrf
<div class="card">
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
            <div class="form-group col-md-8">
                <label>Assessment title</label>
                <input name="title" value="{{ old('title') }}" class="form-control" required
                       placeholder="e.g. Administrative Officer II Written Assessment">
            </div>
            <div class="form-group col-md-4">
                <label>Group code</label>
                <input name="code" value="{{ old('code') }}" class="form-control"
                       placeholder="e.g. AOII-WRITTEN-2026">
            </div>
        </div>

        <div class="form-group">
            <label>Status</label>
            <select name="status" class="form-control">
                <option value="1" {{ old('status','1')==='1'?'selected':'' }}>Active</option>
                <option value="0" {{ old('status')==='0'?'selected':'' }}>Inactive</option>
            </select>
        </div>
    </div>
    <div class="card-footer">
        <button class="btn btn-primary">Create Assessment Group</button>
    </div>
</div>
</form>
@stop
