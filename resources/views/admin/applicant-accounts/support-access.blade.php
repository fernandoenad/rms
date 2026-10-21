@extends('adminlte::page')
@section('title','Production Applicant Support Rights')
@section('content_header')
<div class="d-flex align-items-center justify-content-between">
  <h1>Production Applicant Support Rights</h1>
  <a class="btn btn-outline-secondary" href="{{route('admin.applicant_accounts.index')}}">Back to Applicant Management</a>
</div>
@stop
@section('content')
@if(session('status'))<div class="alert alert-success">{{session('status')}}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="alert alert-info">
  Only administrators listed in <code>RMS_SUPPORT_IMPERSONATION_ADMIN_IDS</code> may grant and revoke delegated access here.
  Production Support Mode must also be enabled separately, and each authorized user must have a verified, linked Google staff identity.
  All sessions remain read-only and limited to ten minutes.
</div>
<div class="card">
 <div class="card-header"><strong>Active HR Administrators</strong></div>
 <div class="table-responsive">
  <table class="table table-hover mb-0">
   <thead><tr><th>Account</th><th>Support right</th><th>Action and audit reason</th></tr></thead>
   <tbody>
   @forelse($users as $user)
    @php $bootstrap = $policy->isProvisioningAdmin($user); $delegated = in_array($user->id,$granted); @endphp
    <tr>
      <td>{{ $user->name }}<div class="small text-muted">{{ $user->email }} — ID {{ $user->id }}</div></td>
      <td>{{ $bootstrap ? 'Bootstrap owner (.env)' : ($delegated ? 'Delegated, active' : 'Not granted') }}</td>
      <td>
       @if($bootstrap)
       <span class="small text-muted">Change bootstrap rights through secure server configuration.</span>
       @else
       <form method="POST" action="{{ route($delegated ? 'admin.support_access.revoke' : 'admin.support_access.grant',$user) }}">
         @csrf
         <label class="sr-only" for="reason-{{ $user->id }}">Reason</label>
         <input class="form-control form-control-sm mb-1" id="reason-{{ $user->id }}"
                name="reason" required minlength="20" maxlength="1000"
                placeholder="Document authorization or revocation reason">
         <button class="btn btn-sm btn-{{ $delegated ? 'danger' : 'primary' }}" type="submit">
           {{ $delegated ? 'Revoke' : 'Grant read-only support' }}
         </button>
       </form>
       @endif
      </td>
    </tr>
   @empty
    <tr><td colspan="3">No active HR administrators.</td></tr>
   @endforelse
   </tbody>
  </table>
 </div>
 <div class="card-footer">{{ $users->links('pagination::simple-bootstrap-4') }}</div>
</div>
@stop