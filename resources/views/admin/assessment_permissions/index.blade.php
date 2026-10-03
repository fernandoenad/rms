@extends('adminlte::page')
@section('title','Assessment Access')
@section('content_header')
<div class="d-flex justify-content-between align-items-center">
    <div><h1 class="mb-0">Assessment Access</h1><small class="text-muted">Optional separation of authoring, review, monitoring, evaluation and release duties</small></div>
    <a href="{{ route('admin.assessment_center.index') }}" class="btn btn-outline-secondary">Assessment Center</a>
</div>
@stop
@section('content')
@if(session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif
<div class="alert alert-light border">
    Administrators (role level 1) always have all capabilities. For backward compatibility, a non-admin user with <strong>no explicit capability records</strong> keeps existing access. Once you save one or more capabilities for that user, only the selected Assessment Center capabilities are allowed.
</div>
<div class="card">
    <div class="card-body table-responsive p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>User</th><th>Role</th><th>Assessment Capabilities</th></tr></thead>
            <tbody>
            @foreach($users as $user)
                @php
                    $userGrants=collect($grants->get($user->id,collect()))->pluck('capability')->all();
                    $isAdmin=(int)optional($user->role)->level===1;
                @endphp
                <tr>
                    <td>{{ $user->name }}<br><small class="text-muted">{{ $user->email }}</small></td>
                    <td>{{ $isAdmin ? 'Administrator' : 'Level '.optional($user->role)->level }}</td>
                    <td>
                        @if($isAdmin)
                            <span class="badge badge-success">All capabilities</span>
                        @else
                        <form method="post" action="{{ route('admin.assessment_permissions.update',$user) }}">@csrf @method('put')
                            <div class="d-flex flex-wrap">
                            @foreach($capabilities as $key=>$label)
                                <label class="border rounded px-2 py-1 mr-2 mb-2">
                                    <input type="checkbox" name="capabilities[]" value="{{ $key }}" {{ in_array($key,$userGrants,true)?'checked':'' }}>
                                    <strong>{{ ucfirst($key) }}</strong><br><small>{{ $label }}</small>
                                </label>
                            @endforeach
                            </div>
                            <button class="btn btn-sm btn-primary">Save Capabilities</button>
                            @if(empty($userGrants))<span class="small text-muted ml-2">Legacy access currently applies.</span>@endif
                        </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@stop
