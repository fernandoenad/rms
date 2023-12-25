@extends('adminlte::page')

@section('title', 'RMS v1.0 | Applications')

@section('content_header')
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1 class="m-0">Applications</h1>
        </div>
        <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="/admin">Dashboard</a></li>
                <li class="breadcrumb-item active">Applications</li>
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
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">List</h3>
                        <a type="button" class="btn btn-sm btn-primary float-right" href="{{route('admin.applications.create')}}">
                            <i class="fas fa-plus"></i> New application
                        </a>
                    </div>
                    <div class="card-body">
                        <table id="applications" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Email</th>
                                    <th>Name</th>
                                    <th>Applied position</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if(sizeof($applications) > 0)
                                    @foreach($applications as $application)
                                        <tr>
                                            <td>
                                                <a href="{{route('admin.applications.show', $application->id)}}" title="View">
                                                    {{$application->application_code}}
                                                </a>
                                            </td>
                                            <td>{{$application->applicant_email}}</td>
                                            <td>{{$application->applicant_fullname}}</td>
                                            <td>{{$application->position_applied}}</td>
                                            <td>
                                                <a href="{{route('admin.applications.edit', ['application' => $application])}}" class="btn btn-sm btn-warning" title="Modify">
                                                    <span class="fas primary fa-fw fa-edit"></span>
                                                </a>
                                                <a href="{{route('admin.applications.delete', ['application' => $application])}}" class="btn btn-sm btn-danger" title="Delete">
                                                    <span class="fas fa-fw fa-trash"></span>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="5">0 applications found.</td>
                                    </tr>
                                @endif
                            </tbody>

                    </table>
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
            "responsive": true, "lengthChange": false, "autoWidth": false, "pageLength": 5,
            "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
            }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
        });
    </script>
@stop