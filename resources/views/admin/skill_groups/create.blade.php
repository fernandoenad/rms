@extends('adminlte::page')
@section('title','New Skills Test Group')
@section('content_header')<h1>New Skills Test Group</h1>@stop
@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('admin.skill_groups.store') }}">@csrf
<div class="card">
<div class="card-header"><strong>Equivalent Skills Test Sets</strong></div>
<div class="card-body">
<div class="form-group">
<label>Position</label>
<select name="vacancy_id" id="skillGroupVacancy" class="form-control" required>
<option value="">Select</option>
@foreach($vacancies as $v)
<option value="{{ $v->id }}" {{ old('vacancy_id')==$v->id?'selected':'' }}>#{{ $v->id }} — {{ $v->position_title }} ({{ $v->cycle }})</option>
@endforeach
</select>
</div>
<div class="form-row">
<div class="form-group col-md-7"><label>Group title</label><input name="title" value="{{ old('title') }}" class="form-control" required></div>
<div class="form-group col-md-3"><label>Code</label><input name="code" value="{{ old('code') }}" class="form-control" placeholder="Auto if blank"></div>
<div class="form-group col-md-2"><label>No. of sets</label><input type="number" min="1" max="26" name="expected_sets" value="{{ old('expected_sets',3) }}" class="form-control" required></div>
</div>
<div class="form-row">
<div class="form-group col-md-4">
<label>Default duration (minutes)</label>
<input type="number" min="1" max="480" name="duration" value="{{ old('duration',60) }}" class="form-control" required>
<small class="text-muted">Applied to new draft sets. Each set gets its own opening and closing schedule later.</small>
</div>
</div>
<div class="form-row">
<div class="form-group col-md-4"><label>Access</label><select name="access_mode" class="form-control"><option value="all_taken_in">All taken-in</option><option value="selected_applicants">Selected applicants</option></select></div>
<div class="form-group col-md-4"><label>Submission modes</label><div class="pt-2"><label class="mr-3"><input type="checkbox" name="submission_modes[]" value="inline" checked> Inline</label><label><input type="checkbox" name="submission_modes[]" value="file"> File</label></div></div>
<div class="form-group col-md-4"><label>Allowed extensions</label><input name="allowed_extensions" value="{{ old('allowed_extensions','docx') }}" class="form-control"></div>
</div>
<div class="form-row">
<div class="form-group col-md-3"><label>Max upload (KB)</label><input type="number" name="max_file_size_kb" value="{{ old('max_file_size_kb',10240) }}" class="form-control"></div>
<div class="form-group col-md-3"><label>AI scoring</label><select name="ai_scoring" class="form-control"><option value="1">Enabled</option><option value="0">Disabled</option></select></div>
<div class="form-group col-md-3"><label>Score release</label><select name="score_release_policy" class="form-control"><option value="manual">Manual</option><option value="after_close">After close</option><option value="immediate">Immediate</option><option value="hidden">Hidden</option></select></div>
<div class="form-group col-md-3"><label>Group status</label><select name="status" class="form-control"><option value="1">Active</option><option value="0">Inactive</option></select></div>
</div>
<div class="form-group">
<label>Applicant score criterion</label>
<select name="assessment_score_key" id="skillGroupScoreKey" class="form-control"><option value="">Do not sync to applicant scores</option></select>
</div>
<div class="alert alert-light border small mb-0">
The group controls shared governance such as access, submission rules, score release, and applicant-score mapping. Each set has its own opening/closing schedule, task, and rubric, and must pass review/readiness independently.
</div>
</div>
<div class="card-footer"><button class="btn btn-primary">Create Group & Draft Sets</button></div>
</div>
</form>
@stop
@section('js')
<script>
(() => {
const criteria=@json($scoreCriteriaByVacancy);
const vacancy=document.getElementById('skillGroupVacancy');
const score=document.getElementById('skillGroupScoreKey');
const old=@json(old('assessment_score_key'));
function refresh(){
 const rows=criteria[String(vacancy.value)]||{};
 score.innerHTML='<option value="">Do not sync to applicant scores</option>';
 Object.entries(rows).forEach(([key,max])=>{
  const o=document.createElement('option');o.value=key;o.textContent=key+' ('+Number(max).toLocaleString()+' pts)';o.selected=key===old;score.appendChild(o);
 });
}
vacancy.addEventListener('change',refresh);refresh();
})();
</script>
@stop
