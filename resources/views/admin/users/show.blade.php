@extends('adminlte::page')

@section('title', 'RMS v1.0 | Show Application')

@section('content_header')
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1 class="m-0">Show Application</h1>
        </div>
        <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="{{route('admin.index')}}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{route('admin.applications.index')}}">Applications</a></li>
                <li class="breadcrumb-item active">{{$application->application_code}}</li>
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
                        <h3 class="card-title">Showing record ID# <strong>{{$application->id}}</strong></h3>
                    </div>
                    <div class="card-body">
                        <table id="applications" class="table table-hover">
                            <tbody>
                                <tr>
                                    <td width="20%">Application Code: </td>
                                    <td>{{$application->application_code}}</td>
                                </tr>
                                <tr>
                                    <td width="20%">Applicant Email Address: </td>
                                    <td>{{$application->applicant_email}}</td>
                                </tr>
                                <tr>
                                    <td width="20%">Applicant Fullname: </td>
                                    <td>{{$application->applicant_fullname}}</td>
                                </tr>
                                <tr>
                                    <td width="20%">Position Applied For: </td>
                                    <td>{{$application->position_applied}}</td>
                                </tr>
                                <tr>
                                    <td width="20%">Application Document: </td>
                                    <td>Click <a href="{{$application->pertinent_doc}} ">here</a></td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td>
                                        <a href="{{route('admin.applications.edit', ['application' => $application])}}" class="btn btn-sm btn-warning" title="Modify">
                                            <span class="fas primary fa-fw fa-edit"></span> Edit 
                                        </a>
                                        <a href="{{route('admin.applications.delete', ['application' => $application])}}" class="btn btn-sm btn-danger" title="Delete">
                                            <span class="fas fa-fw fa-trash"></span> Delete
                                        </a>
                                        <a href="{{route('admin.applications.index')}}" class="btn btn-sm btn-default float-right" title="Back">
                                            <span class="fas fa-fw fa-arrow-left"></span> Back
                                        </a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
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