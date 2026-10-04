@extends('adminlte::page')

@php
    $title = "New Vacancy";
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
                        <h3 class="card-title">Create a vacancy</h3>
                    </div>
                    <form method="post" action="{{ route('admin.vacancies.store') }}">
                        @csrf
                        @method('post')
                        <div class="card-body">
                            <div class="form-group">
                                <label for="#">Cycle</label>
                                <input type="text" class="form-control" placeholder="Enter cycle" 
                                    name="cycle" class="@error('cycle') is-invalid @enderror"
                                    value="{{ now()->year }}" readonly>
                                @error('cycle')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="#">Position title</label>
                                <input type="text" class="form-control" placeholder="Enter position title" 
                                    name="position_title" class="@error('position_title') is-invalid @enderror"
                                    value="{{ old('position_title') }}" autofocus>
                                @error('position_title')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="#">Salary grade</label>
                                <select type="text" class="form-control" placeholder="Enter salary grade" 
                                    name="salary_grade" class="@error('salary_grade') is-invalid @enderror"
                                    value="{{ old('salary_grade') }}">
                                    <option value="">---select---</option>
                                    @for($sg = 1; $sg <= 33; $sg++)
                                        <option value="{{ $sg }}" {{ old('salary_grade') == $sg ? 'selected' : '' }}>Salary Grade {{$sg}}</option>
                                    @endfor
                                </select>
                                @error('salary_grade')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="#">Base pay</label>
                                <input type="number" class="form-control" placeholder="Enter base pay" 
                                    name="base_pay" class="@error('base_pay') is-invalid @enderror"
                                    value="{{ old('base_pay') }}">
                                @error('base_pay')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="#">Office level</label>
                                <select type="text" class="form-control" placeholder="Enter office level" 
                                    name="office_level" class="@error('office_level') is-invalid @enderror"
                                    value="{{ old('office_level') }}">
                                    <option value="">---select---</option>
                                    <option value="0" {{ old('office_level') == 0 ? 'selected' : '' }}>SDO</option>
                                    <option value="-1" {{ old('office_level') == -1 ? 'selected' : '' }}>Field</option>
                                </select>
                                @error('office_level')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="#">Qualifications</label>
                                <textarea type="text" class="form-control" placeholder="Enter qualifications" 
                                    name="qualifications" class="@error('qualifications') is-invalid @enderror"
                                    value="{{ old('qualifications') }}">{{ old('qualifications') }}</textarea>
                                @error('qualifications')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="#">Number of Vacancies</label>
                                <input type="number" class="form-control" placeholder="Enter vacancy" 
                                    name="vacancy" class="@error('vacancy') is-invalid @enderror"
                                    value="{{ old('vacancy') }}">
                                @error('vacancy')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="card card-outline card-info">
                                <div class="card-header py-2"><strong>Posting & Application Window</strong></div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label>Posting Mode</label>
                                        <select class="form-control @error('status') is-invalid @enderror" name="status">
                                            <option value="0" {{ (string)old('status','0')==='0' ? 'selected' : '' }}>Draft — not visible to applicants</option>
                                            <option value="1" {{ (string)old('status','0')==='1' ? 'selected' : '' }}>Published — visibility follows the dates below</option>
                                        </select>
                                        <small class="text-muted">Published vacancies automatically become Scheduled, Open for Applications, or Closed based on the posting window.</small>
                                        @error('status')<span class="text-danger d-block"><small>{{ $message }}</small></span>@enderror
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label>Applications Open</label>
                                            <input type="datetime-local" name="posting_start_at" class="form-control @error('posting_start_at') is-invalid @enderror" value="{{ old('posting_start_at') }}">
                                            <small class="text-muted">Leave blank to open immediately when published.</small>
                                            @error('posting_start_at')<span class="text-danger"><small>{{ $message }}</small></span>@enderror
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label>Applications Close</label>
                                            <input type="datetime-local" name="posting_end_at" class="form-control @error('posting_end_at') is-invalid @enderror" value="{{ old('posting_end_at') }}">
                                            <small class="text-muted">After this time, new applications are blocked automatically. The vacancy does not revert to Draft.</small>
                                            @error('posting_end_at')<span class="text-danger"><small>{{ $message }}</small></span>@enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="#">Template</label>
                                <select type="text" class="form-control" placeholder="Enter status" 
                                    name="template_id" class="@error('template_id') is-invalid @enderror"
                                    value="{{ old('template_id') }}">
                                    <option value="">---select---</option>
                                    @foreach($templates as $template)
                                        <option value="{{ $template->id }}" {{ $template->id == old('template_id') ? 'selected' : '' }}>{{ $template->type }}</option>
                                    @endforeach
                                </select>
                                @error('template_id')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>                            
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save Vacancy</button>
                            <button type="reset" class="btn btn-default">Reset</button>
                            <a href="{{ url()->previous() }}" class="btn btn-default float-right">Cancel</a>
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