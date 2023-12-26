@extends('adminlte::page')

@section('title', 'RMS v1.0 | Change Password')

@section('content_header')
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1 class="m-0">Change Password</h1>
        </div>
        <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="{{route('admin.index')}}">Dashboard</a></li>
                <li class="breadcrumb-item active">Change password</li>
            </ol>
        </div>
    </div>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible auto-close">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            {{ session('status') }}
        </div>
    @endif
<div class="container-fluid">
        <div class="row">
            <div class="col-lg-6 col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Fill out the form below</h3>
                    </div>
                    <form method="post" action="{{route('admin.change_password_ok')}}">
                        @csrf 
                        @method('put')
                        <div class="card-body">
                            <div class="form-group">
                                <label for="#">Current password</label>
                                <input type="password" class="form-control" placeholder="Enter current password" 
                                    name="current_password" class="@error('current_password') is-invalid @enderror"
                                    value="{{ old('current_password') }}">
                                @error('current_password')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="#">New password</label>
                                <input type="password" class="form-control" placeholder="Enter new password" 
                                    name="password" class="@error('password') is-invalid @enderror"
                                    value="{{ old('password') }}">
                                @error('password')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="#">Confirm new password</label>
                                <input type="password" class="form-control" placeholder="Re-enter new password" 
                                    name="password_confirmation" class="@error('password_confirmation') is-invalid @enderror"
                                    value="{{ old('password_confirmation') }}">
                                @error('password2')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">Change password</button>
                            <a href="{{route('admin.index')}}" class="btn btn-default float-right">Cancel</a>
                        </div>
                    </form> 
                </div>
            </div>
        </div>
    </div>
@stop

@section('footer')
    <div class="float-right d-none d-sm-inline">
        Developed by Dr. Fernando B. Enad
    </div>
    <strong>Copyright &copy; 2023 <a href="/">{{ config('app.name', '') }}</a>.</strong> All rights reserved.
@stop


@section('css')
    <link rel="stylesheet" href="/css/admin_custom.css">
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