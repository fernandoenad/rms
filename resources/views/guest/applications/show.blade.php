@extends('layouts.guest')

@section('title')
    {{ config('app.name', '') }} | Application Details
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
                        <li class="breadcrumb-item"><a href="{{route('guest.index')}}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{route('guest.applications.my')}}">My Applications</a></li>
                        <li class="breadcrumb-item active">Details</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container">
            @if (session('status_inquiry'))
                <div class="alert alert-success alert-dismissible auto-close">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    {{ session('status_inquiry') }}
                </div>
            @endif

            @if (session('status'))
                <div class="col-lg-6 offset-lg-3">
                    <div class="position-relative p-3 bg-gray" style="height: 180px">
                        <div class="ribbon-wrapper ribbon-xl">
                            <div class="ribbon bg-success text-lg">
                                Success
                            </div>
                        </div>
                        {{ session('status') }}<br><br>
                        <h4>Your application code is <strong>{{$application->application_code}}</strong></h4>
                        <p>
                            Make sure to print the cover page by clicking <a href="#" onclick="window.print()">here</a>.<br>
                            This should be the first page of your document compilation. 
                        </p>

                    </div>
                </div><br>
            @endif
            <div class="row">
                <div class="col-md-4">
                    <!-- Profile Image -->
                    <div class="card card-primary card-outline">
                        <div class="card-body box-profile">
                            <div class="text-center">
                                <img class="profile-user-img img-fluid img-circle"
                                    src="{{url('/')}}/images/bohol.png"
                                    alt="User profile picture">
                            </div>
                            <h3 class="profile-username text-center">{{$application->getFullname()}}</h3>
                            <p class="text-muted text-center">{{$application->vacancy->position_title}}</p>
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
                            <strong><i class="fas fa-hashtag mr-1"></i> Application code</strong>
                            <p class="text-muted">
                                <a href="#" onclick="window.print()" title="Click here to print the cover page.">
                                    {{$application->application_code}} 
                                </a><br>
                            </p>
                            <hr>
                            <strong><i class="fas fa-at mr-1"></i> Email</strong>
                            <p class="text-muted">{{$application->email}}</p>
                            <hr>
                            <strong><i class="fas fa-phone mr-1"></i> Phone</strong>
                            <p class="text-muted">{{$application->phone}}</p>
                        </div>
                        <!-- /.card-body -->
                    </div>
                    <!-- /.card -->
                </div>
                <!-- /.col -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-user mr-1"></i>
                                Details
                            </h3>
                            <div class="card-tools">
                                <ul class="nav nav-pills ml-auto">
                                    
                                    <li class="nav-item">
                                        <a class="nav-link" href="#my-profile" data-toggle="tab">Profile</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" href="#my-next" data-toggle="tab">Next Steps</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" href="#my-scores" data-toggle="tab">Scores</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" href="#my-queries" data-toggle="tab">Queries</a>
                                    </li>
                                    
                                </ul>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="tab-content p-0">
                                <div class="chart tab-pane" id="my-profile">
                                    <div class="card-body table-responsive p-0">
                                        <table class="table table-hover text-nowrap table-borderless">
                                            <tbody>
                                                <tr>
                                                    <th width="20%">Name</th>
                                                    <td>{{ $application->getFullname() }}</td>
                                                </tr>
                                                <tr>
                                                    <th>Address</th>
                                                    <td>{{ $application->getAddress() }}</td>                                                   </td>
                                                </tr>
                                                <tr>
                                                    <th>Age</th>
                                                    <td>{{ $application->age }}</td>
                                                </tr>
                                                <tr>
                                                    <th>Gender</th>
                                                    <td>{{ $application->gender }}</td>
                                                </tr>
                                                <tr>
                                                    <th>Gender</th>
                                                    <td>{{ $application->civil_status }}</td>
                                                </tr>
                                                <tr>
                                                    <th>Religion</th>
                                                    <td>{{ $application->religion }}</td>
                                                </tr>
                                                <tr>
                                                    <th>Disability</th>
                                                    <td>{{ $application->disability }}</td>
                                                </tr>
                                                <tr>
                                                    <th>Ethnic group</th>
                                                    <td>{{ $application->ethnic_group }}</td>
                                                </tr>
                                                <tr>
                                                    <td colspan="2">
                                                        <em>For corrections on profile entries, have it requested 
                                                            to the school where you're applying to.</em>
                                                    </td>
                                                </tr>
                                     
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="chart tab-pane" id="my-scores">
                                    <div class="card-body table-responsive p-0">
                                        <table class="table table-hover text-nowrap table-borderless">
                                            <tbody>
                                                <tr>
                                                    <th width="20%">Education</th>
                                                    <td>0</td>
                                                </tr>
                                                <tr>
                                                    <th>Training</th>
                                                    <td>0</td>                                                  
                                                </tr>
                                                <tr>
                                                    <th>Experience</th>
                                                    <td>0</td>                                                  
                                                </tr>
                                                <tr>
                                                    <th>COI (Teaching Demo)</th>
                                                    <td>0</td>                                                  
                                                </tr>
                                                <tr>
                                                    <th>NCOI (TRF)</th>
                                                    <td>0</td>                                                  
                                                </tr>
                                                <tr>
                                                    <td>
                                                        Initial Assessment<br>
                                                        Comparative Assessment
                                                    </td>
                                                    <td>
                                                        yyyy-mm-dd<br>
                                                        yyyy-mm-dd
                                                    </td>                                                  
                                                </tr>

                                     
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="chart tab-pane" id="my-next">
                                <div class="card-body table-responsive p-0">
                                        <table class="table table-hover table-borderless">
                                            <tbody>
                                                <tr>
                                                    <th width="20%">Teaching</th>
                                                    <td>
                                                        <em>Fresh applicants:</em>
                                                        <ol> 
                                                            <li>Follow the guidelines outlined in the memo regarding this vacancy. </li>
                                                            <li>Organize two folders: one containing the original documents and another containing the photocopied documents.</li>
                                                            <li>Print the profile page and affix it to the front of both folders.</li>
                                                            <li>Visit the school where you intend to apply for an initial assessment.</li>
                                                            <li>Make sure to bring along with you the folder containing the original documents before leaving the school.</li>
                                                        </ol>

                                                        <em>Old applicants:</em>
                                                        <ol> 
                                                            <li>Follow the guidelines outlined in the memo regarding this vacancy. </li>
                                                            <li>Print the page displaying your name from last year's ranking list.</li>
                                                            <li>Organize two folders: one containing the original documents and another containing the photocopied documents.</li>
                                                            <li>Print the profile page and affix it to the front of both folders.</li>
                                                            <li>Visit the school where you intend to apply for an initial assessment.</li>
                                                            <li>Remember to bring the folder with the original documents with you when leaving the school premises.</li>
                                                        </ol>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th>Non-Teaching/ <br>Teaching-Related/ <br>School Administrators</th>
                                                    <td>
                                                        <ol> 
                                                            <li>Follow the guidelines outlined in the memo regarding this vacancy. </li>
                                                        </ol>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="chart tab-pane p-0" id="my-queries">
                                    <div class="direct-chat-messages" style="min-height: 350px">
                                        <div class="direct-chat-msg left}}">
                                            <div class="direct-chat-infos clearfix">
                                                <span class="direct-chat-name float-left">{{ config('app.name', '') }} System</span>
                                                <span class="direct-chat-timestamp float-left"></span>
                                            </div>
                                            <img class="direct-chat-img" src="{{url('/')}}/images/user.png" alt="user image">
                                            <div class="direct-chat-text">
                                                Send your query here but make sure that it is substantial...
                                            </div>
                                        </div>
                                        @if(sizeof($applicationInquiries) > 0)
                                            @foreach($applicationInquiries as $applicationInquiry)
                                                <!-- Post -->
                                                <div class="direct-chat-msg {{$applicationInquiry->author == $application->getFullname() ?'right':'left'}}">
                                                    <div class="direct-chat-infos clearfix">
                                                        <span class="direct-chat-name float-{{$applicationInquiry->author == $application->getFullname() ?'right':'left'}}">{{$applicationInquiry->author}}</span>
                                                        <span class="direct-chat-timestamp float-{{$applicationInquiry->author == $application->getFullname() ?'left':'right'}}">{{$applicationInquiry->created_at->setTimezone('Asia/Shanghai')->toDayDateTimeString();}}</span>
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
                                    <form class="form-horizontal" method="post" action="{{route('guest.applications.inquire', $application)}}">
                                        @csrf 
                                        @method('post')
                                        <div class="input-group input-group-sm mb-0">
                                            <textarea class="form-control form-control-sm" name="message" required 
                                                {{$diffInDays > 100 ? "readonly" : ""}}
                                                placeholder="Query message">{{$diffInDays > 100 ? "Sending queries are no longer allowed past 10 days of posting." : ""}}</textarea>
                                            <div class="input-group-append">
                                                <button type="submit"  class="btn btn-danger" {{$diffInDays > 100 ? "disabled" : ""}}>Send</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /.row -->
        </div><!-- /.container-fluid -->
    </section>
@endsection 

@section('js')
<script>
    // Store the active tab in local storage
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        localStorage.setItem('activeTab', $(e.target).attr('href'));
    });

    // Get the active tab from local storage on page load
    var activeTab = localStorage.getItem('activeTab');
    if (activeTab) {
    } else {
        activeTab = "#my-profile";
    }

    $(`a[href="${activeTab}"]`).addClass(' active');
    $(activeTab).addClass(' active');

    console.log(activeTab);
</script>
@endsection