@extends('adminlte::page')

@php
    $title = "Train AI";
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
                <li class="breadcrumb-item"><a href="{{route('admin.ai.index')}}">AI</a></li>
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
                        <h3 class="card-title">Dashboard</h3>
                    </div>
                    <div class="card-body">
                        <button id="start-training-btn" class="btn btn-primary btn-lg"><i class="fas fa-fw fa-robot"></i> Start Training</button>
                        <br>
                        <br>
                        <!-- Live Status -->
                            <h2>AI Training Progress</h2>
                            <p>Live updates of the AI fine-tuning process will be shown below...</p>

                            <div class="">
                                <ul id="status-list"></ul>
                            </div>

                            <p id="notice" style="display: none; color: red; font-weight: bold;">
                                IMPORTANT: Do not close until the process is finished.
                            </p>
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

@section('js')
<script>
    $(document).ready(function() {
        $("#notice").hide();
        $("#start-training-btn").click(function() {
            $("#notice").show();
            $(this).prop("disabled", true).text("Training in Progress..."); // Disable button

            let eventSource = new EventSource("{{ route('admin.ai.train.start') }}");

            eventSource.onmessage = function(event) {
                $("#status-list").append("<li>" + event.data + "</li>");

                if (event.data.includes("AI Model successfully trained!")) {
                    eventSource.close();
                    setTimeout(function() {
                        window.location.href = "{{ route('admin.ai.train') }}";
                    }, 6000);
                }
            };

            eventSource.onerror = function() {
                eventSource.close();
                $("#status-list").append("<li><strong>Training process completed or encountered an error.</strong></li>");
                setTimeout(function() {
                        window.location.href = "{{ route('admin.ai.train') }}";
                    }, 6000);
                $("#start-training-btn").prop("disabled", false).text("Start Training"); // Re-enable button if error occurs
            };
        });
    });
</script>
@stop
