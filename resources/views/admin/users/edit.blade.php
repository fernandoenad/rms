@extends('adminlte::page')

@php
    $title = "Modify User";
    $app_name = config('app.name', '') . ' [Admin]';
@endphp 

@section('title', config('app.name', '') . ' | ' . $title)

@section('content_header')
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1 class="m-0">{{ $title }}</h1>
        </div>
        <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="{{route('admin.index')}}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{route('admin.users.index')}}">User management</a></li>
                <li class="breadcrumb-item active">{{ $title }}</li>
            </ol>
        </div>
    </div>
@stop

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Update for record ID# <strong>{{$user->id}}</strong></h3>
                    </div>
                    <form method="post" action="{{route('admin.users.update', ['user' => $user])}}">
                        @csrf
                        @method('put')
                        <div class="card-body">
                            <div class="form-group">
                                <label for="#">Fullname</label>
                                <input type="text" class="form-control" placeholder="Enter user fullname" 
                                    name="name" class="@error('name') is-invalid @enderror"
                                    value="{{ $user->name }}">
                                @error('name')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="#">Email</label>
                                <input type="text" class="form-control" placeholder="Enter email address" 
                                    name="email" class="@error('email') is-invalid @enderror"
                                    value="{{ $user->email }}">
                                @error('email')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="#">Role</label>
                                <select type="text" class="form-control" placeholder="Enter email address" 
                                    name="level" class="@error('level') is-invalid @enderror"
                                    value="{{ old('level') }}">
                                    <option value="2" {{ $user->role->level == 2 ? 'selected':''}}>Staff</option>
                                    <option value="1" {{ $user->role->level == 1 ? 'selected':''}}>Administrator</option>
                                </select>
                                @error('level')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="#">Status</label>
                                <select type="text" class="form-control" placeholder="Enter email address" 
                                    name="status" class="@error('status') is-invalid @enderror"
                                    value="{{ old('status') }}">
                                    <option value="0" {{ $user->role->status == 2 ? 'selected':''}}>Inactive</option>
                                    <option value="1" {{ $user->role->status == 1 ? 'selected':''}}>Active</option>
                                </select>
                                @error('level')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">Update</button>
                            <a href="{{url()->previous()}}" class="btn btn-default float-right">Cancel</a>
                        </div>
                    </form> 
                </div>
            </div>
        </div>
    </div>
@stop

@section('footer')
    @include('layouts.footer')
@stop

@section('css')
@stop

@section('plugins.Datatables', true)

@section('js')
    <script> console.log('Hi!'); </script>
    <script>
        $(function () {
            $("#applications").DataTable({
            "responsive": true, "lengthChange": false, "autoWidth": false,
            "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
            }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
        });
    </script>
@stop