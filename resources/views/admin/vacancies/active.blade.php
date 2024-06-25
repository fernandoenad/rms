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
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">List</h3>
                    </div>
                    <div class="card-body">
                        <table id="list" class="table table-sm table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Cycle</th>
                                    <th>Position title</th>
                                    <th class="text-right">Untagged applications</th>
                                    <th class="text-right">Tagged applications</th>
                                    <th class="text-right">Pending (SRC)</th>
                                    <th class="text-right">Completed (SRC)</th>
                                    <th class="text-right">Pending (DRC)</th>
                                    <th class="text-right">Completed (DRC)</th>
                                </tr>
                            </thead>
                            <tbody>
                                
                                @if(sizeof($vacancies) > 0)
                                    @php $untagged = 0; $tagged = 0; $src_pending = 0; $src_completed = 0; $drc_pending = 0; $drc_completed = 0; @endphp
                                    @foreach($vacancies as $vacancy)
                                        @php 
                                            $untagged += $vacancy->applications()->where('station_id', '=', -1)->get()->count();
                                            $tagged += $vacancy->applications()->where('station_id', '!=', -1)->get()->count();
                                        @endphp
                                        <tr>
                                            <td>{{$vacancy->id}}</td>
                                            <td>{{$vacancy->cycle}}</td>
                                            <td>
                                                <a href="{{ route('admin.applications.vacancy.show', $vacancy) }}">
                                                    {{$vacancy->position_title}}
                                                </a>
                                            </td>
                                            @php 
                                                $untagged = $vacancy->applications()->where('station_id', '=', -1)->get()->count();
                                                $tagged = $vacancy->applications()->where('station_id', '!=', -1)->get()->count();
                                            @endphp 
                                            <td class="text-right">{{ number_format($untagged,0) }}</td>
                                            <td class="text-right">{{ number_format($tagged,0) }}</td>
                                            @php 
                                                $assessments = $vacancy->applications()->join('assessments', 'assessments.application_id', '=', 'applications.id')->get(); 
                                                        
                                                if($assessments->count() > 0){
                                                    $src_pending = $assessments->where('status','=',1)->count();
                                                    $src_completed = $assessments->where('status','>=',2)->count();
                                                    $drc_pending = $assessments->where('status','=',2)->count();
                                                    $drc_completed =  $assessments->where('status','>=',3)->count();
                                                } else {
                                                    $src_pending = 0;
                                                    $src_completed = 0;
                                                    $drc_pending = 0;
                                                    $drc_completed = 0;
                                                }

                                            @endphp
                                            @if($tagged > 0)
                                                <td class="text-right">{{number_format($src_pending,0)}}</td>
                                                <td class="text-right">{{number_format($src_completed,0)}} <strong>({{number_format($src_completed/$tagged*100,2)}}%)</strong></td>
                                                <td class="text-right">{{number_format($drc_pending,0)}}</td>
                                                <td class="text-right">{{number_format($drc_completed,0)}} <strong>({{number_format($drc_completed/$tagged*100,2)}}%)</strong></td>
                                            @else 
                                                <td class="text-right">{{number_format($src_pending,0)}}</td>
                                                <td class="text-right">{{number_format($src_completed,0)}} (N/A)</td>
                                                <td class="text-right">{{number_format($drc_pending,0)}}</td>
                                                <td class="text-right">{{number_format($drc_completed,0)}} (N/A)</td>
                                            @endif
                                        </tr>
                                    @endforeach
                                    <!--
                                    <tr>
                                        <td></td>
                                        <td></td>
                                        <th class="text-right">Subtotal</th>
                                        <th class="text-right">{{ number_format($untagged,0) }}</th>
                                        <th class="text-right">{{ number_format($tagged,0) }}</th>
                                        <th class="text-right">{{ number_format($src_pending,0) }}</th>
                                        <th class="text-right">{{ number_format($src_completed,0) }}</th>
                                        <th class="text-right">{{ number_format($drc_pending,0) }}</th>
                                        <th class="text-right">{{ number_format($drc_completed,0) }}</th>
                                    </tr>
                                    <tr>
                                        <td></td>
                                        <td></td>
                                        <th class="text-right">Total</th>
                                        <th colspan="2" class="text-center">{{ number_format($untagged + $tagged, 0)}}</th>
                                        <th colspan="2" class="text-center">{{ number_format($src_pending + $src_completed, 0)}}</th>
                                        <th colspan="2" class="text-center">{{ number_format($drc_pending + $drc_completed, 0)}}</th>
                                    </tr>
                                    -->
                                @else
                                    <tr>
                                        <td colspan="9">0 vacancies found.</td>
                                    </tr>
                                @endif
                            </tbody>

                    </table>
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
            $("#list").DataTable({
            "responsive": true, "lengthChange": false, "autoWidth": false, "pageLength": 100, "ordering" : false,
            "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
            }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
        });
    </script>
@stop