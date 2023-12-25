@extends('layouts.guest')

@section('title')
    {{ config('app.name', '') }} | Application Lookup Portal - Error 500
@endsection

@section('navTitle')
    Recruitment Management System ({{ config('app.name', '') }})
@endsection

@section('main')
    <section class="content-header">
        <div class="container">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Error 500</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/">Recruitment Management System</a></li>
                        <li class="breadcrumb-item active">Error 500</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="error-page">
            <h2 class="headline text-danger"> 500</h2>
            <div class="error-content">
            <h3><i class="fas fa-exclamation-triangle text-danger"></i> Oops! Internal server error.</h3>
            <p>
                An unexpected condition was encountered.
                Meanwhile, you may <a href="{{url()->previous()}}">return to dashboard</a>.
            </p>
        </div>
    </section>
@endsection