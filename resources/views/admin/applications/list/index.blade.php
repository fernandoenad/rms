@extends('adminlte::page')

@php
    $title = "Applications";
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
                <li class="breadcrumb-item"><a href="{{route('admin.vacancies.index')}}">Positions</a></li>
                <li class="breadcrumb-item"><a href="{{route('admin.vacancies.active')}}">Active Positions</a></li>
                <li class="breadcrumb-item active">{{ $title }}</li>
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
                        <h3 class="card-title">List for {{ $vacancy->position_title }}</h3>
                        <a type="button" class="btn btn-sm btn-primary float-right disabled" href="{{route('admin.applications.create')}}">
                            <i class="fas fa-plus"></i> New application
                        </a>
                    </div>
                    <div class="card-body">
                        <a class="float-right" href="{{ route('admin.applications.vacancy.show.tagged', $vacancy) }}">
                            <i class="fas fa-search"></i> Tagged positions only
                        </a>
                        <br><br>
                        <div class="table-responsive">
                            <table id="applications" class="table table-bordered table-striped table-sm" style="width:100%">
                                <thead>
                                    <tr>
                                        <th width="15%">Code</th>
                                        <th>Email</th>
                                        <th width="20%">Name</th>
                                        <th>Station/Unit</th>
                                        <th width="10%">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
@stop

@section('footer')
    @include('layouts.footer')
@stop

@section('css')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css">
<style>
    .dt-buttons {
        margin-bottom: 10px;
    }
    .dt-buttons .btn {
        margin-right: 5px;
    }
</style>
@stop

@section('plugins.Datatables', true)

@section('js')
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap4.min.js"></script>

<script>
    $(function () {
        $("#applications").DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('admin.applications.vacancy.show', $vacancy) }}",
            columns: [
                { data: 'application_code_link', name: 'application_code' },
                { data: 'email', name: 'email' },
                { data: 'fullname', name: 'fullname' },
                { data: 'station_name_display', name: 'station_name_display' },
                { data: 'action', name: 'action', orderable: false, searchable: false }
            ],
            responsive: true,
            lengthChange: true,
            autoWidth: false,
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100, 500, 1000],
            order: [[0, 'desc']],
            dom: '<"row"<"col-md-6"B><"col-md-6"f>>rtip',
            buttons: [
                {
                    extend: 'excel',
                    className: 'btn btn-success btn-sm',
                    text: '<i class="fas fa-file-excel"></i> Excel',
                    exportOptions: { columns: [0, 1, 2, 3] }
                },
                {
                    extend: 'pdf',
                    className: 'btn btn-danger btn-sm',
                    text: '<i class="fas fa-file-pdf"></i> PDF',
                    exportOptions: { columns: [0, 1, 2, 3] }
                },
                {
                    extend: 'print',
                    className: 'btn btn-info btn-sm',
                    text: '<i class="fas fa-print"></i> Print',
                    exportOptions: { columns: [0, 1, 2, 3] }
                },
                {
                    extend: 'colvis',
                    className: 'btn btn-secondary btn-sm',
                    text: '<i class="fas fa-columns"></i> Columns'
                }
            ]
        });
    });
</script>
@stop
