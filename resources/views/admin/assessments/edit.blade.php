@extends('adminlte::page')
@section('title','Edit Written Exam')
@section('content_header')
<div class="d-flex justify-content-between align-items-center">
    <h1 class="mb-0">Edit Written Exam</h1>
    <a href="{{ route('admin.assessments.preview',$exam) }}" class="btn btn-outline-info"><i class="fas fa-eye"></i> Preview & Dry Run</a>
</div>
@stop
@section('content')
@if($exam->access_mode==='selected_applicants')
<div class="card border-info"><div class="card-header"><strong>Optional Selected-Applicant Access</strong></div><div class="card-body">
<form method="post" action="{{ route('admin.assessments.assign',$exam) }}">@csrf
<label>Paste application codes</label>
<textarea name="application_codes" class="form-control" rows="4" placeholder="One per line, or separate with commas/spaces" required></textarea>
<small class="text-muted">Only taken-in applicants for this position will be assigned. Use this only for special batches; All taken-in remains the normal mode.</small><br>
<button class="btn btn-info mt-2">Add Assignments</button>
</form></div></div>
@endif
@if(session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="card card-outline {{ $exam->is_paused ? 'card-warning' : 'card-secondary' }}">
    <div class="card-header"><strong>Operational Control</strong></div>
    <div class="card-body">
        @if($exam->archived_at)
            <div class="alert alert-secondary mb-0">This written test is archived and frozen.</div>
        @elseif($exam->is_paused)
            <div class="alert alert-warning">New starts are paused. Existing in-progress attempts may continue.<br><small>{{ $exam->pause_reason }}</small></div>
            <form method="post" action="{{ route('admin.assessments.resume',$exam) }}" class="d-inline">@csrf
                <button class="btn btn-success btn-sm">Resume New Starts</button>
            </form>
        @else
            <form method="post" action="{{ route('admin.assessments.pause',$exam) }}" class="form-inline mb-2">@csrf
                <input name="reason" class="form-control form-control-sm mr-2" style="min-width:320px" placeholder="Reason for pausing new starts" required>
                <button class="btn btn-warning btn-sm">Pause New Starts</button>
            </form>
            <form method="post" action="{{ route('admin.assessments.archive',$exam) }}"
                  onsubmit="return confirm('Archive and freeze this written test? This is blocked while attempts are in progress.');">@csrf
                <button class="btn btn-outline-secondary btn-sm">Archive / Freeze</button>
            </form>
        @endif
    </div>
</div>

<div class="card card-outline {{ $exam->approval_status==='approved' ? 'card-success' : 'card-warning' }}">
    <div class="card-header"><strong>Assessment Approval</strong></div>
    <div class="card-body">
        <p class="mb-2">
            Status:
            @if($exam->approval_status==='approved')
                <span class="badge badge-success">Approved</span>
            @else
                <span class="badge badge-warning">Pending approval</span>
            @endif
        </p>
        @if($exam->approval_notes)
            <div class="small text-muted mb-2">{{ $exam->approval_notes }}</div>
        @endif
        @if(auth()->user() && auth()->user()->role && (int)auth()->user()->role->level===1 && $exam->approval_status!=='approved')
            <form method="post" action="{{ route('admin.assessments.approve',$exam) }}">@csrf
                <textarea name="approval_notes" class="form-control mb-2" rows="2" placeholder="Approval notes (optional)"></textarea>
                <button class="btn btn-success btn-sm">Approve Written Test</button>
            </form>
        @endif
    </div>
</div>
<form method="post" action="{{ route('admin.assessments.update',$exam) }}">@csrf @method('put')
<div class="card"><div class="card-body">
<div class="form-group"><label>Position</label><select name="vacancy_id" class="form-control" required>
@foreach($vacancies as $v)<option value="{{ $v->id }}" {{ old('vacancy_id',$exam->vacancy_id)==$v->id?'selected':'' }}>{{ $v->position_title }} ({{ $v->cycle }})</option>@endforeach
</select></div>
<div class="form-row">
<div class="form-group col-md-8"><label>Equivalent Assessment Group <span class="text-muted font-weight-normal">(optional)</span></label>
<select name="assessment_group_id" class="form-control">
<option value="">Standalone written exam</option>
@foreach($groups as $group)
<option value="{{ $group->id }}" {{ old('assessment_group_id',$exam->assessment_group_id)==$group->id?'selected':'' }}>
{{ $group->title }} — {{ optional($group->vacancy)->position_title }}
</option>
@endforeach
</select></div>
<div class="form-group col-md-4"><label>Set code</label><input name="set_code" value="{{ old('set_code',$exam->set_code) }}" class="form-control" placeholder="e.g. A, B, C"></div>
</div>
@if(!$exam->assessment_group_id)
<div class="form-group">
<label>Applicant score criterion</label>
<select name="assessment_score_key" class="form-control">
<option value="">Do not write this standalone test into applicant scores</option>
@foreach($scoreCriteria as $key=>$max)
<option value="{{ $key }}" {{ old('assessment_score_key',$exam->assessment_score_key)===$key?'selected':'' }}>{{ $key }} ({{ number_format($max,2) }} pts)</option>
@endforeach
</select>
<small class="text-muted">Standalone Written scores synchronize immediately after submission. Equivalent-set exams use the Assessment Group's score mapping instead.</small>
</div>
@else
<input type="hidden" name="assessment_score_key" value="">
<div class="alert alert-light border small">Applicant-score synchronization for this set is controlled by <strong>{{ optional($exam->assessmentGroup)->title }}</strong>.</div>
@endif
<div class="form-row">
<div class="form-group col-md-8"><label>Exam title</label><input name="title" value="{{ old('title',$exam->title) }}" class="form-control" required></div>
<div class="form-group col-md-4"><label>Exam code</label><input name="code" value="{{ old('code',$exam->code) }}" class="form-control"></div>
</div>
<div class="form-row">
<div class="form-group col-md-4"><label>Opens</label><input type="datetime-local" name="start_date" value="{{ old('start_date',optional($exam->start_date)->format('Y-m-d\TH:i')) }}" class="form-control" required></div>
<div class="form-group col-md-4"><label>Closes</label><input type="datetime-local" name="end_date" value="{{ old('end_date',optional($exam->end_date)->format('Y-m-d\TH:i')) }}" class="form-control" required></div>
<div class="form-group col-md-4"><label>Duration</label><input type="number" min="1" name="duration" value="{{ old('duration',$exam->duration) }}" class="form-control" required></div>
</div>
<div class="form-group"><label>Access</label><select name="access_mode" class="form-control">
<option value="all_taken_in" {{ $exam->access_mode==='all_taken_in'?'selected':'' }}>All taken-in applicants</option>
<option value="selected_applicants" {{ $exam->access_mode==='selected_applicants'?'selected':'' }}>Selected applicants only</option>
</select></div>
<div class="form-row">
<div class="form-group col-md-4"><label>Shuffle questions</label><select name="shuffle_items" class="form-control"><option value="1" {{ $exam->shuffle_items?'selected':'' }}>Yes</option><option value="0" {{ !$exam->shuffle_items?'selected':'' }}>No</option></select></div>
<div class="form-group col-md-4"><label>Shuffle options</label><select name="shuffle_options" class="form-control"><option value="1" {{ $exam->shuffle_options?'selected':'' }}>Yes</option><option value="0" {{ !$exam->shuffle_options?'selected':'' }}>No</option></select></div>
<div class="form-group col-md-4"><label>Status</label><select name="status" class="form-control"><option value="0" {{ !$exam->status?'selected':'' }}>Draft</option><option value="1" {{ $exam->status?'selected':'' }}>Published</option></select></div>
</div>
@if($exam->attempts()->exists())<div class="alert alert-warning">Settings are locked because attempts exist. Duplicate this exam to create a new set/version.</div>@endif
</div><div class="card-footer"><button class="btn btn-primary" {{ $exam->attempts()->exists()?'disabled':'' }}>Save</button></div></div>
</form>
@stop
