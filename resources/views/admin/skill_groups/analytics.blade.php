@extends('adminlte::page')
@section('title','Skills Test Group Analytics')

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">{{ $skillTestGroup->title }} — Analytics</h1>
        <small class="text-muted">{{ optional($skillTestGroup->vacancy)->position_title }}</small>
    </div>
    <div class="mt-2 mt-md-0">
        <a href="{{ route('admin.skill_groups.edit',$skillTestGroup) }}" class="btn btn-outline-secondary mr-2"><i class="fas fa-arrow-left"></i> Back</a>
        <a href="{{ route('admin.skill_groups.results',$skillTestGroup) }}" class="btn btn-outline-secondary mr-2"><i class="fas fa-chart-bar"></i> Live Results</a>
        <a href="{{ route('admin.skill_groups.export',$skillTestGroup) }}" class="btn btn-outline-success"><i class="fas fa-file-csv"></i> Export CSV</a>
    </div>
</div>
@stop

@section('content')
@include('admin.assessment_center._nav')

<div class="alert alert-light border">
    <strong>Descriptive analytics:</strong> Statistics use recorded attempts and human-final evaluations.
    AI score differences are informational only. Sets may use different tasks and rubric maximums;
    compare raw score averages with care. Small samples do not establish set equivalence.
</div>

<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner"><h3>{{ number_format($summary['sets']) }}</h3><p>Skills Test Sets</p></div>
            <div class="icon"><i class="fas fa-layer-group"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-light">
            <div class="inner"><h3>{{ number_format($summary['attempted']) }}</h3><p>Attempts Started</p></div>
            <div class="icon"><i class="fas fa-user-check"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-primary">
            <div class="inner"><h3>{{ number_format($summary['submitted']) }}</h3><p>Submitted ({{ number_format($summary['completion_rate'],1) }}%)</p></div>
            <div class="icon"><i class="fas fa-file-alt"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner"><h3>{{ number_format($summary['evaluated']) }}</h3><p>Human-Evaluated</p></div>
            <div class="icon"><i class="fas fa-check-circle"></i></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>Per-Set Assessment Analytics</strong></div>
    <div class="card-body table-responsive p-0">
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th>Set</th>
                    <th>Started</th>
                    <th>Submitted</th>
                    <th>Human-Evaluated</th>
                    <th>Rubric Max</th>
                    <th>Mean Human Final</th>
                    <th>Lowest / Highest Final</th>
                    <th>Mean |AI − Human|</th>
                    <th>Set Results</th>
                </tr>
            </thead>
            <tbody>
                @forelse($setSummary as $set)
                    <tr>
                        <td><span class="badge badge-primary">{{ $set['set_code'] ?: $set['title'] }}</span></td>
                        <td>{{ number_format($set['attempted']) }}</td>
                        <td>{{ number_format($set['submitted']) }}</td>
                        <td>{{ number_format($set['evaluated']) }}</td>
                        <td>{{ $set['rubric_points'] > 0 ? number_format($set['rubric_points'],2) : '—' }}</td>
                        <td>{{ $set['mean_final'] !== null ? number_format($set['mean_final'],2) : '—' }}</td>
                        <td>
                            @if($set['min_final'] !== null && $set['max_final'] !== null)
                                {{ number_format($set['min_final'],2) }} / {{ number_format($set['max_final'],2) }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            {{ $set['mean_ai_gap'] !== null ? number_format($set['mean_ai_gap'],2) : '—' }}
                            @if($set['ai_pairs'] > 0)
                                <span class="text-muted small">(n={{ $set['ai_pairs'] }})</span>
                            @endif
                        </td>
                        <td><a href="{{ route('admin.skills.results',$set['id']) }}" class="btn btn-xs btn-outline-primary">View Set</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-muted p-3">No Skills Test sets have been configured yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@stop
