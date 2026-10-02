@extends('adminlte::page')
@section('title','Skills Test Results')
@section('content_header')<h1>{{ $skillTest->title }} — Results</h1>@stop
@section('content')
@if(session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif
<div class="card"><div class="card-body table-responsive p-0"><table class="table table-hover">
<thead><tr><th>Applicant</th><th>Submitted</th><th>AI proposed</th><th>Human final</th><th>AI status</th><th>Finalize</th></tr></thead>
<tbody>
@forelse($attempts as $attempt)
@php $latest=$attempt->aiEvaluations->sortByDesc('id')->first(); @endphp
<tr>
<td>{{ optional($attempt->application)->application_code }}<br><small>{{ optional($attempt->application)->getFullname() }}</small></td>
<td>{{ $attempt->submitted_at }}</td>
<td>{{ $attempt->ai_proposed_score ?? '-' }}</td>
<td>{{ $attempt->final_score ?? '-' }}</td>
<td>{{ optional($latest)->status ?? 'Not queued' }}</td>
<td>
<form class="form-inline" method="post" action="{{ route('admin.skills.final_score',[$skillTest,$attempt]) }}">@csrf
<input type="number" step=".01" min="0" max="100" name="final_score" value="{{ $attempt->final_score ?? $attempt->ai_proposed_score }}" class="form-control form-control-sm mr-1" style="width:90px" required>
<button class="btn btn-sm btn-success">Finalize</button>
</form>
@if($latest && $latest->criterion_scores)
<details class="mt-2"><summary>AI rubric evidence</summary><pre class="small text-wrap">{{ json_encode($latest->criterion_scores, JSON_PRETTY_PRINT) }}</pre></details>
@endif
</td>
</tr>
@empty<tr><td colspan="6">No submitted skills tests yet.</td></tr>@endforelse
</tbody></table></div></div>
{{ $attempts->links('pagination::bootstrap-4') }}
@stop
