@extends('adminlte::page')

@php
    $title = "Edit Vacancy";
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
                        <h3 class="card-title">Modify for record ID# <strong>{{ $vacancy->id }}</strong></h3>
                    </div>
                    <form method="post" action="{{ route('admin.vacancies.update', $vacancy) }}">
                        @csrf
                        @method('put')
                        <div class="card-body">
                            <div class="form-group">
                                <label for="#">Cycle</label>
                                <input type="text" class="form-control" placeholder="Enter cycle" 
                                    name="cycle" class="@error('cycle') is-invalid @enderror"
                                    value="{{ $vacancy->cycle }}" readonly>
                                @error('cycle')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="#">Position title</label>
                                <input type="text" class="form-control" placeholder="Enter position title" 
                                    name="position_title" class="@error('position_title') is-invalid @enderror"
                                    value="{{ $vacancy->position_title }}" autofocus>
                                @error('position_title')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="#">Salary grade</label>
                                <select type="text" class="form-control" placeholder="Enter salary grade" 
                                    name="salary_grade" class="@error('salary_grade') is-invalid @enderror"
                                    value="{{ $vacancy->salary_grade }}">
                                    <option value="">---select---</option>
                                    @for($sg = 1; $sg <= 33; $sg++)
                                        <option value="{{$sg}}" {{ $vacancy->salary_grade == $sg ? 'selected' : '' }}>Salary Grade {{$sg}}</option>
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
                                    value="{{ $vacancy->base_pay }}">
                                @error('base_pay')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="#">Office level</label>
                                <select type="text" class="form-control" placeholder="Enter office level" 
                                    name="office_level" class="@error('office_level') is-invalid @enderror"
                                    value="{{ $vacancy->office_level }}">
                                    <option value="">---select---</option>
                                    <option value="0" {{ $vacancy->office_level == 0 ? 'selected' : '' }}>SDO</option>
                                    <option value="-1" {{ $vacancy->office_level == -1 ? 'selected' : '' }}>Field</option>
                                </select>
                                @error('office_level')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="#">Qualifications</label>
                                <textarea type="text" class="form-control" placeholder="Enter qualifications" 
                                    name="qualifications" class="@error('qualifications') is-invalid @enderror"
                                    value="{{ $vacancy->qualifications }}">{{ $vacancy->qualifications }}</textarea>
                                @error('qualifications')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="#">Vacancy</label>
                                <input type="number" class="form-control" placeholder="Enter vacancy" 
                                    name="vacancy" class="@error('vacancy') is-invalid @enderror"
                                    value="{{ $vacancy->vacancy }}">
                                @error('vacancy')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="#">Status</label>
                                <select type="text" class="form-control" placeholder="Enter status" 
                                    name="status" class="@error('status') is-invalid @enderror"
                                    value="{{ $vacancy->status }}">
                                    <option value="">---select---</option>
                                    <option value="0" {{ $vacancy->status == 0 ? 'selected' : '' }}>Draft</option>
                                    <option value="1" {{ $vacancy->status == 1 ? 'selected' : '' }}>Published</option>
                                </select>
                                @error('status')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="#">Template</label>
                                <select type="text" class="form-control" placeholder="Enter status" 
                                    name="template_id" class="@error('template_id') is-invalid @enderror"
                                    value="{{ old('template_id') }}">
                                    <option value="">---select---</option>
                                    @foreach($templates as $template)
                                        <option value="{{ $template->id }}" {{ $template->id == $vacancy->template_id ? 'selected' : '' }}>{{ $template->type }}</option>
                                    @endforeach
                                </select>
                                @error('template_id')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div> 
                            <div class="form-group">
                                <label for="#">Level 1 Status (Station Level)</label>
                                <select type="text" class="form-control" placeholder="Enter status" 
                                    name="level1_status" class="@error('level1_status') is-invalid @enderror"
                                    value="{{ $vacancy->level1_status }}">
                                    <option value="">---select---</option>
                                    <option value="0" {{ $vacancy->level1_status == 0 ? 'selected' : '' }}>Close</option>
                                    <option value="1" {{ $vacancy->level1_status == 1 ? 'selected' : '' }}>Open</option>
                                    <option value="2" {{ $vacancy->level1_status == 2 ? 'selected' : '' }}>Completed</option>
                                </select>
                                @error('level1_status')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="#">Level 2 Status (Sub-Committee Level)</label>
                                <select type="text" class="form-control" placeholder="Enter status" 
                                    name="level2_status" class="@error('level2_status') is-invalid @enderror"
                                    value="{{ $vacancy->level2_status }}">
                                    <option value="">---select---</option>
                                    <option value="0" {{ $vacancy->level2_status == 0 ? 'selected' : '' }}>Close</option>
                                    <option value="1" {{ $vacancy->level2_status == 1 ? 'selected' : '' }}>Open</option>
                                    <option value="2" {{ $vacancy->level2_status == 2 ? 'selected' : '' }}>Completed</option>
                                </select>
                                @error('level2_status')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>   
                            
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">Submit</button>
                            <button type="reset" class="btn btn-default">Clear</button>
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