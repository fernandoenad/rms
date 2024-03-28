@extends('adminlte::page')

@php
    $title = "Delete Vacancy";
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
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-6 offset-lg-3 col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Are you sure?</h3>
                </div>
                <form method="post" action="{{ route('admin.vacancies.destroy', $vacancy) }}">
                    @csrf 
                    @method('delete')
                    <div class="card-body">
                        <div class="form-group">
                            <p>You are about to delete <strong>{{ $vacancy->position_title }}</strong>
                                which is irreversible. <br>Would you like to proceed? </p>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">Yes</button>
                        <a href="{{ url()->previous() }}" class="btn btn-default float-right">No</a>
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
            "responsive": true, "lengthChange": false, "autoWidth": false, "pageLength": 5,
            "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
            }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
        });
    </script>
@stop