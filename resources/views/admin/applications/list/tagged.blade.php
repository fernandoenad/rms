@extends('adminlte::page')

@php
    $title = "Tagged Applications";
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
                <li class="breadcrumb-item"><a href="{{route('admin.applications.vacancy.show', $vacancy)}}">All</a></li>
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
                        <a type="button" class="btn btn-sm btn-primary float-right" href="{{route('admin.applications.create')}}">
                            <i class="fas fa-plus"></i> New application
                        </a>
                    </div>
                    <div class="card-body">
                        <a type="button" class="btn btn-sm btn-info float-right" target="_blank" href="{{route('admin.applications.vacancy.show.carview', $vacancy)}}">
                            <i class="fas fa-print"></i> CAR-RQA
                        </a>
                        <a type="button" class="btn btn-sm btn-info float-right mr-1" target="_blank" href="{{route('admin.applications.vacancy.show.carview2', $vacancy)}}">
                            <i class="fas fa-print"></i> CAR-RQA (Per District)
                        </a>
                        <a type="button" class="btn btn-sm btn-info float-right mr-1" target="_blank" href="{{route('admin.applications.vacancy.show.carview3', $vacancy)}}">
                            <i class="fas fa-print"></i> CAR-RQA (SPIMS)
                        </a>
                        <br><br>
                        
                        <table id="applications" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th width="15%">Code</th>
                                    <th width="20%">Name</th>
                                    <th>Station/Unit</th>
                                    <th>Status</th>
                                    <th width="10%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if(sizeof($applications) > 0)
                                    @foreach($applications as $application)
                                        <tr>
                                            <td>
                                                <a href="{{route('admin.applications.show', $application)}}" title="View">
                                                    {{$application->application_code}}
                                                </a>
                                            </td>
                                            <td>{{$application->getFullname()}}</td>
                                            @php  $station = App\Models\Station::find($application->station_id); @endphp
                                            <td>{{ isset($station) ? $station->name . ' (' . $station->code . ')' : ($application->station_id == 0 ? 'Division' : 'Untagged') }}</td>
                                            @php  $assessment = $application->assessment; @endphp
                                            <td><small>{{isset($assessment) ? $assessment->get_status() : 'Not assessed yet' }}</small></td>
                                            <td>
                                                <a href="{{route('admin.applications.edit', ['application' => $application])}}" class="btn btn-sm btn-warning" title="Modify">
                                                    <span class="fas primary fa-fw fa-edit"></span>
                                                </a>
                                                <a href="{{route('admin.applications.delete', ['application' => $application])}}" class="btn btn-sm btn-danger {{ $application->station_id != -1 ? 'disabled' : '' }}" title="Delete">
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
    @include('layouts.footer')
@stop

@section('css')
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/jquery.dataTables.min.css">
<!-- Buttons CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.1.1/css/buttons.dataTables.min.css">
@stop

@section('plugins.Datatables', true)

@section('js')
    <script> console.log('Hi!'); </script>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
    <!-- Buttons JS -->
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.flash.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.colVis.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>

    <script>
        $(function () {
            $("#applications").DataTable({
                "responsive": true,
                "lengthChange": true,
                "autoWidth": false,
                "pageLength": 10,
                "lengthMenu": [5, 10, 25, 50, 100, 1000, 2000, 3000, 4000, 5000], // You can customize these values
                "ordering": false, // Disable initial sorting
                "dom": 'Blfrtip', // Ensure the buttons are displayed
                "buttons": ["excel", "pdf", "print", "colvis"]
            }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
        });
    </script>


@stop