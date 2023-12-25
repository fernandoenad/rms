@extends('adminlte::page')

@section('title', 'RMS v1.0 | Edit Application')

@section('content_header')
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1 class="m-0">Edit Application</h1>
        </div>
        <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="{{route('admin.index')}}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{route('admin.applications.index')}}">Applications</a></li>
                <li class="breadcrumb-item"><a href="{{route('admin.applications.show', ['application' => $application])}}">{{$application->application_code}}</a></li>
                <li class="breadcrumb-item active">Editing</li>
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
                        <h3 class="card-title">Update for record ID# <strong>{{$application->id}}</strong></h3>
                    </div>
                    <form method="post" action="{{route('admin.applications.update', ['application' => $application])}}">
                        @csrf
                        @method('put')
                        <div class="card-body">
                            <div class="form-group">
                                <label for="#">Application code</label>
                                <input type="text" class="form-control" placeholder="Enter application code" 
                                    name="application_code" class="@error('application_code') is-invalid @enderror"
                                    value="{{ $application->application_code }}">
                                @error('application_code')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="#">Applicant's email</label>
                                <input type="email" class="form-control" placeholder="Enter applicant's email" 
                                    name="applicant_email" class="@error('applicant_email') is-invalid @enderror"
                                    value="{{ $application->applicant_email }}">
                                @error('applicant_email')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror                            
                            </div>
                            <div class="form-group">
                                <label for="#">Applicant's fullname</label>
                                <input type="text" class="form-control" placeholder="Enter applicant's fullname" 
                                    name="applicant_fullname" class="@error('applicant_fullname') is-invalid @enderror"
                                    value="{{ $application->applicant_fullname }}">
                                @error('applicant_fullname')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror                            
                            </div>
                            <div class="form-group">
                                <label for="#">Position applied for</label>
                                <input type="text" class="form-control" placeholder="Enter position applied for" 
                                    name="position_applied" class="@error('position_applied') is-invalid @enderror"
                                    value="{{ $application->position_applied }}">
                                @error('position_applied')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror                           
                            </div>
                            <div class="form-group">
                                <label for="#">Application document</label>
                                <input type="url" class="form-control" placeholder="Enter application document URL" 
                                    name="pertinent_doc" class="@error('pertinent_doc') is-invalid @enderror"
                                    value="{{ $application->pertinent_doc }}">
                                @error('pertinent_doc')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror                           
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">Update</button>
                            <a href="{{route('admin.applications.show',['application' => $application])}}" class="btn btn-default float-right">Cancel</a>
                        </div>
                    </form> 
                </div>
            </div>
        </div>
    </div>
@stop

@section('footer')
    <strong>{{ config('app.name', 'Laravel') }}</strong>. Developed by Dr. Fernando B. Enad
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