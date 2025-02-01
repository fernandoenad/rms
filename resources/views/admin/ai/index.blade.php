@extends('adminlte::page')

@php
    $title = "AI Tool";
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
                        <a href="{{route('admin.ai.train')}}" class="btn btn-success btn-sm float-right"><i class="fas fa-fw fa-robot"></i> Train AI</a>
                    </div>
                    <div class="card-body">
                        <table id="applications" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th width="20%">Query</th>
                                    <th>Response</th>
                                    <th width="15%">Last Update</th>
                                    <th width="12%">Action
                                        <a href="{{route('admin.ai.create')}}" class="btn btn-primary btn-xs float-right" title="New training data"><i class="fas fa-fw fa-plus"></i>New</a>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @if(sizeof($trainings) > 0)
                                    @foreach($trainings as $training)
                                        <tr>
                                            <td>{{$training->id}}</td>
                                            <td>{{$training->user_message}}</td>
                                            <td>{{$training->ai_response}}</td>
                                            <td>{{$training->updated_at}}</td>
                                            <td>
                                                <a href="{{route('admin.ai.modify', $training)}}" class="btn btn-warning btn-xs" title="Modify training data"><i class="fas fa-fw fa-edit"></i></a>
                                                <a href="{{route('admin.ai.delete', $training)}}" 
                                                    onclick="return confirm('This will delete the training data. Are you sure?')"
                                                    class="btn btn-danger btn-xs" title="Delete training data"><i class="fas fa-fw fa-trash"></i></a>
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="5">0 trainings found.</td>
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
                "pageLength": 100,
                "lengthMenu": [5, 10, 25, 50, 100, 1000, 2000, 3000, 4000, 5000], // You can customize these values
                "ordering": false, // Disable initial sorting
                "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
            }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
        });
    </script>
@stop