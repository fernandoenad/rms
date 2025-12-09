@extends('adminlte::page')

@php
    $title = "Assessment Items";
    $app_name = config('app.name', '') . ' [Admin]';
@endphp

@section('title', config('app.name', '') . ' | ' . $title)

@section('content_header')
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1 class="m-0">{{ $title }}</h1>
            <small class="text-muted">Position: {{ $exam->vacancy ? $exam->vacancy->position_title : 'N/A' }}</small>
        </div>
        <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.assessments.index') }}">Assessments</a></li>
                <li class="breadcrumb-item active">{{ $exam->title }}</li>
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
                        <div>
                            <h3 class="card-title mb-0">Items for: {{ $exam->title }}</h3>
                        </div>
                        <div class="float-right d-flex align-items-center">
                            <a type="button" class="btn btn-sm btn-secondary mr-2" href="{{ route('admin.assessments.results', $exam) }}">
                                <i class="fas fa-chart-bar"></i> View results
                            </a>
                            <a type="button" class="btn btn-sm btn-primary {{ $hasAttempts ? 'disabled' : '' }}" href="{{ $hasAttempts ? '#' : route('admin.assessments.items.create', $exam) }}">
                                <i class="fas fa-plus"></i> Add item
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3 justify-content-end">
                            <form method="post" action="{{ route('admin.assessments.items.import', $exam) }}" enctype="multipart/form-data" class="d-inline-flex">
                                @csrf
                                <div class="input-group input-group-sm">
                                    <div class="custom-file">
                                        <input type="file" name="file" accept=".csv,.txt" class="custom-file-input" id="csvFile" {{ $hasAttempts ? 'disabled' : '' }} required>
                                        <label class="custom-file-label" for="csvFile">Choose CSV</label>
                                    </div>
                                    <div class="input-group-append">
                                        <button type="submit" class="btn btn-sm btn-info" {{ $hasAttempts ? 'disabled' : '' }}>Upload CSV</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <table id="exam_items" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Question</th>
                                    <th>Answer</th>
                                    <th>Status</th>
                                    <th>Attempts</th>
                                    <th width="16%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($items as $item)
                                    <tr>
                                        <td>{{ $item->id }}</td>
                                        <td>{{ $item->question }}</td>
                                        <td>{{ $item->answer_key }}</td>
                                        <td>
                                            <form method="post" action="{{ route('admin.assessments.items.toggle', [$exam, $item]) }}">
                                                @csrf
                                                @method('put')
                                                <button type="submit" class="btn btn-link p-0">
                                                    @if($item->status == 1)
                                                        <i class="fas fa-eye text-success" title="Active"></i>
                                                    @else
                                                        <i class="fas fa-eye-slash text-muted" title="Inactive"></i>
                                                    @endif
                                                </button>
                                            </form>
                                        </td>
                                        <td>{{ $item->attempts ?? 0 }}</td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#itemModal{{ $item->id }}">
                                                <i class="fas fa-eye"></i> 
                                            </button>
                                            <a href="{{ route('admin.assessments.items.edit', [$exam, $item]) }}" class="btn btn-sm btn-warning {{ $hasAttempts ? 'disabled' : '' }}">
                                                <i class="fas fa-edit"></i> 
                                            </a>
                                            <form method="post" action="{{ route('admin.assessments.items.destroy', [$exam, $item]) }}" class="d-inline">
                                                @csrf
                                                @method('delete')
                                                <button type="submit" class="btn btn-sm btn-danger {{ $hasAttempts || ($item->attempts ?? 0) > 0 ? 'disabled' : '' }}" onclick="return confirm('Are you sure you want to delete this item?');">
                                                    <i class="fas fa-trash"></i> 
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <div class="modal fade" id="itemModal{{ $item->id }}" tabindex="-1" aria-labelledby="itemModalLabel{{ $item->id }}" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="itemModalLabel{{ $item->id }}">Item #{{ $item->id }}</h5>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <p><strong>Question:</strong> {{ $item->question }}</p>
                                                    <ul>
                                                        <li><strong>A:</strong> {{ $item->option_a }}</li>
                                                        <li><strong>B:</strong> {{ $item->option_b }}</li>
                                                        <li><strong>C:</strong> {{ $item->option_c }}</li>
                                                        <li><strong>D:</strong> {{ $item->option_d }}</li>
                                                    </ul>
                                                    <p><strong>Answer Key:</strong> {{ $item->answer_key }}</p>
                                                    <p><strong>Status:</strong> {{ $item->status == 1 ? 'Active' : 'Inactive' }}</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <tr>
                                        <td colspan="6">0 items found.</td>
                                    </tr>
                                @endforelse
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
            $("#exam_items").DataTable({
                "responsive": true, "lengthChange": false, "autoWidth": false, "pageLength": 10,
                "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
            }).buttons().container().appendTo('#exam_items_wrapper .col-md-6:eq(0)');
        });
    </script>
@stop
@section('css')
    <style>
        .custom-file-label::after {
            content: "Browse";
        }
    </style>
@stop
