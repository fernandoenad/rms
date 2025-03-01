@extends('adminlte::page')

@php
    $title = $station->name;
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
                <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.vacancies.index') }}">Positions</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.vacancies.reports.index') }}">Reports</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.vacancies.reports.show', [$office, $station]) }}">{{$office->name}}</a></li>
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
                        <h3 class="card-title">List</h3>
                    </div>
                    <div class="card-body">
                        <table id="list" class="table table-sm table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th class="text-left">Application Code</th>
                                    <th width="25%">Applicants</th>
                                    <th class="text-left">Position Applied for</th>
                                    <th class="text-left">Status</th>
                                    <th class="text-right">Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <th colspan="6">{{$station->name}}</th>
                                </tr>
                                @forelse($applications as $application)
                                    <tr>
                                        <td class="text-left"><a href="{{route('admin.applications.show', $application)}}">{{$application->application_code}}</a></td>
                                        <td>{{$application->getFullname()}}</a></td>
                                        <td class="text-left">{{$application->vacancy->position_title}}</td>
                                        <td class="text-left">
                                            @if(isset($application->assessment))
                                                {{$application->assessment->get_status()}}
                                            @else 
                                                New
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if(isset($application->assessment))
                                                {{$application->assessment->score}}
                                            @else 
                                                Not available
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                @endforelse
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
            $("#list").DataTable({
                "responsive": true,
                "lengthChange": true,
                "autoWidth": false,
                "pageLength": 100,
                "lengthMenu": [5, 10, 25, 50, 100], // You can customize these values
                "ordering": false, // Disable initial sorting
                "dom": 'Blfrtip', // Ensure the buttons are displayed
                "buttons": ["excel", "pdf", "print", "colvis"]
            }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
        });
    </script>
@stop