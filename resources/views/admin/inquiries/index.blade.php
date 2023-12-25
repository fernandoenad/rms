@extends('adminlte::page')

@section('title', 'RMS v1.0 | Inquiries')

@section('content_header')
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1 class="m-0">Inquiries</h1>
        </div>
        <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="/admin">Dashboard</a></li>
                <li class="breadcrumb-item active">Inquiries</li>
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
                    </div>
                    <div class="card-body">
                        <table id="applications" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Code</th>
                                    <th>Sender</th>
                                    <th>Message snippet</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if(sizeof($inquiries) > 0)
                                    @foreach($inquiries as $inquiry)
                                        <tr>
                                            <td>{{$inquiry->id}}</td>
                                            <td>
                                                <a href="{{route('admin.applications.show', $inquiry->application)}}" title="View">
                                                    {{$inquiry->application->application_code}}
                                                </a>
                                            </td>
                                            <td>{{$inquiry->author}}</td>
                                            <td>{{substr($inquiry->message,0,20)}}...</td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="5">0 inquiries found.</td>
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