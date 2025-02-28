@extends('adminlte::page')

@php
    $title = "Modify Application";
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
                <li class="breadcrumb-item"><a href="{{route('admin.applications.show', ['application' => $application])}}">{{$application->application_code}}</a></li>
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
                        <h3 class="card-title">Modify Application Code: <strong>{{$application->application_code}}</strong></h3>
                    </div>
                    <form method="post" action="{{route('admin.applications.update', ['application' => $application])}}">
                        @csrf
                        @method('put')
                        <div class="card-body">
                            <div class="form-group">
                                <label for="#">Applicant's email</label>
                                <input type="email" class="form-control" placeholder="Enter applicant's email" 
                                    name="email" class="@error('email') is-invalid @enderror"
                                    value="{{ $application->email }}">
                                @error('email')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror                            
                            </div>
                            <div class="form-group">
                                <label for="#">Position applied for</label>
                                <select type="text" class="form-control" placeholder="Enter position applied for" 
                                    name="vacancy_id" class="@error('position_applied') is-invalid @enderror"
                                    value="{{ $application->vacancy_id }}">
                                    @if(sizeof($vacancies) > 0)
                                        @foreach($vacancies as $vacancy)
                                            <option value="{{$vacancy->id}}" {{ $vacancy->id == $application->vacancy_id ? 'selected' :'' }}>{{ $vacancy->cycle}}- {{ $vacancy->position_title}}</option>
                                        @endforeach
                                    @else 
                                        <option value="">No entries yet</option>
                                    @endif
                                </select>
                                @error('vacancy_id')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror                           
                            </div>
                            <div class="form-group">
                                <label for="#">Station tagging</label>
                                <select type="text" class="form-control" placeholder="Enter position applied for" 
                                    name="station_id" class="@error('station_id') is-invalid @enderror"
                                    value="{{ $application->station_id }}">
                                    <option value="0" {{ $application->station_id == -1 || $application->station_id == 0 ? 'selected' :'' }} {{ isset($application->assessment) ? 'disabled' : '' }}>Not yet tagged</option>
                                    @foreach($stations as $station)
                                        <option value="{{$station->id}}" {{ $station->id == $application->station_id ? 'selected' :'' }}>{{ $station->code}}- {{ $station->name}}</option>
                                    @endforeach
                                </select>
                                @error('station_id')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror   
                                
                                @if(isset($application->assessment))
                                    <br>
                                    <a href="{{route('admin.applications.remove_station', $application)}}" class="btn btn-danger"
                                        onclick="return confirm('This will remove the school tagging permanently. Are you sure?')">Remove Station Tagging</a>
                                @endif                           
                            </div>
                            <div class="form-group">
                                <label for="#">Status</label>
                                <input type="text" class="form-control" placeholder="Enter position applied for" 
                                    name="" 
                                    value="{{ isset($application->assessment) ? $application->assessment->get_status() : 'New' }}" readonly>
                                @error('station_id')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror 

                                @if(isset($application->assessment))
                                    <br>
                                    @if($application->assessment->status == 4)
                                    <a href="{{route('admin.applications.qualify', $application)}}" class="btn btn-success"
                                        onclick="return confirm('This will tag the application as Qualified. Are you sure?')">Qualify</a>
                                    @else
                                    <a href="{{route('admin.applications.disqualify', $application)}}" class="btn btn-danger"
                                        onclick="return confirm('This will tag the application as Disqualified. Are you sure?')">Disqualify</a>
                                    @endif
                                @endif                          
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">Update</button>
                            
                            <a href="{{route('admin.applications.show', $application)}}" class="btn btn-default float-right">Cancel</a>
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