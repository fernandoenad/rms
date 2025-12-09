@extends('adminlte::page')

@php
    $title = "Update Item";
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
                <li class="breadcrumb-item"><a href="{{ route('admin.assessments.index') }}">Written Exams</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.assessments.items.index', $exam) }}">Items</a></li>
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
                        <h3 class="card-title">Edit item #{{ $item->id }}</h3>
                    </div>
                    <form method="post" action="{{ route('admin.assessments.items.update', [$exam, $item]) }}">
                        @csrf
                        @method('put')
                        <div class="card-body">
                            <div class="form-group">
                                <label for="question">Question</label>
                                <textarea id="question" class="form-control @error('question') is-invalid @enderror" placeholder="Enter question"
                                    name="question">{{ old('question', $item->question) }}</textarea>
                                @error('question')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-12">
                                    <label for="option_a">Option A</label>
                                    <input type="text" id="option_a" class="form-control @error('option_a') is-invalid @enderror" placeholder="Enter option A"
                                        name="option_a" value="{{ old('option_a', $item->option_a) }}">
                                    @error('option_a')
                                        <span class="text-danger"><small>{{ $message }}</small></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-12">
                                    <label for="option_b">Option B</label>
                                    <input type="text" id="option_b" class="form-control @error('option_b') is-invalid @enderror" placeholder="Enter option B"
                                        name="option_b" value="{{ old('option_b', $item->option_b) }}">
                                    @error('option_b')
                                        <span class="text-danger"><small>{{ $message }}</small></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-12">
                                    <label for="option_c">Option C</label>
                                    <input type="text" id="option_c" class="form-control @error('option_c') is-invalid @enderror" placeholder="Enter option C"
                                        name="option_c" value="{{ old('option_c', $item->option_c) }}">
                                    @error('option_c')
                                        <span class="text-danger"><small>{{ $message }}</small></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-12">
                                    <label for="option_d">Option D</label>
                                    <input type="text" id="option_d" class="form-control @error('option_d') is-invalid @enderror" placeholder="Enter option D"
                                        name="option_d" value="{{ old('option_d', $item->option_d) }}">
                                    @error('option_d')
                                        <span class="text-danger"><small>{{ $message }}</small></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="answer_key">Answer key</label>
                                <select id="answer_key" name="answer_key" class="form-control @error('answer_key') is-invalid @enderror">
                                    <option value="">---select---</option>
                                    @foreach(['A','B','C','D'] as $letter)
                                        <option value="{{ $letter }}" {{ old('answer_key', $item->answer_key) == $letter ? 'selected' : '' }}>
                                            {{ $letter }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('answer_key')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary" onclick="return confirm('Are you sure you want to update this item?');">Update</button>
                            <a href="{{ route('admin.assessments.items.index', $exam) }}" class="btn btn-default float-right">Back</a>
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

@section('plugins.Datatables', false)

@section('js')
    <script>console.log('Edit item loaded');</script>
@stop
