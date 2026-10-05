@extends('adminlte::page')
@section('title','Edit Assessment Group')
@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">{{ $assessmentGroup->title }}</h1>
        <div class="small text-muted mb-1">
            <a href="{{ route('admin.assessment_center.index') }}">Assessment Center</a>
            <span class="mx-1">›</span>
            <a href="{{ route('admin.assessment_center.index') }}#assessments">Assessments</a>
            <span class="mx-1">›</span>
            Written
            <span class="mx-1">›</span>
            {{ $assessmentGroup->title }}
        </div>
        <small class="text-muted">Equivalent written-test sets · Blueprint v{{ $assessmentGroup->blueprint_version }}</small>
    </div>
    <div class="mt-2 mt-md-0">
        <a href="{{ route('admin.assessment_center.index') }}" class="btn btn-outline-secondary mr-2"><i class="fas fa-arrow-left"></i> Back</a>
        <a href="{{ route('admin.assessment_groups.results',$assessmentGroup) }}" class="btn btn-outline-secondary mr-2"><i class="fas fa-chart-bar"></i> Live Results</a>
        <a href="{{ route('admin.assessment_groups.analytics',$assessmentGroup) }}" class="btn btn-outline-info mr-2"><i class="fas fa-chart-line"></i> Analytics</a>
        <a href="{{ route('admin.assessment_groups.export',$assessmentGroup) }}" class="btn btn-outline-success"><i class="fas fa-file-csv"></i> Export CSV</a>
    </div>
</div>

@stop

@section('content')
@include('admin.assessment_center._nav')

@php
    $nextSetIssue = collect($assessmentGroup->exams)->first(function ($set) use ($readiness) {
        return !($readiness[$set->id]['ready'] ?? false);
    });
    $nextSetIssueData = $nextSetIssue ? ($readiness[$nextSetIssue->id] ?? null) : null;
@endphp
@if($nextSetIssue && $nextSetIssueData)
<div class="alert alert-warning py-2 d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <strong>Next recommended action:</strong>
        Review Set {{ $nextSetIssue->set_code ?: '?' }}
        @if(!empty($nextSetIssueData['issues'][0])) — {{ $nextSetIssueData['issues'][0] }} @endif
    </div>
    <a href="{{ route('admin.assessments.edit',$nextSetIssue) }}" class="btn btn-sm btn-outline-warning mt-2 mt-md-0">Manage Set</a>
</div>
@endif

