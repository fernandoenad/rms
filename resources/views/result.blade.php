@extends('layouts.guest')

@section('title')
    {{ config('app.name', '') }} | Application Lookup Portal - Lookup Result
@endsection

@section('navTitle')
    {{ config('app.name', '') }}
@endsection

@section('main')
    <section class="content-header">
        <div class="container">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Lookup Result</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/">Recruitment Management System</a></li>
                        <li class="breadcrumb-item active">Lookup result</li>
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
                                value="{{$applicant_email}}"
                                id="applicant_email" 
                                class="@error('applicant_email') is-invalid @enderror">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-lg btn-default">
                                    <i class="fa fa-search"></i>
                                </button>
                            </div>
                        </div>
                        @error('applicant_email')
                            <div class="text-center">
                                <span class="text-danger"><small>{{ $message }}</small></span>
                            </div>
                        @enderror
                    </form>
                </div>
            </div>
        </div>

        <div class="container">
            <p></p>
        </div>

        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Search results... </h3>
                        </div>

                        <div class="card-body table-responsive p-0" style="height: 300px;">
                            <table class="table table-head-fixed text-nowrap">
                                <thead>
                                    <tr>
                                        <th>Application code</th>
                                        <th>Applicant name</th>
                                        <th>Position applied for</th>
                                        <th>Pertinent Doc</th>
                                    </tr>
                                </thead>
                                    <tbody>
                                    @if(sizeof($applications) > 0)
                                        @foreach($applications as $application)
                                        <tr>
                                            <td>
                                                <a href="{{route('guest.application.show',  $application)}}">{{$application->application_code}}</a>
                                            </td>
                                            <td>{{$application->applicant_fullname}}</td>
                                            <td>{{$application->position_applied}}</td>
                                            <td><a href="{{$application->pertinent_doc}}" target="_blank">View</a></td>
                                        </tr>
                                        @endforeach
                                        <tr>
                                            <td colspan="4">
                                                <em>Note: Use the corresponding applicant code and look it up 
                                                in the published IER accessible <a href="https://www.depedbohol.org/?page_id=49230">here</a>.</em>

                                            </td>
                                        </tr>
                                    @else
                                        <tr>
                                            <td colspan="4">No records found! Try different search parameters.</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection 