@extends('adminlte::page')

@php
    $title = "Delete Application";
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
                <li class="breadcrumb-item"><a href="{{route('admin.applications.index')}}">Applications</a></li>
                <li class="breadcrumb-item"><a href="{{route('admin.applications.show', $application)}}">{{$application->application_code}}</a></li>
                <li class="breadcrumb-item active">{{ $title }}</li>
            </ol>
        </div>
    </div>
@stop

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-6 offset-lg-3 col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Are you sure?</h3>
                </div>
                <form method="post" action="{{route('admin.applications.destroy', $application)}}">
                    @csrf 
                    @method('delete')
                    <div class="card-body">
                        @if($errors->has('deletion'))
                            <div class="alert alert-danger" role="alert">{{ $errors->first('deletion') }}</div>
                        @endif
                        @if($blockingReasons !== [])
                            <div class="alert alert-warning" role="alert">
                                <strong>Deletion unavailable: protected application history exists.</strong>
                                <p class="mb-2">Application <strong>{{ $application->application_code }}</strong> cannot be permanently deleted while the following records exist:</p>
                                <ul class="mb-2">
                                    @foreach($blockingReasons as $reason)
                                        <li>{{ $reason }}</li>
                                    @endforeach
                                </ul>
                                <p class="mb-0">No records have been removed. Use the appropriate HR review or cancellation process; do not directly remove account ownership, assessment, or audit records from the database.</p>
                            </div>
                        @else
                            <div class="alert alert-info" role="status">
                                This application currently has no linked ownership, assessment, or other protected processing records.
                                Permanent deletion cannot be undone and will be audited.
                            </div>
                            <p>Are you sure you want to permanently delete <strong>{{ $application->application_code }}</strong>?</p>
                        @endif
                    </div>
                    <div class="card-footer">
                        @if($blockingReasons === [])
                            <button type="submit" class="btn btn-danger">Yes, delete unused application</button>
                        @endif
                        <a href="{{ route('admin.applications.show', $application) }}" class="btn btn-default float-right">Back to Application</a>
                    </div>
                </form> 
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
            "responsive": true, "lengthChange": false, "autoWidth": false,
            "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
            }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
        });
    </script>
@stop