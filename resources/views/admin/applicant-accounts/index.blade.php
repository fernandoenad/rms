@extends('adminlte::page')
@section('title','Applicant Management')
@section('content_header')
<div class="d-flex justify-content-between align-items-center">
  <div><h1 class="mb-0">Applicant Management</h1>
  <small class="text-muted">Account profiles, historical application ownership, and test access</small></div>
</div>
@stop
@section('content')
@if(session('status'))<div class="alert alert-success">{{session('status')}}</div>@endif
@if(auth()->user() && app(\App\Support\ApplicantImpersonationPolicy::class)->isProvisioningAdmin(auth()->user()))
<div class="mb-3"><a class="btn btn-outline-primary" href="{{ route('admin.support_access.index') }}">Manage Support Impersonation Rights</a></div>
@endif
<div class="alert alert-info">
  An application's email address and its account ownership are separate records.
  Changing the application email does not change an existing ownership link.
  Conflicting ownership must be independently verified; this page cannot transfer it automatically.
</div>
<div class="card card-outline card-primary">
 <div class="card-header"><strong>Find an applicant account</strong></div>
 <div class="card-body">
  <form action="{{route('admin.applicant_accounts.index')}}" method="GET" class="form-row align-items-end">
   <div class="col-md-10 mb-2">
    <label for="applicantSearch">Search by name, account email, or application code</label>
    <input id="applicantSearch" name="search" class="form-control" value="{{ $search }}" maxlength="255" placeholder="Search applicant accounts">
   </div>
   <div class="col-md-2 mb-2"><button class="btn btn-primary btn-block" type="submit">Search</button></div>
  </form>
  <p class="small text-muted mb-0">{{ $impersonationEnabled ? 'Read-only test impersonation enabled for this non-production environment.' : 'Impersonation disabled. Enable explicitly in local/staging only.' }}</p>
 </div>
 <div class="table-responsive">
  <table class="table table-hover mb-0">
   <thead><tr><th>Applicant account</th><th>Email</th><th>Linked applications</th><th>Account</th><th>Details</th></tr></thead>
   <tbody>
   @forelse($profiles as $profile)
   <tr>
     <td>{{ $profile->first_name }} {{ $profile->last_name }} <div class="small text-muted">{{ $profile->user?->name }}</div></td>
     <td>{{ $profile->user?->email }}</td>
     <td>{{ $profile->application_links_count }}</td>
     <td>#{{ $profile->user_id }}</td>
     <td><a class="btn btn-outline-primary btn-sm" href="{{ route('admin.applicant_accounts.show', $profile) }}">View Ownership</a></td>
   </tr>
   @empty<tr><td colspan="5" class="text-center text-muted py-4">No applicant accounts matched.</td></tr>
   @endforelse
   </tbody>
  </table>
 </div>
 <div class="card-footer">{{ $profiles->links() }}</div>
</div>
@stop
