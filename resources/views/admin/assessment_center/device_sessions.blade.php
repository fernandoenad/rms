@extends('adminlte::page')
@section('title','Assessment Device Sessions')
@section('content_header')
<h1>Assessment Device Sessions</h1>
@stop
@section('content')
@include('admin.assessment_center._nav')
@if(session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="alert alert-warning">
  Written and Skills assessments permit one authenticated browser session per applicant.
  Applicants should <strong>sign out on the original device</strong> before opening another.
  Use this audited recovery action only when the original device is unavailable.
  Releasing a device lock does not reset exam timers, scores, or attempt eligibility.
</div>
<div class="card">
 <div class="card-header"><strong>Active applicant browser locks</strong></div>
 <div class="table-responsive">
 <table class="table table-hover">
  <thead><tr><th>Account</th><th>Active assessment locks</th><th>Last activity</th><th>HR recovery</th></tr></thead>
  <tbody>
   @forelse($users as $user)
   <tr>
    <td>{{ $user->name }}<div class="small text-muted">{{ $user->email }}</div></td>
    <td>{{ $user->active_attempts }}</td>
    <td>{{ $user->last_activity }}</td>
    <td>
     <form method="POST" action="{{route('admin.assessment_device_sessions.release',$user->id)}}">
       @csrf
       <label class="sr-only" for="release-reason-{{ $user->id }}">Reason</label>
       <input id="release-reason-{{ $user->id }}" class="form-control form-control-sm mb-2"
              required minlength="20" maxlength="1000" name="reason"
              placeholder="Document lost-device recovery and HR authorization">
       <button class="btn btn-outline-danger btn-sm" type="submit"
               onclick="return confirm('Release this applicant’s active device-session locks? The current device may lose access.')">
           Release device locks
       </button>
     </form>
    </td>
   </tr>
   @empty
   <tr><td colspan="4">No active assessment session locks.</td></tr>
   @endforelse
  </tbody>
 </table></div>
 <div class="card-footer">{{ $users->links('pagination::simple-bootstrap-4') }}</div>
</div>
@stop
