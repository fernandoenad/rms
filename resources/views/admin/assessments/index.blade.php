@extends('adminlte::page')

@php
    $title = "Assessments";
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
                        <a type="button" class="btn btn-sm btn-primary float-right" href="{{ route('admin.assessments.create') }}">
                            <i class="fas fa-plus"></i> Add assessment
                        </a>
                    </div>
                    <div class="card-body">
                        <table id="written_exams" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Exam title</th>
                                    <th>Position</th>
                                    <th>Enrollment key</th>
                                    <th>Start</th>
                                    <th>End</th>
                                    <th>Duration (min)</th>
                                    <th>Shuffle</th>
                                    <th>Items</th>
                                    <th>Status</th>
                                    <th width="15%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if(sizeof($exams) > 0)
                                    @foreach($exams as $exam)
                                        <tr>
                                            <td>{{ $exam->id }}</td>
                                            <td>{{ $exam->title }}</td>
                                            <td>{{ $exam->vacancy ? $exam->vacancy->position_title : 'Unassigned' }}</td>
                                            <td>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span>{{ $exam->enrollment_key }}</span>
                                                    <form method="post" action="{{ route('admin.assessments.regenerate_key', $exam) }}" class="d-inline mb-0">
                                                        @csrf
                                                        @method('put')
                                                        <button type="submit" class="btn btn-link btn-sm" title="Regenerate enrollment key" onclick="return confirm('Generate a new enrollment key?');">
                                                            <i class="fas fa-recycle"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                            <td>{{ $exam->start_date }}</td>
                                            <td>{{ $exam->end_date }}</td>
                                            <td>{{ $exam->duration }}</td>
                                            <td>{{ $exam->shuffle_items ? 'Yes' : 'No' }}</td>
                                            <td>{{ $exam->written_exams_count }}</td>
                                            <td>
                                                <form method="post" action="{{ route('admin.assessments.toggle', $exam) }}">
                                                    @csrf
                                                    @method('put')
                                                    <button type="submit" class="btn btn-link p-0">
                                                        @if($exam->status == 1)
                                                            <i class="fas fa-toggle-on text-success"></i>
                                                        @else
                                                            <i class="fas fa-toggle-off text-muted"></i>
                                                        @endif
                                                    </button>
                                                </form>
                                            </td>
                                            <td>
                                                <a href="{{ route('admin.assessments.edit', $exam) }}" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i> 
                                                </a>
                                                <form action="{{ route('admin.assessments.destroy', $exam) }}" method="post" class="d-inline">
                                                    @csrf
                                                    @method('delete')
                                                    <button type="submit" class="btn btn-sm btn-danger {{ $exam->written_exams_count > 0 ? 'disabled' : '' }}" onclick="return confirm('Are you sure you want to delete this exam?');">
                                                        <i class="fas fa-trash"></i> 
                                                    </button>
                                                </form>
                                                <a href="{{ route('admin.assessments.items.index', $exam) }}" class="btn btn-sm btn-info">
                                                    <i class="fas fa-folder-open"></i> 
                                                </a>
                                                <a href="{{ route('admin.assessments.results', $exam) }}" class="btn btn-sm btn-secondary" title="View results">
                                                    <i class="fas fa-chart-bar"></i>
                                                </a>                                                
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="11">0 written exams found.</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
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
@stop

@section('plugins.Datatables', true)

@section('js')
    <script>
        $(function () {
            $("#written_exams").DataTable({
                "responsive": true, "lengthChange": false, "autoWidth": false, "pageLength": 10,
                "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
            }).buttons().container().appendTo('#written_exams_wrapper .col-md-6:eq(0)');
        });
    </script>
@stop
