@extends('layouts.guest')

@section('title')
    {{ config('app.name', '') }} | Application Lookup Portal - Application Details
@endsection

@section('navTitle')
    {{ config('app.name', '') }}
@endsection

@section('main')
    <section class="content-header">
        <div class="container">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Application Details</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/">Recruitment Management System</a></li>
                        <li class="breadcrumb-item active">{{$application->application_code}} / Application details</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container">
            <div class="row">
                <div class="col-md-3">
                    <!-- Profile Image -->
                    <div class="card card-primary card-outline">
                        <div class="card-body box-profile">
                            <div class="text-center">
                                <img class="profile-user-img img-fluid img-circle"
                                    src="{{url('/')}}/images/bohol.png"
                                    alt="User profile picture">
                            </div>
                            <h3 class="profile-username text-center">{{$application->applicant_fullname}}</h3>
                            <p class="text-muted text-center">{{$application->position_applied}}</p>
                        </div>
                        <!-- /.card-body -->
                    </div>
                    <!-- /.card -->
                    <!-- About Me Box -->
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">About</h3>
                        </div>
                        <!-- /.card-header -->
                        <div class="card-body">
                            <strong><i class="fas fa-id-card mr-1"></i> Application ID</strong> 
                            <p class="text-muted">{{$application->application_code}}</p>
                            <hr>
                            <strong><i class="fas fa-at mr-1"></i> Email</strong>
                            <p class="text-muted">{{$application->applicant_email}}</p>
                            <hr>
                            <strong><i class="fas fa-file mr-1"></i> Document</strong>
                            <p class="text-muted"><a href="{{$application->pertinent_doc}}">View</a></p>
                        </div>
                        <!-- /.card-body -->
                    </div>
                    <!-- /.card -->
                </div>
                <!-- /.col -->
                <div class="col-md-9">
                    <div class="card card-default direct-chat direct-chat-primary">
                        <div class="card-header p-2">
                            <h7>Inquiry History</h7>
                        </div>
                        <!-- /.card-header -->
                        <div class="card-body">
                            <div class="direct-chat-messages" style="min-height: 430px">
                                @if(sizeof($applicationInquiries) > 0)
                                    @foreach($applicationInquiries as $applicationInquiry)
                                        <!-- Post -->
                                        <div class="direct-chat-msg {{$applicationInquiry->author == $application->applicant_fullname ?'right':'left'}}">
                                            <div class="direct-chat-infos clearfix">
                                                <span class="direct-chat-name float-{{$applicationInquiry->author == $application->applicant_fullname ?'right':'left'}}">{{$applicationInquiry->author}}</span>
                                                <span class="direct-chat-timestamp float-{{$applicationInquiry->author == $application->applicant_fullname ?'left':'right'}}">{{$applicationInquiry->created_at->setTimezone('Asia/Shanghai')->toDayDateTimeString();}}</span>
                                            </div>
                                            <img class="direct-chat-img" src="{{url('/')}}/images/user.png" alt="user image">
                                            <div class="direct-chat-text">
                                                {!!nl2br($applicationInquiry->message)!!}
                                            </div>
                                        </div>
                                        <!-- /.post -->
                                    @endforeach
                                @endif
                            </div>
                        </div>
                        <!-- /.card-body -->
                        <div class="card-footer">
                            <form class="form-horizontal" method="post" action="{{route('guest.application.store', $application)}}">
                                @csrf 
                                @method('post')
                                <div class="input-group input-group-sm mb-0">
                                    <textarea class="form-control form-control-sm" name="message" required 
                                        {{$diffInDays >= 10 ? "readonly" : ""}}
                                        placeholder="Inquiry message">{{$diffInDays >= 10 ? "Sending queries are no longer allowed past 10 days of posting." : ""}}</textarea>
                                    <div class="input-group-append">
                                        <button type="submit"  class="btn btn-danger" {{$diffInDays >= 10 ? "disabled" : ""}}>Send</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /.row -->
        </div><!-- /.container-fluid -->
    </section>
@endsection 