@if(session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="card card-outline card-primary mb-3">
    <div class="card-body py-3">
        <div class="d-flex justify-content-between align-items-start flex-wrap">
            <div class="mb-2">
                <div class="small text-uppercase text-muted font-weight-bold">Assessment workflow</div>
                <div class="mt-1">
                    @if($assessmentGroup->archived_at)
                        <span class="badge badge-secondary mr-1">Archived</span>
                    @elseif($assessmentGroup->is_paused)
                        <span class="badge badge-warning mr-1">Paused</span>
                    @elseif($assessmentGroup->status)
                        <span class="badge badge-success mr-1">Active</span>
                    @else
                        <span class="badge badge-secondary mr-1">Inactive</span>
                    @endif
                    <span class="badge badge-light border mr-1">{{ $assessmentGroup->exams->count() }} set(s)</span>
                    <span class="badge badge-light border">Blueprint v{{ $assessmentGroup->blueprint_version }}</span>
                </div>
            </div>
            <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Assessment workflow sections">
                <a href="#groupSettings" class="btn btn-outline-primary">1. Settings</a>
                <a href="#blueprint" class="btn btn-outline-primary">2. Blueprint</a>
                <a href="#aiGeneration" class="btn btn-outline-primary">3. Generate</a>
                <a href="#setManagement" class="btn btn-outline-primary">4. Review Sets</a>
                <a href="{{ route('admin.assessment_groups.results',$assessmentGroup) }}" class="btn btn-outline-primary">5. Monitor</a>
            </div>
        </div>
        <div class="small text-muted mt-2">
            Recommended flow: configure the group → confirm the shared blueprint → generate or add equivalent sets → review and publish each set → monitor attempts and release scores.
        </div>
    </div>
</div>

<div class="row mb-2 assessment-scope-guide">
    <div class="col-md-6 mb-2">
        <div class="card border-success mb-0">
            <div class="card-body py-2 px-3">
                <div class="small text-uppercase text-success font-weight-bold mb-1"><i class="fas fa-layer-group mr-1"></i> Shared across all sets</div>
                <div class="small text-muted">Position · title · score criterion · score release · blueprint/TOS · group governance</div>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-2">
        <div class="card border-primary mb-0">
            <div class="card-body py-2 px-3">
                <div class="small text-uppercase text-primary font-weight-bold mb-1"><i class="fas fa-clone mr-1"></i> Configured per set</div>
                <div class="small text-muted">Schedule · items · duration · review/readiness · publication · attempts</div>
            </div>
        </div>
    </div>
</div>

<div id="accommodations" class="card border-info mb-3">
    <div class="card-header py-2"><strong>Applicant Accommodation</strong></div>
    <div class="card-body py-3">
        <form method="post" action="{{ route('admin.assessment_groups.accommodations',$assessmentGroup) }}">@csrf
            <div class="form-row align-items-end">
                <div class="form-group col-md-3 mb-2"><label class="mb-1">Application code</label><input name="application_code" class="form-control" required></div>
                <div class="form-group col-md-2 mb-2"><label class="mb-1">Extra minutes</label><input type="number" min="0" max="240" name="extra_minutes" value="0" class="form-control" required></div>
                <div class="form-group col-md-2 mb-2"><label class="mb-1">Large text</label><select name="large_text" class="form-control"><option value="0">No</option><option value="1">Yes</option></select></div>
                <div class="form-group col-md-3 mb-2"><label class="mb-1">Approval note</label><input name="notes" class="form-control" placeholder="Approved accommodation note"></div>
                <div class="form-group col-md-2 mb-2">
                    <button class="btn btn-info btn-block text-nowrap"><i class="fas fa-save mr-1"></i> Save</button>
                </div>
            </div>
            <small class="text-muted">Applies the approved accommodation to all equivalent sets in this assessment group.</small>
        </form>
    </div>
</div>

<div id="operations" class="card card-outline {{ $assessmentGroup->is_paused ? 'card-warning' : 'card-secondary' }}">
    <div class="card-header"><strong>Operational Control</strong></div>
    <div class="card-body">
        @if($assessmentGroup->archived_at)
            <div class="alert alert-secondary mb-0">This assessment is archived and frozen.</div>
        @elseif($assessmentGroup->is_paused)
            <div class="alert alert-warning">New starts are paused. Existing in-progress attempts may continue.<br><small>{{ $assessmentGroup->pause_reason }}</small></div>
            <form method="post" action="{{ route('admin.assessment_groups.resume',$assessmentGroup) }}" class="d-inline">@csrf
                <button class="btn btn-success btn-sm">Resume New Starts</button>
            </form>
        @else
            <form method="post" action="{{ route('admin.assessment_groups.pause',$assessmentGroup) }}" class="form-inline mb-2">@csrf
                <input name="reason" class="form-control form-control-sm mr-2" style="min-width:320px" placeholder="Reason for pausing new starts" required>
                <button class="btn btn-warning btn-sm">Pause New Starts</button>
            </form>
            <div class="d-flex flex-wrap align-items-center mt-2 assessment-group-actions">
                @if($assessmentGroup->score_release_policy==='manual')
                    @if($assessmentGroup->scores_released_at)
                        <form method="post" action="{{ route('admin.assessment_groups.hide_scores',$assessmentGroup) }}" class="mr-2 mb-2">
                            @csrf
                            <button class="btn btn-sm btn-outline-warning">
                                <i class="fas fa-eye-slash mr-1"></i> Withdraw Manual Release
                            </button>
                        </form>
                    @else
                        <form method="post" action="{{ route('admin.assessment_groups.release_scores',$assessmentGroup) }}"
                              class="mr-2 mb-2"
                              onsubmit="return confirm('Release official Written Test scores for all equivalent sets in this group?');">
                            @csrf
                            <button class="btn btn-sm btn-success">
                                <i class="fas fa-check-circle mr-1"></i> Release Official Scores for All Sets
                            </button>
                        </form>
                    @endif
                @endif

                <form method="post" action="{{ route('admin.assessment_groups.archive',$assessmentGroup) }}"
                      class="mr-2 mb-2"
                      onsubmit="return confirm('Archive and freeze this written assessment? This is blocked while attempts are in progress.');">
                    @csrf
                    <button class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-archive mr-1"></i> Archive / Freeze
                    </button>
                </form>

                @if(auth()->user() && auth()->user()->role && (int)auth()->user()->role->level===1)
                <form method="post" action="{{ route('admin.assessment_groups.destroy',$assessmentGroup) }}"
                      class="mb-2"
                      onsubmit="return confirm('Permanently delete this Written Assessment Group and all of its sets? This is allowed only if no applicant has attempted or been locked to any set.');">
                    @csrf @method('delete')
                    <button class="btn btn-danger btn-sm">
                        <i class="fas fa-trash mr-1"></i> Delete Group
                    </button>
                </form>
                @endif
            </div>
        @endif
    </div>
</div>

@php
    $bp = $assessmentGroup->blueprint ?: [];
    $solo = $bp['solo_distribution'] ?? [];
    $difficulty = $bp['difficulty_distribution'] ?? [];
    $competencyText = collect($bp['competencies'] ?? [])->map(function($row){
        return ($row['name'] ?? '').(!empty($row['items']) ? ' | '.$row['items'] : '');
    })->filter()->implode("\n");
@endphp

<form method="post" action="{{ route('admin.assessment_groups.update',$assessmentGroup) }}">@csrf @method('put')
<div id="groupSettings" class="card">
    <div class="card-header"><strong>Shared Across All Sets · Structure & Governance</strong><div class="small text-muted">Changes here apply to the assessment group, not to an individual Set A/B/C.</div></div>
    <div class="card-body">
        <div class="form-group">
            <label>Position</label>
            <select name="vacancy_id" class="form-control" required disabled>
                @foreach($vacancies as $v)
                    <option value="{{ $v->id }}" {{ $assessmentGroup->vacancy_id==$v->id?'selected':'' }}>
                        {{ $v->position_title }} ({{ $v->cycle }})
                    </option>
                @endforeach
            </select>
            <input type="hidden" name="vacancy_id" value="{{ $assessmentGroup->vacancy_id }}">
        </div>

        <div class="form-row">
            <div class="form-group col-md-5">
                <label>Assessment title</label>
                <input name="title" value="{{ old('title',$assessmentGroup->title) }}" class="form-control" required>
            </div>
            <div class="form-group col-md-3">
                <label>Group code</label>
                <input name="code" value="{{ old('code',$assessmentGroup->code) }}" class="form-control">
            </div>
            <div class="form-group col-md-2">
                <label>Equivalent sets</label>
                <input type="number" min="1" max="26" name="expected_sets" value="{{ old('expected_sets',$assessmentGroup->expected_sets) }}" class="form-control" required>
            </div>
            <div class="form-group col-md-2">
                <label>Default duration</label>
                <input type="number" min="1" max="480" name="default_duration" value="{{ old('default_duration', optional($assessmentGroup->exams->first())->duration ?: 60) }}" class="form-control">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-md-6">
                <label>Applicant score criterion</label>
                <select name="assessment_score_key" class="form-control">
                    <option value="">Do not write this assessment into applicant scores</option>
                    @foreach($scoreCriteria as $key=>$max)
                        <option value="{{ $key }}" {{ old('assessment_score_key',$assessmentGroup->assessment_score_key)===$key?'selected':'' }}>
                            {{ $key }} ({{ number_format($max,2) }} pts)
                        </option>
                    @endforeach
                </select>
                <small class="text-muted">Official written percentages are scaled to the selected recruitment-template criterion.</small>
                @if($assessmentGroup->scores_synced_at)
                    <div class="small text-success mt-1">Last applicant-score sync: {{ $assessmentGroup->scores_synced_at->format('M d, Y h:i A') }}</div>
                @endif
            </div>
            <div class="form-group col-md-6">
                <div class="alert alert-light border small mt-4 mb-0">
                    This updates only the selected criterion and recalculates the applicant's assessment total. Other criteria are preserved.
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-md-6">
                <label>Score release policy</label>
                <select name="score_release_policy" class="form-control" required>
                    @foreach([
                        'manual'=>'Manual release after validation',
                        'after_close'=>'After all set schedules close',
                        'immediate'=>'Immediately after submission',
                        'hidden'=>'Always hidden'
                    ] as $value=>$label)
                        <option value="{{ $value }}" {{ old('score_release_policy',$assessmentGroup->score_release_policy)===$value?'selected':'' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-6">
                <label>Group status</label>
                <select name="status" class="form-control">
                    <option value="1" {{ old('status',(string)(int)$assessmentGroup->status)==='1'?'selected':'' }}>Active</option>
                    <option value="0" {{ old('status',(string)(int)$assessmentGroup->status)==='0'?'selected':'' }}>Inactive</option>
                </select>
            </div>
        </div>
    </div>
</div>

<div id="blueprint" class="card">
    <div class="card-header"><strong>Shared Blueprint / TOS</strong></div>
    <div class="card-body">
        <div class="form-group col-md-3 pl-0">
            <label>Items per set</label>
            <input type="number" min="1" max="300" name="blueprint_item_count" value="{{ old('blueprint_item_count',$bp['item_count'] ?? '') }}" class="form-control">
        </div>

        <label>SOLO Distribution (%)</label>
        <div class="form-row">
            <div class="form-group col-md-3"><input type="number" min="0" max="100" name="solo_unistructural" value="{{ old('solo_unistructural',$solo['unistructural'] ?? 10) }}" class="form-control"></div>
            <div class="form-group col-md-3"><input type="number" min="0" max="100" name="solo_multistructural" value="{{ old('solo_multistructural',$solo['multistructural'] ?? 20) }}" class="form-control"></div>
            <div class="form-group col-md-3"><input type="number" min="0" max="100" name="solo_relational" value="{{ old('solo_relational',$solo['relational'] ?? 45) }}" class="form-control"></div>
            <div class="form-group col-md-3"><input type="number" min="0" max="100" name="solo_extended_abstract" value="{{ old('solo_extended_abstract',$solo['extended_abstract'] ?? 25) }}" class="form-control"></div>
        </div>

        <label>Difficulty Distribution (%)</label>
        <div class="form-row">
            <div class="form-group col-md-4"><input type="number" min="0" max="100" name="difficulty_easy" value="{{ old('difficulty_easy',$difficulty['easy'] ?? 20) }}" class="form-control"></div>
            <div class="form-group col-md-4"><input type="number" min="0" max="100" name="difficulty_moderate" value="{{ old('difficulty_moderate',$difficulty['moderate'] ?? 60) }}" class="form-control"></div>
            <div class="form-group col-md-4"><input type="number" min="0" max="100" name="difficulty_difficult" value="{{ old('difficulty_difficult',$difficulty['difficult'] ?? 20) }}" class="form-control"></div>
        </div>

        <div class="form-group">
            <label>Competencies / constructs</label>
            <textarea name="blueprint_competencies" rows="5" class="form-control"
                      placeholder="Records Management | 10">{{ old('blueprint_competencies',$competencyText) }}</textarea>
        </div>
    </div>
    <div class="card-footer"><button class="btn btn-primary">Save Assessment Group & Blueprint</button></div>
</div>
</form>

<div id="aiGeneration" class="card card-outline card-info">
    <div class="card-header"><strong><i class="fas fa-magic mr-1"></i> Generate All Empty Sets with AI</strong></div>
    <div class="card-body">
        <p class="small text-muted">
            One click generates the full item set for every empty draft Set A/B/C/etc. using this group's shared blueprint.
            RMS serializes equivalent-set generation so later sets are told to avoid questions already generated for earlier sets.
            Generated items remain <strong>Pending Review</strong>; nothing is automatically approved or published.
        </p>
        <form method="post" action="{{ route('admin.assessment_groups.generate_all_sets',$assessmentGroup) }}"
              onsubmit="return confirm('Queue AI generation for every empty draft set in this Written Assessment Group?');">
            @csrf
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Generation focus</label>
                    <select name="generation_focus" class="form-control">
                        <option value="mixed">Mixed job-relevant</option>
                        <option value="duties">Duties and responsibilities</option>
                        <option value="technical">Technical competencies</option>
                        <option value="situational">Situational / work scenario</option>
                    </select>
                </div>
                <div class="form-group col-md-8">
                    <label>Additional context <span class="text-muted font-weight-normal">(optional)</span></label>
                    <textarea name="additional_context" rows="3" class="form-control" maxlength="30000"></textarea>
                </div>
            </div>
            <input type="hidden" name="use_qualifications" value="0">
            <input type="hidden" name="use_job_description" value="0">
            <label class="mr-3"><input type="checkbox" name="use_qualifications" value="1" checked> Use qualifications</label>
            <label class="mr-3"><input type="checkbox" name="use_job_description" value="1" checked> Use job description</label>
            <button class="btn btn-info"><i class="fas fa-magic mr-1"></i> Generate All Empty Sets</button>
        </form>
    </div>
</div>

<div id="setManagement" class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Set Workspaces · Configure Each Set</strong>
        <form method="post" action="{{ route('admin.assessment_groups.equivalent_set',$assessmentGroup) }}" class="form-inline">@csrf
            <select name="source_exam_id" class="form-control form-control-sm mr-2">
                <option value="">Blank settings</option>
                @foreach($assessmentGroup->exams as $source)
                    <option value="{{ $source->id }}">Copy settings/context from Set {{ $source->set_code }}</option>
                @endforeach
            </select>
            <button class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Add Equivalent Set</button>
        </form>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Set</th><th>Schedule</th><th>Items</th><th>Attempts</th><th>Approval</th><th>Readiness</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($assessmentGroup->exams as $exam)
                @php $ready = $readiness[$exam->id] ?? ['ready'=>false,'issues'=>[],'item_count'=>0]; @endphp
                <tr>
                    <td><span class="badge badge-primary">Set {{ $exam->set_code ?: '?' }}</span><br><small>{{ $exam->title }}</small></td>
                    <td>
                        @if($exam->start_date)
                            {{ $exam->start_date->format('M d, Y h:i A') }}<br><small>to {{ optional($exam->end_date)->format('M d, Y h:i A') }}</small>
                        @else
                            <span class="text-muted">Not scheduled</span>
                        @endif
                    </td>
                    <td>{{ $ready['item_count'] }}</td>
                    <td>{{ $exam->attempts_count }}</td>
                    <td>
                        @if($exam->approval_status==='approved')
                            <span class="badge badge-success">Approved</span>
                        @else
                            <span class="badge badge-warning">Pending</span>
                            @if(auth()->user() && auth()->user()->role && (int)auth()->user()->role->level===1)
                                <form method="post" action="{{ route('admin.assessments.approve',$exam) }}" class="mt-1">@csrf
                                    <button class="btn btn-xs btn-outline-success">Approve</button>
                                </form>
                            @endif
                        @endif
                    </td>
                    <td>
                        @if($ready['ready'])
                            <span class="badge badge-success">Ready</span>
                        @else
                            <span class="badge badge-warning">Needs review</span>
                            <details class="small mt-1">
                                <summary>{{ count($ready['issues']) }} issue(s)</summary>
                                <ul class="pl-3 mb-0">@foreach(array_slice($ready['issues'],0,8) as $issue)<li>{{ $issue }}</li>@endforeach</ul>
                            </details>
                        @endif
                    </td>
                    <td>
                        @php $setState=$exam->getStatus(); @endphp
                        <span class="badge badge-{{ $setState==='Open' ? 'success' : ($setState==='Scheduled' ? 'info' : ($setState==='Draft' ? 'secondary' : 'light border')) }}">{{ $setState }}</span>
                    </td>
                    <td class="text-nowrap">
                        <a href="{{ route('admin.assessments.edit',$exam) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-cog mr-1"></i> Manage Set</a>
                        <a href="{{ route('admin.assessments.items.index',$exam) }}" class="btn btn-sm btn-outline-info"><i class="fas fa-list mr-1"></i> Items</a>
                        @if(auth()->user() && auth()->user()->role && (int)auth()->user()->role->level===1 && $exam->attempts_count===0)
                        <form method="post" action="{{ route('admin.assessments.destroy',$exam) }}" class="d-inline"
                              onsubmit="return confirm('Delete Set {{ $exam->set_code }} permanently? No applicant attempt may exist.');">
                            @csrf @method('delete')
                            <button class="btn btn-sm btn-outline-danger" title="Delete unattempted set"><i class="fas fa-trash"></i></button>
                        </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8">No sets yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><strong>Score Visibility</strong></div>
            <div class="card-body">
                <p class="mb-2">Policy: <strong>{{ ucfirst(str_replace('_',' ',$assessmentGroup->score_release_policy)) }}</strong></p>
                @if($assessmentGroup->score_release_policy==='manual')
                    @if($assessmentGroup->scores_released_at)
                        <p class="mb-1"><span class="badge badge-success">Released to applicants</span></p>
                        <p class="small text-muted mb-0">Released at: {{ $assessmentGroup->scores_released_at->format('M d, Y h:i A') }}</p>
                    @else
                        <p class="mb-1"><span class="badge badge-secondary">Not yet released</span></p>
                        <p class="small text-muted mb-0">Use <strong>Release Official Scores for All Sets</strong> under Operational Control above.</p>
                    @endif
                @elseif($assessmentGroup->score_release_policy==='immediate')
                    <p class="mb-0"><span class="badge badge-info">Automatic</span> <span class="small text-muted">Scores become visible immediately after submission.</span></p>
                @elseif($assessmentGroup->score_release_policy==='after_close')
                    <p class="mb-0"><span class="badge badge-info">Automatic</span> <span class="small text-muted">Scores become visible after all set schedules close.</span></p>
                @else
                    <p class="mb-0"><span class="badge badge-secondary">Hidden</span> <span class="small text-muted">Scores remain hidden from applicants.</span></p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><strong>Open Incidents</strong></div>
            <div class="card-body">
                @forelse($assessmentGroup->incidents as $incident)
                    <div class="border-bottom pb-2 mb-2">
                        <strong>{{ ucfirst($incident->type) }}</strong>
                        <div class="small">{{ $incident->notes }}</div>
                        <form method="post" action="{{ route('admin.assessment_groups.incidents.resolve',[$assessmentGroup,$incident]) }}" class="mt-1">@csrf @method('put')
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
            @forelse($assessmentGroup->auditLogs as $log)
                <tr>
                    <td>{{ $log->created_at->format('M d, Y h:i A') }}</td>
                    <td>{{ str_replace('_',' ',$log->action) }}</td>
                    <td>{{ optional($log->user)->email ?: 'System' }}</td>
                    <td class="small">{{ $log->metadata ? json_encode($log->metadata) : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-muted">No governance audit entries yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@stop

@section('css')
<style>
.assessment-group-actions .btn { min-height: 34px; }
@media (max-width: 575.98px) {
    .assessment-group-actions { display:block !important; }
    .assessment-group-actions form { margin-right:0 !important; }
    .assessment-group-actions .btn { width:100%; }
}
</style>
@stop
