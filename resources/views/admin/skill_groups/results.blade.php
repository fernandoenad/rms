@extends('adminlte::page')

@section('title','Skills Test Group Results')

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">{{ $skillTestGroup->title }}</h1>
        <small class="text-muted">Live monitoring across all Skills Test sets · {{ optional($skillTestGroup->vacancy)->position_title }}</small>
    </div>
    <div class="mt-2 mt-md-0">
        <a href="{{ route('admin.skill_groups.export',$skillTestGroup) }}" class="btn btn-outline-success mr-2">
            <i class="fas fa-file-csv"></i> Export All Sets CSV
        </a>
        <a href="{{ route('admin.skill_groups.edit',$skillTestGroup) }}" class="btn btn-outline-secondary">Back to Group</a>
    </div>
</div>
@stop

@section('content')
@if(session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif

<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner"><h3>{{ number_format((int)$dashboard->attempted) }}</h3><p>Attempted</p></div>
            <div class="icon"><i class="fas fa-users"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner"><h3>{{ number_format((int)$dashboard->taking_now) }}</h3><p>Taking Now</p></div>
            <div class="icon"><i class="fas fa-user-clock"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner"><h3>{{ number_format((int)$dashboard->submitted) }}</h3><p>Submitted</p></div>
            <div class="icon"><i class="fas fa-check-circle"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-light">
            <div class="inner"><h3>{{ number_format((float)$dashboard->completion_rate,1) }}%</h3><p>Completion Rate</p></div>
            <div class="icon"><i class="fas fa-chart-pie"></i></div>
        </div>
    </div>
</div>

@if((int)$dashboard->awaiting_timeout > 0)
<div class="alert alert-warning">
    <i class="fas fa-clock mr-1"></i>
    {{ number_format((int)$dashboard->awaiting_timeout) }} expired attempt(s) are waiting for finalization.
</div>
@endif

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Per-Set Monitoring</strong>
        <div class="small text-muted">All equivalent Skills Test sets in one view.</div>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th>Set</th>
                    <th>Attempted</th>
                    <th>Taking</th>
                    <th>Submitted</th>
                    <th>Human Evaluated</th>
                    <th>Mean Final</th>
                    <th>Open Set Results</th>
                </tr>
            </thead>
            <tbody>
            @forelse($setSummary as $set)
                <tr>
                    <td><span class="badge badge-primary">Set {{ $set['set_code'] ?: $set['title'] }}</span></td>
                    <td>{{ number_format($set['attempted']) }}</td>
                    <td>{{ number_format($set['in_progress']) }}</td>
                    <td>{{ number_format($set['submitted']) }}</td>
                    <td>{{ number_format($set['evaluated']) }}</td>
                    <td>{{ $set['mean_score'] !== null ? number_format($set['mean_score'],2).'/100' : '-' }}</td>
                    <td>
                        <a href="{{ route('admin.skills.results',$set['id']) }}" class="btn btn-xs btn-outline-primary">
                            View Set
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-muted">No Skills Test sets configured.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="alert alert-light border">
    <strong>Group score status:</strong>
    @if($skillTestGroup->scoresAreReleased())
        <span class="badge badge-success">Official scores released</span>
    @else
        <span class="badge badge-secondary">Not yet released</span>
    @endif
    @if($dashboard->mean_final_score !== null)
        <span class="ml-2">Mean finalized human score: <strong>{{ number_format((float)$dashboard->mean_final_score,2) }}/100</strong></span>
    @endif
</div>

<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
    <div class="small text-muted">
        This table combines attempts from every set in the Skills Test group. Human Final remains the authoritative score.
    </div>
    <div>
        <label class="small mb-0 mr-2"><input type="checkbox" id="autoRefresh"> Auto-refresh every 60 seconds</label>
        <a href="{{ request()->fullUrl() }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-sync-alt"></i> Refresh</a>
    </div>
</div>

<div class="card">
    <div class="card-body table-responsive p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Applicant</th>
                    <th>Set</th>
                    <th>Status</th>
                    <th>Started</th>
                    <th>Submitted</th>
                    <th>AI Proposed</th>
                    <th>Human Final</th>
                    <th>Set Results</th>
                </tr>
            </thead>
            <tbody>
            @forelse($attempts as $attempt)
                @php $latestAi = $attempt->aiEvaluations->sortByDesc('id')->first(); @endphp
                <tr>
                    <td>
                        {{ optional($attempt->application)->getFullname() }}<br>
                        <small class="text-muted">{{ optional($attempt->application)->application_code }}</small>
                    </td>
                    <td><span class="badge badge-primary">{{ optional($attempt->skillTest)->set_code ?: optional($attempt->skillTest)->title }}</span></td>
                    <td>
                        @if((int)$attempt->status === 1)
                            @if($attempt->expires_at && now()->gte($attempt->expires_at))
                                <span class="badge badge-warning">Awaiting finalization</span>
                            @else
                                <span class="badge badge-info">In progress</span>
                            @endif
                        @elseif((int)$attempt->status === 2)
                            <span class="badge badge-success">Submitted</span>
                        @elseif((int)$attempt->status === 3)
                            <span class="badge badge-secondary">Voided</span>
                        @endif
                    </td>
                    <td>{{ optional($attempt->started_at)->format('M d, Y h:i A') ?: '-' }}</td>
                    <td>{{ optional($attempt->submitted_at)->format('M d, Y h:i A') ?: '-' }}</td>
                    <td>
                        @if($attempt->ai_proposed_score !== null)
                            {{ number_format((float)$attempt->ai_proposed_score,2) }}/100
                        @else
                            -
                        @endif
                        @if($latestAi)<div class="small text-muted">{{ ucfirst($latestAi->status) }}</div>@endif
                    </td>
                    <td>
                        @if($attempt->final_score !== null)
                            <strong>{{ number_format((float)$attempt->final_score,2) }}/100</strong>
                        @else
                            <span class="text-muted">Pending</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('admin.skills.results',$attempt->skill_test_id) }}" class="btn btn-sm btn-outline-primary">
                            Manage / Evaluate
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-muted">No attempts yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($attempts->hasPages())
        <div class="card-footer">{{ $attempts->links('pagination::bootstrap-4') }}</div>
    @endif
</div>
@stop

@section('js')
<script>
(function(){
    var checkbox=document.getElementById('autoRefresh');
    if(!checkbox) return;
    var timer=null;
    checkbox.addEventListener('change',function(){
        if(timer){ clearInterval(timer); timer=null; }
        if(checkbox.checked){
            timer=setInterval(function(){ window.location.reload(); },60000);
        }
    });
})();
</script>
@stop
