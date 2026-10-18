@extends('layouts.guest')
@section('title', 'HR Application Claims')
@section('main')
<div class="container py-4">
<h1 class="h3">Historical Application Claim Review</h1>
<p>Check the applicant's identity and evidence against existing recruitment records before approving any claim.</p>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@foreach($claims as $claim)
<div class="card mb-3"><div class="card-body">
<h2 class="h5">{{ $claim->claimed_application_code }}</h2>
<p>Claimant profile #{{ $claim->applicant_profile_id }} • Previous email: {{ $claim->claimed_email }} • Status: {{ $claim->status }}</p>
<p>Application ID: {{ $claim->application_id }}</p>
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
</div></div></div>
@endforeach
{{ $claims->links() }}
</div>
@endsection
