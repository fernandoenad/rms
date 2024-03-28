@extends('adminlte::page')

@section('title', 'RMS v1.0 | Dashboard')

@section('content_header')
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1 class="m-0">Dashboard</h1>
        </div>
        <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item active">Dashboard</li>
            </ol>
        </div>
    </div>
@stop

@section('content')
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible auto-close">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            {{ session('error') }}
        </div>
    @endif
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-4">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3>{{ $inquiries->count()}}</h3>
                        <p>Inquiries</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-fw fa-paper-plane"></i>
                    </div>
                    <a href="{{route('admin.inquiries.index')}}" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>{{ $applications->count()}}</h3>
                        <p>Applications</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-fw fa-folder"></i>
                    </div>
                    <a href="{{route('admin.applications.index')}}" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="small-box bg-primary">
                    <div class="inner">
                        <h3>{{ $users->count()}}</h3>
                        <p>Users</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-fw fa-users"></i>
                    </div>
                    <a href="{{route('admin.users.index')}}" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
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

@section('js')
    <script> console.log('Hi!'); </script>
@stop