@extends('adminlte::page')
@section('title','Assessment Group Results')
@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">{{ $assessmentGroup->title }}</h1>
        <small class="text-muted">
            Combined results across equivalent sets · {{ optional($assessmentGroup->vacancy)->position_title }}
        </small>
    </div>
    <a href="{{ route('admin.assessment_groups.edit',$assessmentGroup) }}" class="btn btn-outline-secondary">Back to Group</a>
</div>
@stop

@section('content')
<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3>{{ number_format((int)$dashboard->attempted) }}</h3>
                <p>Attempted</p>
            </div>
            <div class="icon"><i class="fas fa-users"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3>{{ number_format((int)$dashboard->taking_now) }}</h3>
                <p>Taking Now</p>
            </div>
            <div class="icon"><i class="fas fa-user-clock"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3>{{ number_format((int)$dashboard->submitted) }}</h3>
                <p>Submitted</p>
            </div>
            <div class="icon"><i class="fas fa-check-circle"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-light">
            <div class="inner">
                <h3>{{ number_format((float)$dashboard->completion_rate,1) }}%</h3>
                <p>Completion Rate</p>
            </div>
            <div class="icon"><i class="fas fa-chart-pie"></i></div>
        </div>
    </div>
</div>

@if((int)$dashboard->awaiting_timeout_finalization > 0)
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle mr-1"></i>
    {{ number_format((int)$dashboard->awaiting_timeout_finalization) }} attempt(s) have passed their server expiry
    but have not yet been finalized. They will finalize when the attempt is next checked.
</div>
@endif

<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
    <div class="text-muted small">
        In-progress attempts appear immediately after the applicant starts; a submission is not required for the applicant to appear here.
    </div>
    <a href="{{ request()->fullUrl() }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-sync-alt"></i> Refresh
    </a>
</div>

<div class="card">
    <div class="card-body table-responsive p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Applicant</th>
                    <th>Application Code</th>
                    <th>Set</th>
                    <th>Status</th>
                    <th>Started</th>
                    <th>Time</th>
                    <th>Raw Score</th>
                    <th>Written Score</th>
                    <th>Submitted</th>
                </tr>
            </thead>
            <tbody>
            @forelse($attempts as $attempt)
                <tr>
                    <td>{{ optional($attempt->application)->getFullname() }}</td>
                    <td>{{ optional($attempt->application)->application_code }}</td>
                    <td>
                        <span class="badge badge-primary">
                            {{ optional($attempt->exam)->set_code ?: optional($attempt->exam)->title }}
                        </span>
                    </td>
                    <td>
                        @if((int)$attempt->status === 1)
                            @if($attempt->expires_at && now()->gte($attempt->expires_at))
                                <span class="badge badge-warning">Awaiting finalization</span>
                            @else
                                <span class="badge badge-info">In progress</span>
                            @endif
                        @else
                            <span class="badge badge-success">Submitted</span>
                        @endif
                    </td>
                    <td>{{ optional($attempt->started_at)->format('M d, Y h:i A') }}</td>
                    <td>
                        @if((int)$attempt->status === 1 && $attempt->expires_at)
                            @if(now()->lt($attempt->expires_at))
                                {{ now()->diffForHumans($attempt->expires_at, ['parts' => 2, 'short' => true]) }} left
                            @else
                                Expired
                            @endif
                        @elseif((int)$attempt->status === 2)
                            Completed
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        @if((int)$attempt->status === 2)
                            {{ $attempt->correct_answers ?? '-' }} / {{ $attempt->total_items ?? '-' }}
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        @if((int)$attempt->status === 2 && $attempt->percentage !== null)
                            <strong>{{ number_format((float)$attempt->percentage,2) }}%</strong>
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ optional($attempt->ended_at)->format('M d, Y h:i A') ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="9">No attempts yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($attempts->hasPages())
        <div class="card-footer">{{ $attempts->links() }}</div>
    @endif
</div>
@stop
