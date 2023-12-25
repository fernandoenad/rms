@extends('adminlte::page')

@section('title', 'RMS v1.0 | New Application')

@section('content_header')
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1 class="m-0">New Application</h1>
        </div>
        <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="{{route('admin.index')}}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{route('admin.applications.index')}}">Applications</a></li>
                <li class="breadcrumb-item active">New application</li>
            </ol>
        </div>
    </div>
@stop

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Accomplish the form below</h3>
                    </div>
                    <form method="post" action="{{route('admin.applications.store')}}">
                        @csrf
                        @method('post')
                        <div class="card-body">
                            <div class="form-group">
                                <label for="#">Application code</label>
                                <input type="text" class="form-control" placeholder="Enter application code" 
                                    name="application_code" class="@error('application_code') is-invalid @enderror"
                                    value="{{ old('application_code') }}">
                                @error('application_code')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="#">Applicant's email</label>
                                <input type="email" class="form-control" placeholder="Enter applicant's email" 
                                    name="applicant_email" class="@error('applicant_email') is-invalid @enderror"
                                    value="{{ old('applicant_email') }}">
                                @error('applicant_email')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror                            
                            </div>
                            <div class="form-group">
                                <label for="#">Applicant's fullname</label>
                                <input type="text" class="form-control" placeholder="Enter applicant's fullname" 
                                    name="applicant_fullname" class="@error('applicant_fullname') is-invalid @enderror"
                                    value="{{ old('applicant_fullname') }}">
                                @error('applicant_fullname')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror                            
                            </div>
                            <div class="form-group">
                                <label for="#">Position applied for</label>
                                <input type="text" class="form-control" placeholder="Enter position applied for" 
                                    name="position_applied" class="@error('position_applied') is-invalid @enderror"
                                    value="{{ old('position_applied') }}">
                                @error('position_applied')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror                           
                            </div>
                            <div class="form-group">
                                <label for="#">Application document</label>
                                <input type="url" class="form-control" placeholder="Enter application document URL" 
                                    name="pertinent_doc" class="@error('pertinent_doc') is-invalid @enderror"
                                    value="{{ old('pertinent_doc') }}">
                                @error('pertinent_doc')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror                           
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">Submit</button>
                            <button type="reset" class="btn btn-default">Clear</button>
                            <a href="{{route('admin.applications.index')}}" class="btn btn-default float-right">Cancel</a>
                        </div>
                    </form> 
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Create bulk</h3>
                    </div>
                    <form method="post" action="{{route('admin.applications.import')}}" enctype="multipart/form-data">
                        @csrf
                        @method('post')
                        <div class="card-body">
                            <div class="form-group">
                                <label for="#">Application code</label>
                                <input type="file" class="form-control" placeholder="Upload CSV file here" 
                                    name="file" class="@error('file') is-invalid @enderror"
                                    value="{{ old('file') }}" accept=".csv">
                                @error('file')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">Import CSV</button>
                            <button type="reset" class="btn btn-default">Clear</button>
                            <a href="{{route('admin.applications.index')}}" class="btn btn-default float-right">Cancel</a>
                        </div>
                    </form> 
                </div>
            </div>
        </div>
    </div>
@stop

@section('footer')
    <div class="float-right d-none d-sm-inline">
        Developed by Dr. Fernando B. Enad
    </div>
    <strong>Copyright &copy; 2023 <a href="/">{{ config('app.name', '') }}</a>.</strong> All rights reserved.
@stop

@section('css')
    <link rel="stylesheet" href="/css/admin_custom.css">
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