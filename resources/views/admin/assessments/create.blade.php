@extends('adminlte::page')
@section('title','New Written Exam')
@section('content_header')<h1>New Written Exam</h1>@stop
@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('admin.assessments.store') }}">@csrf
<div class="card"><div class="card-body">
<div class="form-group"><label>Position</label><select name="vacancy_id" class="form-control" required>
<option value="">Select</option>@foreach($vacancies as $v)<option value="{{ $v->id }}" {{ old('vacancy_id', optional($selectedGroup)->vacancy_id)==$v->id?'selected':'' }}>{{ $v->position_title }} ({{ $v->cycle }})</option>@endforeach
</select></div>
<div class="form-row">
<div class="form-group col-md-8"><label>Equivalent Assessment Group <span class="text-muted font-weight-normal">(optional)</span></label>
<select name="assessment_group_id" class="form-control">
<option value="">Standalone written exam</option>
@foreach($groups as $group)
<option value="{{ $group->id }}" {{ old('assessment_group_id', optional($selectedGroup)->id)==$group->id?'selected':'' }}>
{{ $group->title }} — {{ optional($group->vacancy)->position_title }}
</option>
@endforeach
</select>
<small class="text-muted">Use a group when Set A, Set B, etc. are equivalent alternatives and each applicant must take only one.</small></div>
<div class="form-group col-md-4"><label>Set code</label><input name="set_code" value="{{ old('set_code') }}" class="form-control" placeholder="e.g. A, B, C"></div>
</div>
<div class="form-row">
<div class="form-group col-md-8"><label>Exam title</label><input name="title" value="{{ old('title') }}" class="form-control" required></div>
<div class="form-group col-md-4"><label>Exam code</label><input name="code" value="{{ old('code') }}" class="form-control" placeholder="Auto if blank"></div>
</div>
<div class="form-row">
<div class="form-group col-md-4"><label>Opens</label><input type="datetime-local" name="start_date" value="{{ old('start_date') }}" class="form-control" required></div>
<div class="form-group col-md-4"><label>Closes</label><input type="datetime-local" name="end_date" value="{{ old('end_date') }}" class="form-control" required></div>
<div class="form-group col-md-4"><label>Duration (minutes)</label><input type="number" min="1" name="duration" value="{{ old('duration',60) }}" class="form-control" required></div>
</div>
<div class="form-group"><label>Who may take it?</label><select name="access_mode" class="form-control">
<option value="all_taken_in" selected>All taken-in applicants for the position</option>
<option value="selected_applicants">Selected applicants only</option>
</select><small class="text-muted">Explicit assignment is optional; all taken-in is the scalable default.</small></div>
<div class="form-row">
<div class="form-group col-md-4"><label>Shuffle questions</label><select name="shuffle_items" class="form-control"><option value="1">Yes</option><option value="0">No</option></select></div>
<div class="form-group col-md-4"><label>Shuffle options</label><select name="shuffle_options" class="form-control"><option value="1">Yes</option><option value="0">No</option></select></div>
<div class="form-group col-md-4"><label>Status</label><select name="status" class="form-control"><option value="0">Draft</option><option value="1">Published</option></select></div>
</div>
</div><div class="card-footer"><button class="btn btn-primary">Create Exam Set</button></div></div>
</form>
@stop
