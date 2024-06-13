@extends('adminlte::page')

@php
    $title = "Active Positions";
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
                        <table  class="table table-sm table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>District</th>
                                    <th class="text-right">Untagged applications</th>
                                    <th class="text-right">Tagged applications</th>
                                    <th class="text-right">Pending (SRC)</th>
                                    <th class="text-right">Completed (SRC)</th>
                                    <th class="text-right">Pending (DRC)</th>
                                    <th class="text-right">Completed (DRC)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Summary</td>
                                    <td class="text-right">{{number_format( $applications->where('station_id', '=', -1)->count(), 0) }}</td>
                                    <td class="text-right">{{number_format( $applications->where('station_id', '!=', -1)->count(), 0) }}</td>
                                    <td class="text-right">{{number_format($src_p, 0) }}</td>
                                    <td class="text-right">{{number_format($src_c, 0) }}</td>
                                    <td class="text-right">{{number_format($src_c, 0) }}</td>
                                    <td class="text-right">{{number_format($drc_c, 0) }}</td>
                                </tr>
                                @forelse($offices as $office)
                                    @php 
                                        $stations = App\Models\Station::where('office_id', '=', $office->id)->pluck('id');
                                        $src_t = App\Models\Application::whereIn('station_id', $stations)->count();
                                        $src_p = App\Models\Application::join('assessments', 'assessments.application_id', '=', 'applications.id')
                                            ->whereIn('applications.station_id', $stations)
                                            ->where('assessments.status', '=', 1)
                                            ->distinct('applications.id') // Ensure distinct applications are counted
                                            ->count('applications.id');
                                        $src_c = App\Models\Application::join('assessments', 'assessments.application_id', '=', 'applications.id')
                                            ->whereIn('applications.station_id', $stations)
                                            ->where('assessments.status', '=', 2)
                                            ->distinct('applications.id') // Ensure distinct applications are counted
                                            ->count('applications.id');
                                        $drc_p = App\Models\Application::join('assessments', 'assessments.application_id', '=', 'applications.id')
                                            ->whereIn('applications.station_id', $stations)
                                            ->where('assessments.status', '=', 2)
                                            ->distinct('applications.id') // Ensure distinct applications are counted
                                            ->count('applications.id');
                                        $drc_c = App\Models\Application::join('assessments', 'assessments.application_id', '=', 'applications.id')
                                            ->whereIn('applications.station_id', $stations)
                                            ->where('assessments.status', '=', 3)
                                            ->distinct('applications.id') // Ensure distinct applications are counted
                                            ->count('applications.id');
                                    @endphp
                                    <tr>
                                        <td>{{$office->name}}</td>
                                        <td class="text-right">-</td>
                                        <td class="text-right">{{number_format($src_t, 0) }}</td>
                                        <td class="text-right">{{number_format($src_p, 0) }}</td>
                                        <td class="text-right">{{number_format($src_c, 0) }}</td>
                                        <td class="text-right">{{number_format($drc_p, 0) }}</td>
                                        <td class="text-right">{{number_format($drc_c, 0) }}</td>
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