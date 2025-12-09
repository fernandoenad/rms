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

            @if (session('status_creation'))
                <div class="col-lg-6 offset-lg-3">
                    <div class="position-relative p-3 bg-gray" style="height: 180px">
                        <div class="ribbon-wrapper ribbon-xl">
                            <div class="ribbon bg-success text-lg">
                                Success
                            </div>
                        </div>
                        {{ session('status_creation') }}<br><br>
                        <h4>Your application code is <strong>{{$application->application_code}}</strong></h4>
                        <p>
                            Make sure to print the cover page by clicking <a href="#" onclick="window.print()">here</a>.<br>
                            This should be the first page of your document compilation. 
                        </p>

                    </div>
                </div><br>
            @endif
            @if (session('status_assessment'))
                <div class="alert alert-info alert-dismissible auto-close">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    {{ session('status_assessment') }}
                </div>
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
                            <p class="text-muted text-center">{{$application->vacancy->position_title}}<br>
                            @php  $station = App\Models\Station::find($application->station_id); @endphp
                            {{ isset($station) ? $station->name : ($application->station_id == 0 ? 'Division' : 'Untagged') }}
                            </p>
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
                                        <a class="nav-link" href="#my-queries" data-toggle="tab">Updates @if($application->vacancy->office_level == 0)/Queries @endif</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" href="#my-assessments" data-toggle="tab">Assessment</a>
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
                                                    <td>{{ $application->getAddress() }}</td>                                                   
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
                                                    <th>Civil Status</th>
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
                                                @if(isset($application->assessment))
                                                    @php 
                                                        $assessment_scores = json_decode($application->assessment->assessment);
                                                        $template = App\Models\Template::find($application->vacancy->template_id);
                                                        $assessment_template = json_decode($template->template, true);
                                                    @endphp 

                                                    @foreach($assessment_scores as $key => $value)
                                                        <tr>
                                                            <th>{{ $key }}</th>
                                                            <td>
                                                                {{ $application->assessment->status == 3 ? $value : '-' }}
                                                            </td>
                                                        </tr>
                                                    @endforeach

                                                    <tr class="bg-info">
                                                        <th >Final RQA Score</th>
                                                        <td>{{ $application->assessment->status == 3 ? $application->assessment->score : '-' }}</td>
                                                    </tr>
                                                
                                                    <tr>
                                                        <td>
                                                            Status<br>
                                                            Initial Assessment<br>
                                                            Comparative Assessment
                                                        </td>
                                                        <td>
                                                            {{$application->assessment->get_status()}}<br>
                                                            {{$application->assessment->created_at->format('M d, Y h:ia')}}<br>
                                                            {{$application->assessment->status == 3 ? $application->assessment->updated_at->format('M d, Y h:ia') : 'TBA'}}
                                                        </td>                                                  
                                                    </tr>
                                                @else 
                                                    <tr>
                                                        <td>
                                                            Status<br>
                                                        </td>
                                                        <td>                                                            
                                                            <span class="badge bg-warning">IMPORTANT</span>
                                                            <br>
                                                            If you already have submitted to your Pertinent Documents, <br>
                                                            please contact School (teaching position) / <br>
                                                            SDO-HR Office (non-teaching, school administration, and  <br>
                                                            teaching-related positions) to have it marked COMPLETED. 
                                                        </td>                                                  
                                                    </tr>
                                                @endif
                                            
                                     
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="chart tab-pane" id="my-assessments">
                                    <div class="card-body table-responsive p-0">
                                        <table class="table table-hover text-nowrap">
                                            <thead>
                                                <tr>
                                                    <th>Exam</th>
                                                    <th>Duration</th>
                                                    <th>Score</th>
                                                    <th>Status</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php $hasAssessmentRel = $application->assessment !== null; @endphp
                                                @forelse($exams as $exam)
                                                    @php
                                                        $attempt = $exam->attempts->first();
                                                        $submitted = $attempt && $attempt->status == 2;
                                                    @endphp
                                                    <tr>
                                                        <td>{{ $exam->title }}</td>
                                                        <td>{{ $exam->duration }} min</td>
                                                        <td>
                                                            @if(isset($attempt) && $attempt->status == 2)
                                                                @php
                                                                    $total = $exam->writtenExams->where('status',1)->count();
                                                                    $answers = $attempt->answers;
                                                                    $correct = 0;
                                                                    foreach($exam->writtenExams as $item){
                                                                        $a = $answers->firstWhere('written_exam_id', $item->id);
                                                                        if($a && strtoupper($a->selected_option) == strtoupper($item->answer_key)){
                                                                            $correct++;
                                                                        }
                                                                    }
                                                                    $scorePct = $total > 0 ? round(($correct / $total) * 100, 2) : 0;
                                                                @endphp
                                                                {{ $correct }} / {{ $total }} ({{ $scorePct }}%)
                                                            @else
                                                                -
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if($submitted)
                                                                Submitted
                                                            @elseif($attempt && $attempt->status == 1)
                                                                In progress
                                                            @else
                                                                Not started
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if(!$hasAssessmentRel)
                                                                <button class="btn btn-sm btn-secondary" disabled>Not eligible</button>
                                                            @elseif($submitted)
                                                                <button class="btn btn-sm btn-success" disabled>Completed</button>
                                                            @else
                                                                <form method="post" action="{{ route('guest.assessments.attempts.start', [$application, $exam]) }}" class="d-inline">
                                                                    @csrf
                                                                    <div class="input-group input-group-sm">
                                                                        <input type="text" name="enrollment_key" class="form-control" placeholder="Enrollment key" required>
                                                                        <div class="input-group-append">
                                                                            <button type="submit" class="btn btn-primary" onclick="return confirm('This is a single-attempt test. Countdown starts after OK. Proceed?');">
                                                                                {{ $attempt ? 'Continue' : 'Take test' }}
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </form>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="5">No active assessments for this position.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="chart tab-pane" id="my-next">
                                <div class="card-body table-responsive p-0">
                                        <table class="table table-hover table-borderless">
                                            <tbody>
                                                <tr>
                                                    <td>
                                                        Follow the guidelines outlined in the memo regarding this vacancy at the DepEd Bohol website at 
                                                            <a href="https://www.depedbohol.org">
                                                            https://www.depedbohol.org</a>.
                                                        <br>
                                                        <br>
                                                        <em>Take Note:</em>
                                                        <ol> 
                                                            <li><strong>This is already DONE!</strong> Step 1: Online submission of intents.</li>
                                                            <li><strong>Your NEXT step!</strong> Step 2: Submission of Pertinent Documents 
                                                                (e.g., Letter of intents and other documents listed in the Checklist of 
                                                                Requirements: Annex A/C)to your preferred school (for teaching position) / SDO-HR Office
                                                                (non-teaching, school administration, and teaching-related positions).</li>
                                                        </ol>


                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="chart tab-pane p-0" id="my-queries">
                                    <div class="direct-chat-messages" style="min-height: 350px">
                                        <div class="direct-chat-msg left">
                                            <div class="direct-chat-infos clearfix">
                                                <span class="direct-chat-name float-left">{{ config('app.name', '') }} AI</span>
                                                <span class="direct-chat-timestamp float-left"></span>
                                            </div>
                                            <img class="direct-chat-img" src="{{url('/')}}/images/user.png" alt="user image">
                                            <div class="direct-chat-text">
                                                Application was created on {{ $application->created_at->format('M d, Y @ h:ia') }}.
                                            </div>
                                        </div>
                                        <!--
                                        <div class="direct-chat-msg left">
                                            <div class="direct-chat-infos clearfix">
                                                <span class="direct-chat-name float-left">{{ config('app.name', '') }} System</span>
                                                <span class="direct-chat-timestamp float-left"></span>
                                            </div>
                                            
                                            <img class="direct-chat-img" src="{{url('/')}}/images/user.png" alt="user image">
                                            
                                            <div class="direct-chat-text">
                                                Send your query here but make sure that it is substantial... 
                                            </div>
                                        </div>
                                        -->
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
                                    
                                    @if($application->vacancy->office_level == 0)
                                    <form class="form-horizontal" method="post" action="{{ route('guest.applications.inquire2', $application) }}">
                                        @csrf 
                                        @method('post')
                                        <div class="input-group input-group-sm mb-0">
                                            <textarea class="form-control form-control-sm" name="message" id="message" required 
                                                placeholder="Query message"></textarea>
                                            <div class="input-group-append">
                                                <button type="submit"  class="btn btn-danger" disabled>Send</button>
                                            </div>
                                        </div>
                                    </form>
                                    @endif
                                    
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
