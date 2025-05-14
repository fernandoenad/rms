@extends('adminlte::page')

@php
    $title = "Modify Assessment";
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
                <li class="breadcrumb-item"><a href="{{route('admin.discrepancies.index')}}">Discrepancies</a></li>
                <li class="breadcrumb-item active">{{ $title }}</li>
            </ol>
        </div>
    </div>
@stop

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                @if (session('status'))
                    <div class="alert alert-success">
                        {{ session('status') }}
                    </div>
                @endif

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Modify Assessment for Application Code: <strong>{{$assessment->application->application_code}}</strong></h3>
                    </div>
                    <form method="post" action="{{route('admin.discrepancies.update', $assessment)}}">
                        @csrf 
                        @method('put')
                    <div class="card-body p-0">
                        <table class="table m-0 table-hover">
                            <tbody>
                                <tr>
                                    <th width="30%">Name</th>
                                    <td>{{ $assessment->application->getFullname() }}</td>
                                </tr>
                                <tr>
                                    <th>Position title applied for</th>
                                    <td>{{ $assessment->application->vacancy->position_title }}</td>
                                </tr>
                                @php 
                                    $assessment = App\Models\Assessment::where('application_id', '=', $assessment->application->id)->first();

                                @endphp
                                <tr>
                                    <th>Status</th>
                                    <td>{{ $assessment->status == 3 ? 'DRC Completed' : 'Posted' }}</td>
                                </tr>
                                @php 
                                    $assessment_scores = json_decode($assessment->assessment);
                                    $template = App\Models\Template::find($assessment->application->vacancy->template_id);
                                    $assessment_template = json_decode($template->template, true);
                                    $total_points = 0;
                                @endphp 

                                @foreach($assessment_scores as $key => $value)
                                    @php $total_points += is_numeric($value) ? $value : 0; @endphp
                                    <tr>
                                        <th>{{ $key }} ({{ $assessment_template[$key] }})</th>
                                        <td>
                                            <div class="form-group">
                                                <input type="{{ is_numeric($value) ? 'number' : 'text' }}" class="form-control" placeholder="Enter {{$key}} points" 
                                                    name="{{ $key }}" class="@error('{{ $key }}') is-invalid @enderror"
                                                    max="{{ $assessment_template[$key] }}"
                                                    step="{{ is_numeric($value) ? '0.001' : '' }}"
                                                    value="{{ $value }}">
                                                @error($key)
                                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                                @enderror
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <th>Score</th>
                                    <td>
                                        Raw: <strong>{{$assessment->score}} </strong><br>
                                        Published: <strong>{{$total_points}} </strong>
                                </td>
                                </tr>

                               
                            </tbody>
                        </table>                    
                    </div>

                    <div class="card-footer p-2">
                        <button type="submit" class="btn btn-primary" {{$assessment->status != 3 ? 'disabled':''}}>Update</button>
                        <div class="float-right">
                            <a href="{{route('admin.discrepancies.index')}}" 
                            class="btn btn-info"><i class="fas fa-reply"></i> Back</a>
                        </div>
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