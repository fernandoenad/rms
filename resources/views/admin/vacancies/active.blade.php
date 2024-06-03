@extends('adminlte::page')

@php
    $title = "Active Vacancies";
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
                <li class="breadcrumb-item"><a href="{{ route('admin.vacancies.index') }}">Vacancies</a></li>
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
                                    <th>ID</th>
                                    <th>Cycle</th>
                                    <th>Position title</th>
                                    <th>Untagged applications</th>
                                    <th>Tagged applications</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $untagged = 0; $tagged = 0; @endphp
                                @if(sizeof($vacancies) > 0)
                                    @foreach($vacancies as $vacancy)
                                        @php 
                                            $untagged += $vacancy->applications()->where('station_id', '=', -1)->get()->count();
                                            $tagged += $vacancy->applications()->where('station_id', '!=', -1)->get()->count();
                                        @endphp
                                        <tr>
                                            <td>{{$vacancy->id}}</td>
                                            <td>{{$vacancy->cycle}}</td>
                                            <td>{{$vacancy->position_title}}</td>
                                            <td>{{ $vacancy->applications()->where('station_id', '=', -1)->get()->count() }}</td>
                                            <td>{{ $vacancy->applications()->where('station_id', '!=', -1)->get()->count() }}</td>
                                        </tr>
                                    @endforeach
                                    <tr>
                                        <td></td>
                                        <td></td>
                                        <th class="text-right">Subtotal</th>
                                        <th>{{ $untagged }}</th>
                                        <th>{{ $tagged }}</th>
                                    </tr>
                                    <tr>
                                        <td></td>
                                        <td></td>
                                        <th class="text-right">Total</th>
                                        <th colspan="2" class="text-center">{{ $untagged + $tagged }}</th>
                                    </tr>
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
            "responsive": true, "lengthChange": false, "autoWidth": false, "pageLength": 5,
            "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
            }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
        });
    </script>
@stop