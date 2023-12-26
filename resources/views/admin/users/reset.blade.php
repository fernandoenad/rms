@extends('adminlte::page')

@section('title', 'RMS v1.0 | Confirm User Password Reset')

@section('content_header')
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1 class="m-0">Reset User Password</h1>
        </div>
        <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="{{route('admin.index')}}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{route('admin.users.index')}}">User management</a></li>
                <li class="breadcrumb-item active">Confirm password reset</li>
            </ol>
        </div>
    </div>
@stop

@section('content')
<div class="container-fluid">
        <div class="row">
            <div class="col-lg-6 col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Confirm action</h3>
                    </div>
                    <form method="post" action="{{route('admin.users.resetOk', ['user' => $user])}}">
                        @csrf 
                        @method('put')
                        <div class="card-body">
                            <div class="form-group">
                                <p>Selecting 'Yes' will reset the password to <strong>password123</strong>. Are you sure?</p>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">Yes</button>
                            <a href="{{route('admin.users.index')}}" class="btn btn-default float-right">No</a>
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