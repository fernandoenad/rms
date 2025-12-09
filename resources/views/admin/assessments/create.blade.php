@extends('adminlte::page')

@php
    $title = "New Written Exam";
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
                <li class="breadcrumb-item active">Assessments</li>
                <li class="breadcrumb-item"><a href="{{ route('admin.assessments.index') }}">Written Exams</a></li>
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
                            <h3 class="card-title">Accomplish the form below</h3>
                        </div>
                    <form method="post" action="{{ route('admin.assessments.store') }}">
                        @csrf
                        @method('post')
                        <div class="card-body">
                            <h5 class="text-primary mb-3">Test details</h5>
                            <div class="form-group">
                                <label for="vacancy_id">Position</label>
                                <select name="vacancy_id" id="vacancy_id" class="form-control @error('vacancy_id') is-invalid @enderror">
                                    <option value="">---select---</option>
                                    @foreach($vacancies as $vacancy)
                                        <option value="{{ $vacancy->id }}" {{ old('vacancy_id') == $vacancy->id ? 'selected' : '' }}>
                                            {{ $vacancy->position_title }} ({{ $vacancy->cycle }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('vacancy_id')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="title">Exam title</label>
                                <input type="text" id="title" class="form-control @error('title') is-invalid @enderror" placeholder="Enter exam title"
                                    name="title" value="{{ old('title') }}" autofocus>
                                @error('title')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="start_date">Start date</label>
                                <input type="datetime-local" id="start_date" class="form-control @error('start_date') is-invalid @enderror"
                                    name="start_date" value="{{ old('start_date') }}">
                                @error('start_date')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="end_date">End date</label>
                                <input type="datetime-local" id="end_date" class="form-control @error('end_date') is-invalid @enderror"
                                    name="end_date" value="{{ old('end_date') }}">
                                @error('end_date')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="duration">Duration (minutes)</label>
                                <input type="number" min="1" id="duration" class="form-control @error('duration') is-invalid @enderror" placeholder="Enter duration in minutes"
                                    name="duration" value="{{ old('duration') }}">
                                @error('duration')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="shuffle_items">Shuffle items</label>
                                <select id="shuffle_items" name="shuffle_items" class="form-control @error('shuffle_items') is-invalid @enderror">
                                    <option value="">---select---</option>
                                    <option value="1" {{ old('shuffle_items', '0') == '1' ? 'selected' : '' }}>Yes</option>
                                    <option value="0" {{ old('shuffle_items', '0') == '0' ? 'selected' : '' }}>No</option>
                                </select>
                                @error('shuffle_items')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status" class="form-control @error('status') is-invalid @enderror">
                                    <option value="">---select---</option>
                                    <option value="0" {{ old('status', '') === '0' ? 'selected' : '' }}>Draft</option>
                                    <option value="1" {{ old('status') == '1' ? 'selected' : '' }}>Published</option>
                                </select>
                                @error('status')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary" onclick="return confirm('Are you sure you want to save this exam?');">Submit</button>
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
    <script>console.log('Written exam create loaded');</script>
@stop
