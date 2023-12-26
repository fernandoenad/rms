@extends('layouts.guest')

@section('title')
    {{ config('app.name', '') }} | Application Lookup Portal - Lookup Application Code
@endsection

@section('navTitle')
    {{ config('app.name', '') }}
@endsection

@section('main')
    <section class="content-header">
        <div class="container">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Lookup Application Code</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item active">Recruitment Management System</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container">
            <h3 class="text-center display-3">Search</h3>
            <div class="row">
                <div class="col-md-6 offset-md-3">
                    <form method="post" action="{{route('guest.lookup')}}">
                        @csrf
                        @method('post')
                        <div class="input-group">
                            <input type="search" 
                                class="form-control form-control-lg" 
                                name="applicant_email" 
                                placeholder="Type email address used to apply..." 
                                id="applicant_email" 
                                value="{{ old('applicant_email') }}"
                                class="@error('applicant_email') is-invalid @enderror">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-lg btn-default">
                                    <i class="fa fa-search"></i>
                                </button>
                            </div>
                        </div>
                        @error('applicant_email')
                            <div class="text-center" >
                                <span class="text-danger"><small>{{ $message }}</small></span>
                            </div>
                        @enderror
                    </form>
                </div>
            </div>

            <div class="container">
                <p class="text-center"></p>
                <p class="text-center">Input the email address used during the application</p>
            </div>
        </div>
    </section>
@endsection
