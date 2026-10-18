@extends('adminlte::page')
@section('title', 'HR Application Claims')
@section('content_header')
<h1>Historical Application Claim Review</h1>
@stop
@section('content')
<div class="container-fluid pb-4">
<p>Check the applicant's identity and evidence against existing recruitment records before approving any claim.</p>
<nav aria-label="Claim review status" class="mb-3">
    <div class="btn-group" role="group" aria-label="Claim status">
        <a class="btn btn-sm {{ $status === 'open' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('admin.applicant_claims.index', ['status' => 'open']) }}">Pending / Clarification</a>
        <a class="btn btn-sm {{ $status === 'approved' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('admin.applicant_claims.index', ['status' => 'approved']) }}">Approved history</a>
        <a class="btn btn-sm {{ $status === 'rejected' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('admin.applicant_claims.index', ['status' => 'rejected']) }}">Rejected history</a>
    </div>
</nav>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if($claims->isEmpty())
<div class="alert alert-info" role="status">No {{ $status === 'open' ? 'pending or clarification-requested' : $status }} application claims found.</div>
@endif
@foreach($claims as $claim)
<div class="card mb-3"><div class="card-body">
<h2 class="h5">{{ $claim->claimed_application_code }}</h2>
<p>Claimant: {{ trim(($claim->profile?->first_name ?? '').' '.($claim->profile?->last_name ?? '')) ?: 'Profile unavailable' }} (profile #{{ $claim->applicant_profile_id }}) • Previous email: {{ $claim->claimed_email }} • Status: {{ $claim->status }}</p>
<p>Application ID: {{ $claim->application_id }}</p>
@if($status !== 'open')
<p>Reviewed: {{ $claim->reviewed_at?->format('M j, Y g:i A') ?? 'Not recorded' }} · Reviewer user #{{ $claim->reviewed_by ?? 'Not recorded' }}</p>
<p><strong>Review notes:</strong> {{ $claim->review_notes ?: 'Not recorded' }}</p>
@else
@if((int) $claim->profile?->user_id === (int) auth()->id())
<div class="alert alert-warning">You cannot review your own claim. Ask another authorized administrator to review it.</div>
@else
<div class="row">
<div class="col-md-6">
<form method="POST" action="{{ route('admin.applicant_claims.approve', $claim) }}">@csrf
<label>Evidence reviewed and reason for approval</label>
<textarea class="form-control mb-2" name="review_notes" required minlength="10"></textarea>
<label class="d-block"><input type="checkbox" name="identity_verified" value="1" required> I reviewed independent identity evidence and confirmed the claimant is the original applicant.</label>
<button class="btn btn-success">Approve ownership</button>
</form></div>
<div class="col-md-6">
<form method="POST" action="{{ route('admin.applicant_claims.reject', $claim) }}">@csrf
<label>Reason for rejection</label>
<textarea class="form-control mb-2" name="review_notes" required minlength="10"></textarea>
<button class="btn btn-danger">Reject claim</button>
</form></div>
<div class="col-md-12 mt-3">
<form method="POST" action="{{ route('admin.applicant_claims.clarification', $claim) }}">@csrf
<label>Request additional identity evidence or clarification</label>
<textarea class="form-control mb-2" name="review_notes" required minlength="10" maxlength="2000" placeholder="Specify the additional documents or information HR requires."></textarea>
<button class="btn btn-outline-primary">Request clarification (no access granted)</button>
</form></div>
</div>
@endif
@endif
</div></div>
@endforeach
{{ $claims->links() }}
</div>
@stop
