@extends('adminlte::page')
@section('title','Assessment Analytics')
@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">{{ $assessmentGroup->title }} — Analytics</h1>
        <small class="text-muted">{{ optional($assessmentGroup->vacancy)->position_title }}</small>
    </div>
    <a href="{{ route('admin.assessment_groups.results',$assessmentGroup) }}" class="btn btn-outline-secondary">Back to Live Results</a>
</div>
@stop

@section('content')
<div class="alert alert-light border">
    These are descriptive post-assessment diagnostics. Difficulty is the proportion answering correctly.
    Discrimination is an upper-versus-lower performance-group difference and should be interpreted with adequate sample size.
</div>

<div class="card">
    <div class="card-header"><strong>Cross-Set Comparability</strong></div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Set</th><th>Attempted</th><th>Submitted</th><th>Mean %</th><th>SD</th></tr></thead>
            <tbody>
            @foreach($setSummary as $set)
                <tr>
                    <td><span class="badge badge-primary">{{ $set['set_code'] ?: $set['title'] }}</span></td>
                    <td>{{ number_format($set['attempted']) }}</td>
                    <td>{{ number_format($set['submitted']) }}</td>
                    <td>{{ $set['mean_score'] !== null ? number_format($set['mean_score'],2) : '-' }}</td>
                    <td>{{ $set['score_sd'] !== null ? number_format($set['score_sd'],2) : '-' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

@if($comparabilityWarnings)
<div class="alert alert-warning">
    <strong>Review suggested:</strong>
    <ul class="mb-0">@foreach($comparabilityWarnings as $warning)<li>{{ $warning }}</li>@endforeach</ul>
</div>
@endif

@foreach($assessmentGroup->exams as $exam)
<div class="card">
    <div class="card-header">
        <strong>Set {{ $exam->set_code }} — Item Analysis</strong>
        <span class="float-right text-muted small">{{ $exam->title }}</span>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-sm table-hover mb-0">
            <thead><tr><th>Item</th><th>Answered</th><th>Difficulty</th><th>Discrimination</th><th>Distractor Responses</th></tr></thead>
            <tbody>
            @forelse($itemAnalytics[$exam->id] ?? [] as $row)
                <tr>
                    <td style="min-width:280px">
                        <strong>#{{ $row['item']->id }}</strong> {{ Str::limit($row['item']->question,150) }}
                    </td>
                    <td>{{ $row['answered'] }}</td>
                    <td>
                        @if($row['difficulty'] !== null)
                            {{ number_format($row['difficulty'],3) }}
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        @if($row['discrimination'] !== null)
                            {{ number_format($row['discrimination'],3) }}
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        @forelse($row['distractors'] as $option)
                            <div class="small {{ $option->is_correct ? 'text-success font-weight-bold' : '' }}">
                                {{ Str::limit($option->option_text,70) }} — {{ $option->responses }}
                            </div>
                        @empty
                            <span class="text-muted">No responses</span>
                        @endforelse
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">No item-response data yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endforeach
@stop
