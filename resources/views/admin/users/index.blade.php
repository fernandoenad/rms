@extends('adminlte::page')

@php
    $title = "User Management";
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
                        <h3 class="card-title">Staff Accounts</h3>
                        <a type="button" class="btn btn-sm btn-primary float-right" href="{{route('admin.users.create')}}">
                            <i class="fas fa-plus"></i> New staff user
                        </a>
                        <a class="btn btn-sm btn-outline-secondary float-right mr-2"
                           href="{{ route('admin.applicant_accounts.index') }}">
                            <i class="fas fa-address-card mr-1" aria-hidden="true"></i>Applicant Management
                        </a>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="{{ route('admin.users.index') }}" class="form-row align-items-end mb-3">
                            <div class="col-sm-9 col-lg-10 mb-2">
                                <label for="staffSearch">Search staff by name or email</label>
                                <input id="staffSearch" type="search" name="q" class="form-control"
                                       maxlength="255" value="{{ $search ?? '' }}"
                                       placeholder="Search staff accounts">
                            </div>
                            <div class="col-sm-3 col-lg-2 mb-2">
                                <button class="btn btn-primary btn-block" type="submit">Search</button>
                            </div>
                        </form>
                        <p class="text-muted small mb-2">Staff accounts only. Showing up to 25 results per page.</p>
                        <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Email</th>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($users as $user)
                                        <tr>
                                            <td>{{$user->id}}</td>
                                            <td>{{$user->email}}</td>
                                            <td>{{$user->name}}</td>
                                            <td>{{ $user->role?->level == 1 ? 'Administrator' : 'Staff' }}</td>
                                            <td>{{ $user->role?->status == 1 ? 'Active' : 'Inactive' }}</td>
                                            <td>
                                                <a href="{{route('admin.users.reset', ['user' => $user])}}" class="btn btn-sm btn-primary" title="Reset password">
                                                    <span class="fas fa-fw fa-key"></span>
                                                </a>
                                                <a href="{{route('admin.users.edit', ['user' => $user])}}" class="btn btn-sm btn-warning" title="Modify">
                                                    <span class="fas primary fa-fw fa-edit"></span>
                                                </a>
                                                <a href="{{route('admin.users.delete', ['user' => $user])}}" class="btn btn-sm btn-danger" title="Delete">
                                                    <span class="fas fa-fw fa-trash"></span>
                                                </a>
                                                
                                            </td>
                                        </tr>
                                @empty
                                    <tr>
                                        <td colspan="6">No matching staff users found. Applicant-only accounts are managed separately.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                        </div>
                        <div class="mt-3">
                            {{ $users->links('pagination::bootstrap-4') }}
                        </div>
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

{{-- This staff directory uses server-side pagination instead of loading all rows
     into the browser or initializing a client-side DataTable. --}}