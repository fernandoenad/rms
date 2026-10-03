@extends('adminlte::page')
@section('title','Edit Assessment Group')
@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">{{ $assessmentGroup->title }}</h1>
        <small class="text-muted">Equivalent written-test sets · Blueprint v{{ $assessmentGroup->blueprint_version }}</small>
    </div>
    <div class="mt-2 mt-md-0">
        <a href="{{ route('admin.assessment_groups.results',$assessmentGroup) }}" class="btn btn-outline-secondary mr-2"><i class="fas fa-chart-bar"></i> Live Results</a>
        <a href="{{ route('admin.assessment_groups.analytics',$assessmentGroup) }}" class="btn btn-outline-info mr-2"><i class="fas fa-chart-line"></i> Analytics</a>
        <a href="{{ route('admin.assessment_groups.export',$assessmentGroup) }}" class="btn btn-outline-success"><i class="fas fa-file-csv"></i> Export CSV</a>
    </div>
</div>

@stop

@section('content')
@if(session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

@php
    $bp = $assessmentGroup->blueprint ?: [];
    $solo = $bp['solo_distribution'] ?? [];
    $difficulty = $bp['difficulty_distribution'] ?? [];
    $competencyText = collect($bp['competencies'] ?? [])->map(function($row){
        return ($row['name'] ?? '').(!empty($row['items']) ? ' | '.$row['items'] : '');
    })->filter()->implode("\n");
@endphp

<form method="post" action="{{ route('admin.assessment_groups.update',$assessmentGroup) }}">@csrf @method('put')
<div class="card">
    <div class="card-header"><strong>Assessment Structure & Governance</strong></div>
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

<div class="card">
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

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Equivalent Sets</strong>
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
            <thead><tr><th>Set</th><th>Schedule</th><th>Items</th><th>Attempts</th><th>Readiness</th><th>Status</th><th></th></tr></thead>
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
                    <td>{{ $exam->getStatus() }}</td>
                    <td class="text-nowrap">
                        <a href="{{ route('admin.assessments.edit',$exam) }}" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                        <a href="{{ route('admin.assessments.items.index',$exam) }}" class="btn btn-sm btn-info"><i class="fas fa-list"></i></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">No sets yet.</td></tr>
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
                <p class="small text-muted">Released at: {{ optional($assessmentGroup->scores_released_at)->format('M d, Y h:i A') ?: 'Not manually released' }}</p>
                <form method="post" action="{{ route('admin.assessment_groups.release_scores',$assessmentGroup) }}" class="d-inline">@csrf
                    <button class="btn btn-success btn-sm" {{ $assessmentGroup->score_release_policy==='hidden'?'disabled':'' }}>Release Scores</button>
                </form>
                <form method="post" action="{{ route('admin.assessment_groups.hide_scores',$assessmentGroup) }}" class="d-inline">@csrf
                    <button class="btn btn-outline-secondary btn-sm">Hide Scores</button>
                </form>
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
