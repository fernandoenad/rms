@extends('adminlte::page')

@php
    $title = "Positions";
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
                        <a type="button" class="btn btn-sm btn-primary float-right" href="{{ route('admin.vacancies.create') }}">
                            <i class="fas fa-plus"></i> New position
                        </a>
                    </div>
                    <div class="card-body">
                        <a class="" href="{{ route('admin.vacancies.reports.index') }}">
                            <i class="fas fa-list"></i> Show report
                        </a>
                        <!--
                        <a class="float-right" href="{{ route('admin.vacancies.active') }}">
                            <i class="fas fa-search"></i> Active positions only
                        </a>
                        -->
                        <br><br>
                        <table id="applications" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Cycle</th>
                                    <th>Position title</th>
                                    <th>Level</th>
                                    <th>Post</th>
                                    <th>SSC</th>
                                    <th>DSC</th>
                                    <th>Applications</th>
                                    <th width="15%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if(sizeof($vacancies) > 0)
                                    @foreach($vacancies as $vacancy)
                                        <tr>
                                            <td>{{$vacancy->id}}</td>
                                            <td>{{$vacancy->cycle}}</td>
                                            <td>
                                                <a href="{{ route('admin.applications.vacancy.show', $vacancy) }}">
                                                {{$vacancy->position_title}}
                                                </a>
                                            </td>
                                            <td>{{$vacancy->getOffice()}}</td>
                                            <td>{{$vacancy->getStatus()}}</td>
                                            <td>{{$vacancy->getLevel1Status()}}</td>
                                            <td>{{$vacancy->getLevel2Status()}}</td>
                                            <td class="text-right">{{$vacancy->applications->where('station_id','>',0)->count()}}/{{$vacancy->applications->count()}}</td>
                                            <td>
                                                <a href="{{ route('admin.vacancies.apply', $vacancy) }}" class="btn btn-sm btn-primary" 
                                                    target="_blank" title="Submit application"
                                                    onclick="return confirm('IMPORTANT: Only click OK if approved by the HRMPSB Chair!')">
                                                    <span class="fas primary fa-fw fa-inbox"></span>
                                                </a>
                                                <a href="{{ route('admin.vacancies.edit', $vacancy) }}" class="btn btn-sm btn-warning" title="Modify">
                                                    <span class="fas primary fa-fw fa-edit"></span>
                                                </a>
                                                @php 
                                                    $count_applications = App\Models\Application::where('vacancy_id', '=', $vacancy->id)->count();
                                                @endphp
                                                <a href="{{ route('admin.vacancies.delete', $vacancy) }}" class="btn btn-sm btn-danger {{ $count_applications > 0 ? 'disabled' : '' }}" title="Delete">
                                                    <span class="fas fa-fw fa-trash"></span>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="6">0 vacancies found.</td>
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
@stop

@section('plugins.Datatables', true)

@section('js')
    <script> console.log('Hi!'); </script>
    <script>
        $(function () {
            $("#applications").DataTable({
                "responsive": true,
                "lengthChange": true,
                "autoWidth": false,
                "pageLength": 10,
                "lengthMenu": [5, 10, 25, 50, 100, 1000, 2000, 3000, 4000, 5000], // You can customize these values
                "ordering": false, // Disable initial sorting
                "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
            }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
        });
    </script>
@stop