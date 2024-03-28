@extends('layouts.guest')

@section('title')
    {{ config('app.name', '') }} | Lookup Result
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
                        <li class="breadcrumb-item"><a href="{{route('guest.index')}}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{route('guest.applications.index')}}">Lookup</a></li>
                        <li class="breadcrumb-item active">Result</li>
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
                    <form method="post" action="{{route('guest.applications.lookup')}}">
                        @csrf
                        @method('post')
                        <div class="input-group">
                            <input type="search" 
                                class="form-control form-control-lg" 
                                name="email" 
                                placeholder="Type email address used to apply..." 
                                value="{{ $email }}"
                                id="email" 
                                class="@error('email') is-invalid @enderror">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-lg btn-default">
                                    <i class="fa fa-search"></i>
                                </button>
                            </div>
                        </div>
                        @error('email')
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
                                        <th>Applicant name</th>
                                        <th>Position applied for</th>
                                        <th width="5%">Application code</th>
                                        <th>Cycle</th>
                                        <th>Office</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                    <tbody>
                                    @if(sizeof($applications) > 0)
                                        @foreach($applications as $application)
                                        <tr>
                                            
                                            <td>{{$application->getFullname()}}</td>
                                            <td> {{$application->vacancy->position_title}}</td>
                                            <td>{{$application->application_code}}</td>
                                            <td>{{$application->vacancy->cycle}}</td>
                                            <td>{{$application->vacancy->getOffice()}}</td>
                                            <td>
                                                <a href="{{route('guest.applications.show',  $application)}}" 
                                                    title="Click to view" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                        @endforeach
                                        <tr>
                                            <td colspan="6">
                                                <h5>Notes:</h5>
                                                <strong>Non-teaching/Teaching-related/School Administrator Applicants:</strong><em> Use the corresponding applicant code and look it up 
                                                in the published IER accessible <a href="https://www.depedbohol.org/?page_id=49230">here</a>.</em><br>
                                                <strong>Teaching Applicants</strong><em>: Refer to the IER posting by the field office.</em><br>

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