@extends('adminlte::page')
@section('title','Assessment Score Integrity')

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">Assessment Score Integrity</h1>
        <small class="text-muted">Compare stored applicant assessment scores against authoritative RMS Assessment Center results.</small>
    </div>
    <a href="{{ route('admin.assessment_center.integrity', array_filter(['type'=>$type,'q'=>$search])) }}"
       class="btn btn-outline-secondary mt-2 mt-md-0">
        <i class="fas fa-sync-alt mr-1"></i> Recheck
    </a>
</div>
@stop

@section('content')
@include('admin.assessment_center._nav')

@if(session('status'))
<div class="alert alert-info py-2">{{ session('status') }}</div>
@endif

<div class="alert alert-light border">
    <strong>How this works:</strong>
    RMS recalculates the score that should be stored in the applicant's assessment record from the official Written or Skills result and the mapped recruitment criterion. Only mismatches are listed.
    <div class="small text-muted mt-1">
        The "Last recorded editor" is shown only when RMS can correlate the assessment update with an existing HR score-update audit entry. If HRMIS did not record the human editor in data RMS can read, the editor will appear as "Not captured".
    </div>
</div>

<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner"><h3>{{ number_format($summary['total']) }}</h3><p>Total anomalies</p></div>
            <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-primary">
            <div class="inner"><h3>{{ number_format($summary['written']) }}</h3><p>Written mismatches</p></div>
            <div class="icon"><i class="fas fa-file-alt"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner"><h3>{{ number_format($summary['skills']) }}</h3><p>Skills mismatches</p></div>
            <div class="icon"><i class="fas fa-tools"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-secondary">
            <div class="inner"><h3>{{ number_format($summary['modifier_captured']) }}</h3><p>Editor identified</p></div>
            <div class="icon"><i class="fas fa-user-edit"></i></div>
        </div>
    </div>
</div>

<div class="card card-outline card-warning">
    <div class="card-header">
        <strong><i class="fas fa-search mr-1"></i> Sameness Check</strong>
    </div>
    <div class="card-body">
        <form method="get" action="{{ route('admin.assessment_center.integrity') }}" class="row">
            <div class="col-md-3 mb-2">
                <label class="small">Assessment type</label>
                <select name="type" class="form-control">
                    <option value="">Written + Skills</option>
                    <option value="written" {{ $type==='written' ? 'selected' : '' }}>Written only</option>
                    <option value="skills" {{ $type==='skills' ? 'selected' : '' }}>Skills only</option>
                </select>
            </div>
            <div class="col-md-7 mb-2">
                <label class="small">Search</label>
                <input type="text" name="q" value="{{ $search }}" class="form-control"
                       placeholder="Application code, applicant, position, assessment, or criterion">
            </div>
            <div class="col-md-2 mb-2 d-flex align-items-end">
                <button class="btn btn-warning btn-block"><i class="fas fa-search mr-1"></i> Run Check</button>
            </div>
        </form>
    </div>

    <div class="card-body p-0 table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead>
                <tr>
                    <th>Applicant</th>
                    <th>Position</th>
                    <th>Source</th>
                    <th>Criterion</th>
                    <th class="text-right">Assessment</th>
                    <th class="text-right">RMS Authoritative</th>
                    <th class="text-right">Difference</th>
                    <th>Last recorded editor</th>
                    <th>Assessment updated</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>
                        <strong>{{ $row['application']->application_code }}</strong>
                        <div class="small text-muted">{{ $row['application']->getFullname() }}</div>
                    </td>
                    <td>{{ $row['vacancy']?->position_title ?: '—' }}</td>
                    <td>
                        <span class="badge {{ $row['type']==='written' ? 'badge-primary' : 'badge-info' }}">{{ $row['type_label'] }}</span>
                        <div class="small mt-1">{{ $row['source_title'] }}</div>
                        @if($row['set_code'])<div class="small text-muted">Set {{ $row['set_code'] }}</div>@endif
                    </td>
                    <td><strong>{{ $row['criterion'] }}</strong></td>
                    <td class="text-right">
                        @if($row['current'] === null)
                            <span class="badge badge-danger">Missing</span>
                        @else
                            {{ number_format($row['current'], 2) }}
                        @endif
                    </td>
                    <td class="text-right"><strong>{{ number_format($row['expected'], 2) }}</strong></td>
                    <td class="text-right">
                        @if($row['difference'] === null)
                            —
                        @elseif(abs($row['difference']) >= 0.01)
                            <span class="text-danger font-weight-bold">{{ $row['difference'] > 0 ? '+' : '' }}{{ number_format($row['difference'], 2) }}</span>
                        @else
                            {{ number_format($row['difference'], 2) }}
                        @endif
                    </td>
                    <td>
                        @if($row['modifier_captured'])
                            <strong>{{ $row['modifier'] }}</strong>
                            <div class="small text-muted">{{ optional($row['modifier_at'])->format('M d, Y h:i A') }}</div>
                        @else
                            <span class="text-muted">Not captured</span>
                        @endif
                    </td>
                    <td class="text-nowrap">{{ optional($row['assessment_updated_at'])->format('M d, Y h:i A') ?: '—' }}</td>
                    <td class="text-nowrap">
                        <form method="post" action="{{ route('admin.assessment_center.integrity.repair') }}"
                              onsubmit="return confirm('Force this applicant assessment criterion back to the authoritative RMS Assessment Center score? This action will be audit-logged.');">
                            @csrf
                            <input type="hidden" name="type" value="{{ $row['type'] }}">
                            <input type="hidden" name="source_id" value="{{ $row['source_id'] }}">
                            <button class="btn btn-xs btn-danger">
                                <i class="fas fa-shield-alt mr-1"></i> Fix Integrity Anomaly
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center py-4 text-success">
                        <i class="fas fa-check-circle mr-1"></i> No score-integrity mismatches found for the selected scope.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($rows->hasPages())
    <div class="card-footer">
        {{ $rows->links() }}
    </div>
    @endif
</div>

<div class="alert alert-warning small">
    <strong>Important:</strong> "Fix Integrity Anomaly" treats the RMS Assessment Center result as authoritative and rewrites only the mapped assessment criterion using the same score-scaling logic used by normal score synchronization. The action is recorded in the Assessment Center audit log.
</div>
@stop
