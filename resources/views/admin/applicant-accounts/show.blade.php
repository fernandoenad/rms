@extends('adminlte::page')
@section('title','Applicant Account Details')
@section('content_header')
<div class="d-flex justify-content-between align-items-center">
 <div><h1 class="mb-0">Applicant Account Details</h1><small class="text-muted">Read-only ownership diagnostics and safe reconciliation</small></div>
 <a href="{{route('admin.applicant_accounts.index')}}" class="btn btn-outline-secondary">Back to Applicant Management</a>
</div>
@stop
@section('content')
@if(session('status'))<div class="alert alert-success">{{session('status')}}</div>@endif
@if($errors->any())<div class="alert alert-danger"><strong>No operation was completed.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="card card-outline card-primary">
 <div class="card-header"><strong>Account and verified identity</strong></div>
 <div class="card-body">
  <div><strong>Account:</strong> {{ $profile->user?->name }} (#{{ $profile->user_id }})</div>
  <div><strong>Registered email:</strong> {{ $profile->user?->email }}</div>
  <div><strong>Google verification:</strong> {{ $verifiedIdentity ? 'Verified Google identity matching registered email' : 'No matching verified Google identity found' }}</div>
  <div><strong>Linked application count:</strong> {{ $profile->applicationLinks->count() }}</div>
 </div>
</div>
<div class="card">
 <div class="card-header"><strong>Applications currently linked to this account</strong></div>
 <div class="table-responsive"><table class="table table-sm table-bordered mb-0">
  <thead><tr><th>Application code</th><th>Position</th><th>Stored application email</th><th>Ownership method</th></tr></thead>
  <tbody>
  @forelse($profile->applicationLinks as $link)
  <tr><td>{{ $link->application?->application_code }}</td><td>{{ $link->application?->vacancy?->position_title }}</td><td>{{ $link->application?->email }}</td><td>{{ $link->link_method }}</td></tr>
  @empty<tr><td colspan="4" class="text-muted">No linked applications.</td></tr>
  @endforelse
  </tbody>
 </table></div>
</div>
<div class="card">
 <div class="card-header"><strong>Historical applications matching the account email (up to 100)</strong></div>
 <div class="table-responsive"><table class="table table-sm table-bordered mb-0">
  <thead><tr><th>Application code</th><th>Stored email</th><th>Account ownership</th><th>Method</th></tr></thead>
  <tbody>
  @forelse($matchingApplications as $app)
  <tr>
   <td>{{ $app->application_code }}</td><td>{{ $app->email }}</td>
   <td>
    @if(!$app->linked_profile_id)<span class="badge badge-warning">Unlinked</span>
    @elseif((int)$app->linked_profile_id === (int)$profile->id)<span class="badge badge-success">This account</span>
    @else<span class="badge badge-danger">Linked to another account</span><div class="small">{{ $app->linked_account_email }}</div>@endif
   </td>
   <td>{{ $app->link_method ?? '—' }}</td>
  </tr>
  @empty<tr><td colspan="4" class="text-muted">No application emails match this account.</td></tr>
  @endforelse
  </tbody>
 </table></div>
 <div class="card-footer">
  <p class="small text-muted">Reconciliation can only attach matching, unowned records when a matching verified Google identity exists. It will never overwrite another account's ownership.</p>
  @if($verifiedIdentity)
  <form method="POST" action="{{route('admin.applicant_accounts.reconcile',$profile)}}">
   @csrf
   <label for="reconciliationReason">Reason for safe reconciliation (required, audited)</label>
   <textarea id="reconciliationReason" name="reason" rows="2" class="form-control mb-2" required minlength="20" maxlength="1000" placeholder="Reference HR verification or the account mismatch investigation">{{ old('reason') }}</textarea>
   <button class="btn btn-outline-primary" type="submit">Link Matching Unowned Applications</button>
  </form>
  @else
  <button class="btn btn-outline-secondary" disabled>Verified Google identity required for reconciliation</button>
  @endif
 </div>
</div>
<div class="card card-outline card-warning">
 <div class="card-header"><strong>Testing impersonation — read only</strong></div>
 <div class="card-body">
  <p>This mode is restricted to local/staging environments with explicit configuration. It permits viewing the applicant dashboard, application details, profile, and vacancies, but blocks submissions, exams, account changes, and other write actions. Every start/stop action is audited.</p>
  @if($impersonationEnabled && !$profile->user?->role)
  <form method="POST" action="{{route('admin.applicant_accounts.impersonation.start',$profile)}}">
    @csrf
    <label for="impersonateReason">Testing reason (required)</label>
    <textarea id="impersonateReason" class="form-control mb-2" name="reason" rows="2" required minlength="20" maxlength="1000" placeholder="State which applicant-facing behavior you need to verify"></textarea>
    <button class="btn btn-warning" type="submit">Enter Read-only Applicant View</button>
  </form>
  @elseif($profile->user?->role)
  <p class="text-muted mb-0">Staff accounts cannot be impersonated.</p>
  @else
  <p class="text-muted mb-0">This action is disabled. To test locally set APP_ENV=local and RMS_APPLICANT_IMPERSONATION_ENABLED=true, then clear the configuration cache.</p>
  @endif
 </div>
</div>
@stop
