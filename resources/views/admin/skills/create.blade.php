@extends('adminlte::page')
@section('title','New Skills Test')
@section('content_header')<h1>New Skills Test</h1>@stop
@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('admin.skills.store') }}">@csrf
<div class="card"><div class="card-body">
<div class="form-group"><label>Position</label><select name="vacancy_id" class="form-control" required>
<option value="">Select</option>@foreach($vacancies as $v)<option value="{{ $v->id }}">{{ $v->position_title }} ({{ $v->cycle }})</option>@endforeach
</select></div>
<div class="form-row">
<div class="form-group col-md-8"><label>Title</label><input name="title" class="form-control" required></div>
<div class="form-group col-md-4"><label>Code</label><input name="code" class="form-control" placeholder="Auto if blank"></div>
</div>
<div class="form-group"><label>Instructions</label><textarea name="instructions" class="form-control" rows="6" required></textarea></div>
<div class="form-group"><label>Expected output</label><textarea name="expected_output" class="form-control" rows="3"></textarea></div>
<div class="form-row">
<div class="form-group col-md-4"><label>Opens</label><input type="datetime-local" name="start_date" class="form-control" required></div>
<div class="form-group col-md-4"><label>Closes</label><input type="datetime-local" name="end_date" class="form-control" required></div>
<div class="form-group col-md-4"><label>Duration (minutes)</label><input type="number" min="1" name="duration" class="form-control" required></div>
</div>
<div class="form-group"><label>Who may take it?</label><select name="access_mode" class="form-control">
<option value="all_taken_in">All taken-in applicants for the position</option>
<option value="selected_applicants">Selected applicants only</option>
</select></div>
<div class="form-group"><label>Submission modes</label><div>
<label class="mr-3"><input type="checkbox" name="submission_modes[]" value="inline" checked> Inline response</label>
<label><input type="checkbox" name="submission_modes[]" value="file"> File upload</label>
</div></div>
<div class="form-row">
<div class="form-group col-md-6"><label>Allowed extensions</label><input name="allowed_extensions" value="docx" class="form-control"><small>Comma-separated, e.g. docx,pdf,xlsx</small></div>
<div class="form-group col-md-6"><label>Max upload size (KB)</label><input type="number" name="max_file_size_kb" value="10240" class="form-control"></div>
</div>
<input type="hidden" name="ai_scoring" value="0"><label class="mr-4"><input type="checkbox" name="ai_scoring" value="1" checked> Enable AI proposed scoring</label>
<input type="hidden" name="status" value="0"><label><input type="checkbox" name="status" value="1"> Publish now</label>
</div><div class="card-footer"><button class="btn btn-primary">Create Skills Test</button></div></div>
</form>
@stop
