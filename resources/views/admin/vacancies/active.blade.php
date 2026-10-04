@extends('adminlte::page')

@php
    $title = "Active Positions";
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
                <li class="breadcrumb-item"><a href="{{ route('admin.vacancies.index') }}">Positions</a></li>
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
        <div class="card">
            <div class="card-header"><h3 class="card-title">Recruitment Progress by Vacancy</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Position</th><th>Cycle</th><th class="text-right">Untagged</th><th class="text-right">Assigned</th>
                            <th class="text-right">Station Pending</th><th class="text-right">Station Completed</th>
                            <th class="text-right">Division Pending</th><th class="text-right">Division Completed</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($vacancies as $vacancy)
                        @php $tagged = (int)$vacancy->tagged_applications_count; @endphp
                        <tr>
                            <td><a href="{{ route('admin.applications.vacancy.show',$vacancy) }}"><strong>{{ $vacancy->position_title }}</strong></a></td>
                            <td>{{ $vacancy->cycle }}</td>
                            <td class="text-right">{{ number_format($vacancy->untagged_applications_count) }}</td>
                            <td class="text-right">{{ number_format($tagged) }}</td>
                            <td class="text-right">{{ number_format($vacancy->station_pending_count) }}</td>
                            <td class="text-right">{{ number_format($vacancy->station_completed_count) }} <span class="text-muted">({{ $tagged ? number_format($vacancy->station_completed_count/$tagged*100,2).'%' : 'N/A' }})</span></td>
                            <td class="text-right">{{ number_format($vacancy->division_pending_count) }}</td>
                            <td class="text-right">{{ number_format($vacancy->division_completed_count) }} <span class="text-muted">({{ $tagged ? number_format($vacancy->division_completed_count/$tagged*100,2).'%' : 'N/A' }})</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No vacancies found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($vacancies->hasPages())<div class="card-footer">{{ $vacancies->links('pagination::bootstrap-4') }}</div>@endif
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
@stop
