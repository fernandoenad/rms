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
<div class="alert alert-light border">
    Each applicant appears only for the set they actually completed. The percentage shown here is that applicant's official written-assessment score for this equivalent-set group.
</div>

<div class="card">
    <div class="card-body table-responsive p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Applicant</th>
                    <th>Application Code</th>
                    <th>Set</th>
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
                    <td>{{ $attempt->correct_answers ?? '-' }} / {{ $attempt->total_items ?? '-' }}</td>
                    <td>
                        @if($attempt->percentage !== null)
                            <strong>{{ number_format((float)$attempt->percentage,2) }}%</strong>
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ optional($attempt->ended_at)->format('M d, Y h:i A') }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No completed attempts yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($attempts->hasPages())
        <div class="card-footer">{{ $attempts->links() }}</div>
    @endif
</div>
@stop
