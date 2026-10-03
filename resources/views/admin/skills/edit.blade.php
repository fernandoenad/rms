@extends('adminlte::page')
@section('title','Skills Test')
@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">{{ $skillTest->title }}</h1>
        <small class="text-muted">Task v{{ $skillTest->task_version }} · {{ optional($skillTest->vacancy)->position_title }}</small>
    </div>
    <div class="mt-2 mt-md-0">
        <a href="{{ route('admin.skills.results',$skillTest) }}" class="btn btn-outline-info mr-2"><i class="fas fa-chart-bar"></i> Live Results</a>
        <a href="{{ route('admin.skills.export',$skillTest) }}" class="btn btn-outline-success"><i class="fas fa-file-csv"></i> Export CSV</a>
    </div>
</div>
@stop

@section('content')
@if(session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="card card-outline {{ $skillTest->approval_status==='approved' ? 'card-success' : 'card-warning' }}">
    <div class="card-header"><strong>Assessment Approval</strong></div>
    <div class="card-body">
        <p class="mb-2">
            Status:
            @if($skillTest->approval_status==='approved')
                <span class="badge badge-success">Approved</span>
            @else
                <span class="badge badge-warning">Pending approval</span>
            @endif
        </p>
        @if($skillTest->approval_notes)
            <div class="small text-muted mb-2">{{ $skillTest->approval_notes }}</div>
        @endif
        @if(auth()->user() && auth()->user()->role && (int)auth()->user()->role->level===1 && $skillTest->approval_status!=='approved')
            <form method="post" action="{{ route('admin.skills.approve',$skillTest) }}">@csrf
                <textarea name="approval_notes" class="form-control mb-2" rows="2" placeholder="Approval notes (optional)"></textarea>
                <button class="btn btn-success btn-sm">Approve Skills Test</button>
            </form>
        @endif
    </div>
</div>

<div class="card card-outline {{ $readiness['ready'] ? 'card-success' : 'card-warning' }}">
    <div class="card-header"><strong>Readiness</strong></div>
    <div class="card-body py-2">
        @if($readiness['ready'])
            <span class="badge badge-success">Ready to publish</span>
            <span class="ml-2 text-muted">Rubric total: {{ number_format($readiness['rubric_total'],2) }}/100</span>
        @else
            <span class="badge badge-warning">Needs review</span>
            <ul class="mb-0 mt-2">@foreach($readiness['issues'] as $issue)<li>{{ $issue }}</li>@endforeach</ul>
        @endif
    </div>
</div>

<div class="d-flex flex-wrap mb-3">
    <form method="post" action="{{ route('admin.skills.toggle',$skillTest) }}" class="mr-2">@csrf
        <button class="btn {{ $skillTest->status ? 'btn-outline-warning':'btn-success' }}">
            {{ $skillTest->status ? 'Return to Draft':'Publish Skills Test' }}
        </button>
    </form>

    <form method="post" action="{{ route('admin.skills.revision',$skillTest) }}" onsubmit="return confirm('Create a new draft revision while preserving this task and rubric?');">
        @csrf
        <button class="btn btn-outline-secondary"><i class="fas fa-code-branch"></i> Create Revision</button>
    </form>
</div>

@if($skillTest->access_mode==='selected_applicants')
<div class="card border-info">
    <div class="card-header"><strong>Selected-Applicant Access</strong></div>
    <div class="card-body">
        <form method="post" action="{{ route('admin.skills.assign',$skillTest) }}">@csrf
            <textarea name="application_codes" class="form-control" rows="4" placeholder="Paste application codes, one per line or comma-separated" required></textarea>
            <small class="text-muted">Only taken-in applicants for this position are accepted.</small><br>
            <button class="btn btn-info mt-2">Add Assignments</button>
        </form>
    </div>
</div>
@endif

@php $locked = $skillTest->status || $hasStartedAttempts; @endphp

<div class="card">
    <div class="card-header"><strong>Task & Settings</strong></div>
    <div class="card-body">
        <form method="post" action="{{ route('admin.skills.update',$skillTest) }}">@csrf @method('put')
            <input type="hidden" name="vacancy_id" value="{{ $skillTest->vacancy_id }}">

            <div class="form-row">
                <div class="form-group col-md-8">
                    <label>Title</label>
                    <input name="title" value="{{ old('title',$skillTest->title) }}" class="form-control" {{ $locked?'disabled':'' }} required>
                </div>
                <div class="form-group col-md-4">
                    <label>Code</label>
                    <input name="code" value="{{ old('code',$skillTest->code) }}" class="form-control" {{ $locked?'disabled':'' }}>
                </div>
            </div>

            <div class="form-group">
                <label>Instructions</label>
                <textarea name="instructions" class="form-control" rows="7" {{ $locked?'disabled':'' }} required>{{ old('instructions',$skillTest->instructions) }}</textarea>
            </div>

            <div class="form-group">
                <label>Expected output</label>
                <textarea name="expected_output" class="form-control" rows="3" {{ $locked?'disabled':'' }}>{{ old('expected_output',$skillTest->expected_output) }}</textarea>
            </div>

            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Opens</label>
                    <input type="datetime-local" name="start_date" value="{{ old('start_date',optional($skillTest->start_date)->format('Y-m-d\TH:i')) }}" class="form-control" {{ $locked?'disabled':'' }} required>
                </div>
                <div class="form-group col-md-4">
                    <label>Closes</label>
                    <input type="datetime-local" name="end_date" value="{{ old('end_date',optional($skillTest->end_date)->format('Y-m-d\TH:i')) }}" class="form-control" {{ $locked?'disabled':'' }} required>
                </div>
                <div class="form-group col-md-4">
                    <label>Duration (minutes)</label>
                    <input type="number" min="1" max="480" name="duration" value="{{ old('duration',$skillTest->duration) }}" class="form-control" {{ $locked?'disabled':'' }} required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Access</label>
                    <select name="access_mode" class="form-control" {{ $locked?'disabled':'' }}>
                        <option value="all_taken_in" {{ $skillTest->access_mode==='all_taken_in'?'selected':'' }}>All taken-in applicants</option>
                        <option value="selected_applicants" {{ $skillTest->access_mode==='selected_applicants'?'selected':'' }}>Selected applicants only</option>
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label>Score release</label>
                    <select name="score_release_policy" class="form-control" {{ $locked?'disabled':'' }}>
                        @foreach(['manual'=>'Manual after validation','after_close'=>'After schedule closes','immediate'=>'Immediately after human finalization','hidden'=>'Always hidden'] as $value=>$label)
                            <option value="{{ $value }}" {{ $skillTest->score_release_policy===$value?'selected':'' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label>Max upload size (KB)</label>
                    <input type="number" name="max_file_size_kb" value="{{ $skillTest->max_file_size_kb }}" class="form-control" {{ $locked?'disabled':'' }}>
                </div>
            </div>

            <div class="form-group">
                <label>Submission modes</label><br>
                <label class="mr-3"><input type="checkbox" name="submission_modes[]" value="inline" {{ in_array('inline',$skillTest->submission_modes ?: [],true)?'checked':'' }} {{ $locked?'disabled':'' }}> Inline response</label>
                <label><input type="checkbox" name="submission_modes[]" value="file" {{ in_array('file',$skillTest->submission_modes ?: [],true)?'checked':'' }} {{ $locked?'disabled':'' }}> File upload</label>
            </div>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Allowed extensions</label>
                    <input name="allowed_extensions" value="{{ implode(',',$skillTest->allowed_extensions ?: []) }}" class="form-control" {{ $locked?'disabled':'' }}>
                </div>
                <div class="form-group col-md-6">
                    <label>AI proposed scoring</label><br>
                    <input type="hidden" name="ai_scoring" value="0">
                    <label><input type="checkbox" name="ai_scoring" value="1" {{ $skillTest->ai_scoring?'checked':'' }} {{ $locked?'disabled':'' }}> Enabled</label>
                </div>
            </div>

            <input type="hidden" name="status" value="0">
            @unless($locked)
                <button class="btn btn-primary">Save Task Settings</button>
            @else
                <div class="alert alert-warning mb-0">This task is immutable while published or after an applicant has started. Create a revision for future changes.</div>
            @endunless
        </form>
    </div>
</div>

<div class="row">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><strong>Task Review</strong></div>
            <div class="card-body">
                <p><strong>Status:</strong>
                    @if($skillTest->review_status==='approved')<span class="badge badge-success">Approved</span>
                    @elseif($skillTest->review_status==='rejected')<span class="badge badge-danger">Rejected</span>
                    @else<span class="badge badge-warning">Pending Review</span>@endif
                </p>
                @if($skillTest->review_notes)<div class="small mb-3"><strong>Notes:</strong> {{ $skillTest->review_notes }}</div>@endif

                @unless($locked)
                <form method="post" action="{{ route('admin.skills.review_task',$skillTest) }}">@csrf @method('put')
                    <div class="form-group">
                        <select name="decision" class="form-control">
                            <option value="approved">Approve</option>
                            <option value="pending_review">Return to Pending</option>
                            <option value="rejected">Reject</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <textarea name="review_notes" rows="3" class="form-control" placeholder="Reviewer notes">{{ $skillTest->review_notes }}</textarea>
                    </div>
                    <button class="btn btn-primary">Save Task Review</button>
                </form>

                <hr>
                <form method="post" action="{{ route('admin.skills.ai_generate',$skillTest) }}">@csrf
                    <strong>AI Generation Context</strong>
                    <div class="small text-muted mb-2">Vacancy context is included automatically when selected. You may paste approved job-specific reference material below.</div>

                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" class="custom-control-input" id="skillUseQualifications" name="use_qualifications" value="1"
                               {{ old('use_qualifications',$skillTest->ai_use_qualifications ?? true) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="skillUseQualifications">Use vacancy qualifications / qualification standards</label>
                    </div>

                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" class="custom-control-input" id="skillUseJobDescription" name="use_job_description" value="1"
                               {{ old('use_job_description',$skillTest->ai_use_job_description ?? true) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="skillUseJobDescription">Use vacancy job description / details</label>
                    </div>

                    <div class="form-group">
                        <label>Generation focus</label>
                        @php $skillFocus=old('generation_focus',$skillTest->ai_generation_focus ?: 'mixed'); @endphp
                        <select name="generation_focus" class="form-control">
                            <option value="mixed" {{ $skillFocus==='mixed'?'selected':'' }}>Mixed job-relevant performance task</option>
                            <option value="duties" {{ $skillFocus==='duties'?'selected':'' }}>Duties and responsibilities</option>
                            <option value="technical" {{ $skillFocus==='technical'?'selected':'' }}>Technical competencies</option>
                            <option value="situational" {{ $skillFocus==='situational'?'selected':'' }}>Situational judgment / work scenario</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Additional Context <span class="text-muted font-weight-normal">(optional)</span></label>
                        <textarea name="additional_context" rows="6" maxlength="30000" class="form-control"
                                  placeholder="Paste approved duties, procedures, competency statements, or reference material.">{{ old('additional_context',$skillTest->ai_context) }}</textarea>
                    </div>

                    <button class="btn btn-outline-primary" onclick="return confirm('Replace the current draft task and active rubric with a new AI-generated draft requiring review?');">
                        <i class="fas fa-magic"></i> Generate/Replace Task & Rubric with AI
                    </button>
                </form>
                @endunless
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><strong>Active Rubric</strong> <span class="float-right">{{ number_format($skillTest->rubricCriteria->sum('max_points'),2) }}/100</span></div>
            <div class="card-body">
                <form method="post" action="{{ route('admin.skills.rubric',$skillTest) }}">@csrf
                    @php $criteria=$skillTest->rubricCriteria; @endphp
                    @for($i=0;$i<max(5,$criteria->count());$i++)
                        @php $criterion=$criteria->get($i); @endphp
                        <div class="border rounded p-2 mb-2">
                            <div class="form-row">
                                <div class="col-md-7">
                                    <input name="criteria[{{ $i }}][criterion]" class="form-control" placeholder="Criterion" value="{{ optional($criterion)->criterion }}" {{ $locked?'disabled':'' }}>
                                </div>
                                <div class="col-md-5">
                                    <input type="number" step=".01" name="criteria[{{ $i }}][max_points]" class="form-control" placeholder="Points" value="{{ optional($criterion)->max_points }}" {{ $locked?'disabled':'' }}>
                                </div>
                            </div>
                            <textarea name="criteria[{{ $i }}][description]" class="form-control mt-2" placeholder="Description" {{ $locked?'disabled':'' }}>{{ optional($criterion)->description }}</textarea>

                            @if($criterion)
                                <div class="small mt-2">
                                    v{{ $criterion->criterion_version }} ·
                                    @if($criterion->review_status==='approved')<span class="text-success">Approved</span>
                                    @elseif($criterion->review_status==='rejected')<span class="text-danger">Rejected</span>
                                    @else<span class="text-warning">Pending Review</span>@endif
                                </div>
                            @endif
                        </div>
                    @endfor

                    @unless($locked)
                        <small class="text-muted">Saving creates a new active rubric version and preserves prior criteria for audit history. Total must equal 100.</small><br>
                        <button class="btn btn-primary mt-2">Save New Rubric Version</button>
                    @endunless
                </form>

                @unless($locked)
                @foreach($criteria as $criterion)
                    @if($criterion->review_status !== 'approved')
                    <form method="post" action="{{ route('admin.skills.rubric.review',[$skillTest,$criterion]) }}" class="border-top pt-2 mt-2">@csrf @method('put')
                        <strong>{{ $criterion->criterion }}</strong>
                        <div class="form-row mt-1">
                            <div class="col-md-4">
                                <select name="decision" class="form-control form-control-sm">
                                    <option value="approved">Approve</option>
                                    <option value="pending_review">Pending</option>
                                    <option value="rejected">Reject</option>
                                </select>
                            </div>
                            <div class="col-md-6"><input name="review_notes" class="form-control form-control-sm" placeholder="Review notes"></div>
                            <div class="col-md-2"><button class="btn btn-sm btn-primary btn-block">Save</button></div>
                        </div>
                    </form>
                    @endif
                @endforeach
                @endunless
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><strong>Score Visibility</strong></div>
            <div class="card-body">
                <p class="mb-2">Policy: <strong>{{ ucfirst(str_replace('_',' ',$skillTest->score_release_policy)) }}</strong></p>
                <p class="small text-muted">Manual release: {{ optional($skillTest->scores_released_at)->format('M d, Y h:i A') ?: 'Not released' }}</p>
                <form method="post" action="{{ route('admin.skills.release_scores',$skillTest) }}" class="d-inline">@csrf
                    <button class="btn btn-success btn-sm" {{ $skillTest->score_release_policy!=='manual'?'disabled':'' }}>Release Scores</button>
                </form>
                <form method="post" action="{{ route('admin.skills.hide_scores',$skillTest) }}" class="d-inline">@csrf
                    <button class="btn btn-outline-secondary btn-sm" {{ in_array($skillTest->score_release_policy,['immediate','after_close'],true)?'disabled':'' }}>Hide Scores</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><strong>Open Incidents</strong></div>
            <div class="card-body">
                @forelse($incidents as $incident)
                    <div class="border-bottom pb-2 mb-2">
                        <strong>{{ ucfirst($incident->type) }}</strong>
                        <div class="small">{{ $incident->notes }}</div>
                        <form method="post" action="{{ route('admin.skills.incidents.resolve',[$skillTest,$incident]) }}" class="mt-1">@csrf @method('put')
                            <button class="btn btn-xs btn-outline-success">Mark Resolved</button>
                        </form>
                    </div>
                @empty
                    <span class="text-muted">No open incidents.</span>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>Recent Governance Audit Trail</strong></div>
    <div class="card-body table-responsive p-0">
        <table class="table table-sm mb-0">
            <thead><tr><th>When</th><th>Action</th><th>User</th><th>Details</th></tr></thead>
            <tbody>
            @forelse($auditLogs as $log)
                <tr>
                    <td>{{ $log->created_at->format('M d, Y h:i A') }}</td>
                    <td>{{ str_replace('_',' ',$log->action) }}</td>
                    <td>{{ optional($log->user)->email ?: 'System' }}</td>
                    <td class="small">{{ $log->metadata ? json_encode($log->metadata) : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-muted">No governance entries yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@stop
