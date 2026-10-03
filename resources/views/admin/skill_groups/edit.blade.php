@extends('adminlte::page')
@section('title','Manage Skills Test Sets')
@section('content_header')
<div class="d-flex justify-content-between align-items-center">
<div><h1 class="mb-0">{{ $skillTestGroup->title }}</h1><small class="text-muted">{{ optional($skillTestGroup->vacancy)->position_title }} · {{ $skillTestGroup->code }}</small></div>
<a href="{{ route('admin.skill_groups.index') }}" class="btn btn-outline-secondary">Back to Groups</a>
</div>
@stop
@section('content')
@if(session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="card card-outline {{ $skillTestGroup->is_paused ? 'card-warning':'card-secondary' }}">
<div class="card-header"><strong>Group Operations</strong></div>
<div class="card-body">
@if($skillTestGroup->is_paused)
<div class="alert alert-warning">New starts are paused for every set in this group.<br><small>{{ $skillTestGroup->pause_reason }}</small></div>
<form method="post" action="{{ route('admin.skill_groups.resume',$skillTestGroup) }}" class="d-inline">@csrf
<button class="btn btn-sm btn-success">Resume All Sets</button>
</form>
@else
<form method="post" action="{{ route('admin.skill_groups.pause',$skillTestGroup) }}" class="form-inline mb-2">@csrf
<input name="reason" class="form-control form-control-sm mr-2" style="min-width:320px" placeholder="Reason for pausing all new starts" required>
<button class="btn btn-sm btn-warning">Pause All New Starts</button>
</form>
@endif

@if($skillTestGroup->score_release_policy==='manual')
@if($skillTestGroup->scores_released_at)
<form method="post" action="{{ route('admin.skill_groups.hide_scores',$skillTestGroup) }}" class="d-inline">@csrf
<button class="btn btn-sm btn-outline-warning">Withdraw Manual Release</button>
</form>
@else
<form method="post" action="{{ route('admin.skill_groups.release_scores',$skillTestGroup) }}" class="d-inline">@csrf
<button class="btn btn-sm btn-success">Release Official Scores for All Sets</button>
</form>
@endif
@endif

<form method="post" action="{{ route('admin.skill_groups.archive',$skillTestGroup) }}" class="d-inline ml-2"
onsubmit="return confirm('Archive and freeze this entire Skills Test group?');">@csrf
<button class="btn btn-sm btn-outline-secondary">Archive / Freeze Group</button>
</form>
</div>
</div>

<div class="card card-outline card-info">
<div class="card-header"><strong><i class="fas fa-magic mr-1"></i> Generate All Empty Sets with AI</strong></div>
<div class="card-body">
<p class="small text-muted">
One click generates all empty Set A/B/C tasks. The first generated set establishes the shared 100-point rubric.
The remaining sets receive different but equivalent tasks designed to use that same rubric. All generated tasks and rubric criteria remain <strong>Pending Review</strong>.
</p>
<form method="post" action="{{ route('admin.skill_groups.generate_all_sets',$skillTestGroup) }}"
onsubmit="return confirm('Queue AI generation for every empty draft set in this Skills Test Group?');">@csrf
<div class="form-row">
<div class="form-group col-md-4"><label>Generation focus</label>
<select name="generation_focus" class="form-control">
<option value="mixed">Mixed job-relevant</option>
<option value="duties">Duties and responsibilities</option>
<option value="technical">Technical competencies</option>
<option value="situational">Situational / work scenario</option>
</select></div>
<div class="form-group col-md-8"><label>Additional context <span class="text-muted font-weight-normal">(optional)</span></label>
<textarea name="additional_context" rows="3" maxlength="30000" class="form-control"></textarea></div>
</div>
<input type="hidden" name="use_qualifications" value="0"><input type="hidden" name="use_job_description" value="0">
<label class="mr-3"><input type="checkbox" name="use_qualifications" value="1" checked> Use qualifications</label>
<label class="mr-3"><input type="checkbox" name="use_job_description" value="1" checked> Use job description</label>
<button class="btn btn-info"><i class="fas fa-magic mr-1"></i> Generate All Empty Sets</button>
</form>
</div>
</div>

<div class="card card-outline card-primary">
<div class="card-header"><strong>Equivalent Sets</strong></div>
<div class="card-body table-responsive p-0">
<table class="table table-hover mb-0">
<thead><tr><th>Set</th><th>Task</th><th>Attempts</th><th>Assignments</th><th>Readiness</th><th>Status</th><th></th></tr></thead>
<tbody>
@foreach($skillTestGroup->skillTests as $set)
@php $ready=$readiness[$set->id] ?? ['ready'=>false,'issues'=>[]]; @endphp
<tr>
<td><span class="badge badge-primary">Set {{ $set->set_code }}</span></td>
<td><strong>{{ $set->title }}</strong><br><small class="text-muted">{{ Str::limit($set->instructions,90) }}</small></td>
<td>{{ $set->attempts_count }}</td>
<td>{{ $set->assignments_count }}</td>
<td>@if($ready['ready'])<span class="badge badge-success">Ready</span>@else<span class="badge badge-warning" title="{{ implode(' ',array_slice($ready['issues'],0,4)) }}">Needs review</span>@endif</td>
<td>{{ $set->status ? 'Published' : 'Draft' }}</td>
<td class="text-nowrap">
<a href="{{ route('admin.skills.edit',$set) }}" class="btn btn-sm btn-warning">Edit</a>
<a href="{{ route('admin.skills.preview',$set) }}" class="btn btn-sm btn-outline-info">Preview</a>
</td>
</tr>
@endforeach
</tbody>
</table>
</div>
<div class="card-footer">
<form method="post" action="{{ route('admin.skill_groups.equivalent_set',$skillTestGroup) }}" class="form-inline">@csrf
<label class="mr-2">Add equivalent set</label>
<select name="source_skill_test_id" class="form-control form-control-sm mr-2">
<option value="">Blank placeholder</option>
@foreach($skillTestGroup->skillTests as $set)
<option value="{{ $set->id }}">Copy Set {{ $set->set_code }} settings + rubric</option>
@endforeach
</select>
<button class="btn btn-sm btn-primary">Add Set</button>
</form>
</div>
</div>

<div class="card">
<div class="card-header"><strong>Shared Group Settings</strong></div>
<div class="card-body">
<form method="post" action="{{ route('admin.skill_groups.update',$skillTestGroup) }}">@csrf @method('put')
<input type="hidden" name="vacancy_id" value="{{ $skillTestGroup->vacancy_id }}">
<div class="form-row">
<div class="form-group col-md-6"><label>Title</label><input name="title" value="{{ old('title',$skillTestGroup->title) }}" class="form-control" required></div>
<div class="form-group col-md-3"><label>Code</label><input name="code" value="{{ old('code',$skillTestGroup->code) }}" class="form-control"></div>
<div class="form-group col-md-3"><label>Expected sets</label><input type="number" min="1" max="26" name="expected_sets" value="{{ old('expected_sets',$skillTestGroup->expected_sets) }}" class="form-control"></div>
</div>
@php $first=$skillTestGroup->skillTests->first(); @endphp
<div class="form-row">
<div class="form-group col-md-4"><label>Opens</label><input type="datetime-local" name="start_date" value="{{ old('start_date',optional($first?->start_date)->format('Y-m-d\TH:i')) }}" class="form-control" required></div>
<div class="form-group col-md-4"><label>Closes</label><input type="datetime-local" name="end_date" value="{{ old('end_date',optional($first?->end_date)->format('Y-m-d\TH:i')) }}" class="form-control" required></div>
<div class="form-group col-md-4"><label>Duration</label><input type="number" min="1" max="480" name="duration" value="{{ old('duration',$first?->duration ?? 60) }}" class="form-control"></div>
</div>
<div class="form-row">
<div class="form-group col-md-4"><label>Access</label><select name="access_mode" class="form-control"><option value="all_taken_in" {{ ($first?->access_mode)==='all_taken_in'?'selected':'' }}>All taken-in</option><option value="selected_applicants" {{ ($first?->access_mode)==='selected_applicants'?'selected':'' }}>Selected applicants</option></select></div>
<div class="form-group col-md-4"><label>Submission modes</label><div class="pt-2"><label class="mr-3"><input type="checkbox" name="submission_modes[]" value="inline" {{ in_array('inline',$first?->submission_modes ?? [],true)?'checked':'' }}> Inline</label><label><input type="checkbox" name="submission_modes[]" value="file" {{ in_array('file',$first?->submission_modes ?? [],true)?'checked':'' }}> File</label></div></div>
<div class="form-group col-md-4"><label>Allowed extensions</label><input name="allowed_extensions" value="{{ implode(',',$first?->allowed_extensions ?? ['docx']) }}" class="form-control"></div>
</div>
<div class="form-row">
<div class="form-group col-md-3"><label>Max upload KB</label><input type="number" name="max_file_size_kb" value="{{ $first?->max_file_size_kb ?? 10240 }}" class="form-control"></div>
<div class="form-group col-md-3"><label>AI scoring</label><select name="ai_scoring" class="form-control"><option value="1" {{ $first?->ai_scoring?'selected':'' }}>Enabled</option><option value="0" {{ !$first?->ai_scoring?'selected':'' }}>Disabled</option></select></div>
<div class="form-group col-md-3"><label>Score release</label><select name="score_release_policy" class="form-control">@foreach(['manual'=>'Manual','after_close'=>'After close','immediate'=>'Immediate','hidden'=>'Hidden'] as $k=>$label)<option value="{{ $k }}" {{ $skillTestGroup->score_release_policy===$k?'selected':'' }}>{{ $label }}</option>@endforeach</select></div>
<div class="form-group col-md-3"><label>Status</label><select name="status" class="form-control"><option value="1" {{ $skillTestGroup->status?'selected':'' }}>Active</option><option value="0" {{ !$skillTestGroup->status?'selected':'' }}>Inactive</option></select></div>
</div>
<div class="form-group"><label>Applicant score criterion</label><select name="assessment_score_key" class="form-control"><option value="">Do not sync</option>@foreach($scoreCriteria as $key=>$max)<option value="{{ $key }}" {{ $skillTestGroup->assessment_score_key===$key?'selected':'' }}>{{ $key }} ({{ number_format($max,2) }} pts)</option>@endforeach</select></div>
<button class="btn btn-primary">Save Shared Settings</button>
</form>
</div>
</div>
@stop
