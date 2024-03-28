@extends('adminlte::page')

@php
    $title = "Vacancies";
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
                            <i class="fas fa-plus"></i> New vacancy
                        </a>
                    </div>
                    <div class="card-body">
                        <table id="applications" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Cycle</th>
                                    <th>Position title</th>
                                    <th>Office level</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if(sizeof($vacancies) > 0)
                                    @foreach($vacancies as $vacancy)
                                        <tr>
                                            <td>{{$vacancy->id}}</td>
                                            <td>{{$vacancy->cycle}}</td>
                                            <td>{{$vacancy->position_title}}</td>
                                            <td>{{$vacancy->getOffice()}}</td>
                                            <td>{{$vacancy->getStatus()}}</td>
                                            <td>
                                                <a href="{{ route('admin.vacancies.edit', $vacancy) }}" class="btn btn-sm btn-warning" title="Modify">
                                                    <span class="fas primary fa-fw fa-edit"></span>
                                                </a>
                                                <a href="{{ route('admin.vacancies.delete', $vacancy) }}" class="btn btn-sm btn-danger" title="Delete">
                                                    <span class="fas fa-fw fa-trash"></span>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="5">0 vacancies found.</td>
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
            "responsive": true, "lengthChange": false, "autoWidth": false, "pageLength": 5,
            "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
            }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
        });
    </script>
@stop