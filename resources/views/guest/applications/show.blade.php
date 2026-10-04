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
                                    <div class="alert alert-light border mb-3">
                                        <div class="d-flex align-items-start">
                                            <i class="fas fa-clipboard-check text-primary mt-1 mr-2"></i>
                                            <div>
                                                <strong>Assessment Center</strong>
                                                <div class="small text-muted">
                                                    Review the status of each assessment below. Before a new test starts, RMS will show the test rules, timing, submission requirements, and important reminders. Your timer begins only after you confirm and click the final start button.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body table-responsive p-0">
                                        <table class="table table-hover text-nowrap">
                                            <thead>
                                                <tr>
                                                    <th>Exam</th>
                                                    <th>Duration</th>
                                                    <th>Score</th>
                                                    <th>Status</th>
                                                    <th>Note</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php $hasAssessmentRel = $application->assessment !== null; @endphp

                                                @foreach($exams as $exam)
                                                    @php
                                                        $attempt = $exam->attempts->first();
                                                        $submitted = $attempt && $attempt->status == 2;
                                                        $scoresReleased = !$exam->assessmentGroup || $exam->assessmentGroup->scoresAreReleased();
                                                        $examOpen = (!$exam->start_date || now()->gte($exam->start_date))
                                                            && (!$exam->end_date || now()->lt($exam->end_date));
                                                        $examUpcoming = $exam->start_date && now()->lt($exam->start_date);
                                                    @endphp
                                                    <tr>
                                                        <td>
                                                            <strong>{{ $exam->assessmentGroup?->title ?: $exam->title }}</strong>
                                                            <div class="small text-muted">
                                                                Written Exam
                                                                @if($exam->assessmentGroup && $exam->set_code)
                                                                    · Assigned set: {{ $exam->set_code }}
                                                                @endif
                                                            </div>
                                                        </td>
                                                        <td>{{ $exam->duration }} min</td>
                                                        <td>
                                                            @if($submitted)
                                                                @if($scoresReleased)
                                                                    {{ $attempt->correct_answers ?? '-' }} / {{ $attempt->total_items ?? '-' }}
                                                                    @if($attempt->percentage !== null) ({{ $attempt->percentage }}%) @endif
                                                                @else
                                                                    <span class="text-muted">Pending official release</span>
                                                                @endif
                                                            @else
                                                                -
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if($submitted)
                                                                <span class="badge badge-success">Submitted</span>
                                                            @elseif($attempt && $attempt->status == 1)
                                                                <span class="badge badge-warning">In progress</span>
                                                            @elseif($examUpcoming)
                                                                <span class="badge badge-secondary">Scheduled</span>
                                                            @elseif($examOpen)
                                                                <span class="badge badge-info">Available</span>
                                                            @else
                                                                <span class="badge badge-secondary">Closed</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if($submitted && $attempt && $attempt->auto_submitted)
                                                                <button type="button" class="btn btn-link p-0" data-toggle="modal" data-target="#attemptNoteModal{{ $attempt->id }}" title="View auto-submit note">
                                                                    <i class="fas fa-info-circle text-info"></i>
                                                                </button>
                                                            @else
                                                                -
                                                            @endif
                                                        </td>
                                                        <td class="text-nowrap">
                                                            @if(!$hasAssessmentRel)
                                                                <button class="btn btn-sm btn-secondary" disabled>Not eligible</button>
                                                            @elseif($submitted)
                                                                <button class="btn btn-sm btn-success" disabled>Completed</button>
                                                            @elseif($attempt && $attempt->status == 1)
                                                                <form method="post" action="{{ route('guest.assessments.attempts.start', [$application, $exam]) }}" class="d-inline">
                                                                    @csrf
                                                                    <button type="submit" class="btn btn-sm btn-primary">Continue</button>
                                                                </form>
                                                            @elseif(!$examOpen)
                                                                <button class="btn btn-sm btn-secondary" disabled>{{ $examUpcoming ? 'Not open yet' : 'Closed' }}</button>
                                                            @else
                                                                <button type="button" class="btn btn-sm btn-primary"
                                                                    data-toggle="modal" data-target="#writtenStartModal{{ $exam->id }}">
                                                                    <i class="fas fa-play-circle mr-1"></i> Take Written Test
                                                                </button>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach

                                                @foreach($skillTests as $skillTest)
                                                    @php
                                                        $skillAttempt = $skillTest->attempts->first();
                                                        $skillSubmitted = $skillAttempt && $skillAttempt->status == 2;
                                                        $skillVoided = $skillAttempt && $skillAttempt->status == 3;
                                                        $skillScoreReleased = $skillTest->scoresAreReleased();
                                                        $skillOpen = $skillTest->status
                                                            && $skillTest->start_date
                                                            && $skillTest->end_date
                                                            && now()->gte($skillTest->start_date)
                                                            && now()->lt($skillTest->end_date);
                                                        $skillUpcoming = $skillTest->status
                                                            && $skillTest->start_date
                                                            && now()->lt($skillTest->start_date);
                                                    @endphp
                                                    <tr>
                                                        <td>
                                                            <strong>{{ $skillTest->skillTestGroup?->title ?: $skillTest->title }}</strong>
                                                            <div class="small text-muted">
                                                                Skills Test
                                                                @if($skillTest->skill_test_group_id && $skillTest->set_code)
                                                                    @if($skillAttempt)
                                                                        · Assigned set: {{ $skillTest->set_code }}
                                                                    @elseif($skillOpen)
                                                                        · Current set: {{ $skillTest->set_code }}
                                                                    @elseif($skillUpcoming)
                                                                        · Scheduled set: {{ $skillTest->set_code }}
                                                                    @endif
                                                                @endif
                                                            </div>
                                                        </td>
                                                        <td>{{ $skillTest->duration }} min</td>
                                                        <td>
                                                            @if($skillSubmitted)
                                                                @if($skillScoreReleased && $skillAttempt->final_score !== null)
                                                                    {{ number_format((float)$skillAttempt->final_score, 2) }} / 100
                                                                @elseif($skillScoreReleased)
                                                                    <span class="text-muted">Pending human evaluation</span>
                                                                @else
                                                                    <span class="text-muted">Pending official release</span>
                                                                @endif
                                                            @else
                                                                -
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if($skillVoided)
                                                                <span class="badge badge-secondary">Voided</span>
                                                            @elseif($skillSubmitted)
                                                                <span class="badge badge-success">Submitted</span>
                                                            @elseif($skillAttempt)
                                                                <span class="badge badge-warning">In progress</span>
                                                            @elseif($skillUpcoming)
                                                                <span class="badge badge-secondary">Scheduled</span>
                                                            @elseif($skillOpen)
                                                                <span class="badge badge-info">Available</span>
                                                            @else
                                                                <span class="badge badge-secondary">Closed</span>
                                                            @endif
                                                        </td>
                                                        <td>-</td>
                                                        <td class="text-nowrap">
                                                            @if(!$hasAssessmentRel)
                                                                <button class="btn btn-sm btn-secondary" disabled>Not eligible</button>
                                                            @elseif($skillVoided)
                                                                <button class="btn btn-sm btn-secondary" disabled>Retake required</button>
                                                            @elseif($skillSubmitted)
                                                                <button class="btn btn-sm btn-success" disabled>Completed</button>
                                                            @elseif($skillAttempt)
                                                                <a href="{{ route('guest.skills.attempts.take',$skillAttempt) }}" class="btn btn-sm btn-primary">Continue</a>
                                                            @elseif($skillOpen)
                                                                <button type="button" class="btn btn-sm btn-primary"
                                                                    data-toggle="modal" data-target="#skillStartModal{{ $skillTest->id }}">
                                                                    <i class="fas fa-tools mr-1"></i> Take Skills Test
                                                                </button>
                                                            @else
                                                                <button class="btn btn-sm btn-secondary" disabled>
                                                                    {{ $skillUpcoming ? 'Not open yet' : 'Unavailable' }}
                                                                </button>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach

                                                @if($exams->isEmpty() && $skillTests->isEmpty())
                                                    <tr>
                                                        <td colspan="6" class="text-muted">No active assessments for this position.</td>
                                                    </tr>
                                                @endif
                                            </tbody>
                                        </table>
                                        {{-- Pre-test instruction modals: the start POST is intentionally inside
                                             the modal so merely opening/reading the instructions never starts a timer. --}}
                                        @foreach($exams as $exam)
                                            @php
                                                $attempt = $exam->attempts->first();
                                                $examOpen = (!$exam->start_date || now()->gte($exam->start_date))
                                                    && (!$exam->end_date || now()->lt($exam->end_date));
                                            @endphp
                                            @if(!$attempt && $hasAssessmentRel && $examOpen)
                                                <div class="modal fade" id="writtenStartModal{{ $exam->id }}" tabindex="-1" aria-labelledby="writtenStartLabel{{ $exam->id }}" aria-hidden="true">
                                                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                                        <div class="modal-content">
                                                            <div class="modal-header bg-light">
                                                                <div>
                                                                    <h5 class="modal-title mb-0" id="writtenStartLabel{{ $exam->id }}">
                                                                        <i class="fas fa-file-alt text-primary mr-1"></i>
                                                                        Before You Start the Written Test
                                                                    </h5>
                                                                    <small class="text-muted">{{ $exam->assessmentGroup?->title ?: $exam->title }}</small>
                                                                </div>
                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="row mb-3">
                                                                    <div class="col-md-4 mb-2">
                                                                        <div class="border rounded p-3 h-100">
                                                                            <small class="text-muted d-block">Time limit</small>
                                                                            <strong>{{ $exam->duration }} minutes</strong>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-4 mb-2">
                                                                        <div class="border rounded p-3 h-100">
                                                                            <small class="text-muted d-block">Opens</small>
                                                                            <strong>{{ optional($exam->start_date)->format('M d, Y h:i A') ?: 'Available now' }}</strong>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-4 mb-2">
                                                                        <div class="border rounded p-3 h-100">
                                                                            <small class="text-muted d-block">Closes</small>
                                                                            <strong>{{ optional($exam->end_date)->format('M d, Y h:i A') ?: 'As announced' }}</strong>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="alert alert-warning">
                                                                    <strong>Your timer starts only when you click “Start Test Now” below.</strong>
                                                                    Do not start until you are ready to complete the assessment.
                                                                </div>

                                                                <div class="alert alert-danger">
                                                                    <strong><i class="fas fa-shield-alt mr-1"></i> Assessment integrity notice</strong>
                                                                    <div class="mt-1">
                                                                        RMS records and monitors technical and activity information associated with your attempt, including device/browser details, IP address, session activity, tab/app visibility events, connection events, and other assessment-integrity signals. Attempts showing unusual or suspicious patterns may be flagged for investigation and, when validated as a violation of assessment rules, may be invalidated or assigned a score of zero.
                                                                    </div>
                                                                </div>

                                                                <h6 class="font-weight-bold">Important instructions</h6>
                                                                <ol class="pl-4">
                                                                    <li class="mb-2">Use a stable internet connection and, when possible, a fully charged device or a device connected to power.</li>
                                                                    <li class="mb-2">Answer one question at a time. You may move between questions using the Previous, Next, and Questions controls.</li>
                                                                    <li class="mb-2">Your selected answers are saved automatically. If your connection is interrupted, RMS will attempt to save again when the connection returns.</li>
                                                                    <li class="mb-2">Refreshing the page, switching tabs/apps, locking the device, or temporarily losing connection does not by itself submit your test. These events may be recorded for assessment integrity and audit purposes.</li>
                                                                    <li class="mb-2">When the allotted time expires, the system finalizes and submits your saved responses automatically.</li>
                                                                    <li class="mb-2">You may submit earlier, but once submitted you can no longer reopen or change your answers.</li>
                                                                    @if($exam->assessment_group_id)
                                                                        <li class="mb-2">You will be locked to the equivalent test set assigned to you for this assessment.</li>
                                                                    @endif
                                                                </ol>

                                                                <div class="custom-control custom-checkbox mt-3">
                                                                    <input type="checkbox" class="custom-control-input assessment-start-ack"
                                                                           id="writtenAck{{ $exam->id }}"
                                                                           data-target="#writtenStartBtn{{ $exam->id }}">
                                                                    <label class="custom-control-label" for="writtenAck{{ $exam->id }}">
                                                                        I have read the instructions and I am ready to begin.
                                                                    </label>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Not Yet</button>
                                                                <form method="post" action="{{ route('guest.assessments.attempts.start', [$application, $exam]) }}" class="d-inline">
                                                                    @csrf
                                                                    <button type="submit" id="writtenStartBtn{{ $exam->id }}" class="btn btn-primary" disabled>
                                                                        <i class="fas fa-play mr-1"></i> Start Test Now
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        @endforeach

                                        @foreach($skillTests as $skillTest)
                                            @php
                                                $skillAttempt = $skillTest->attempts->first();
                                                $skillOpen = $skillTest->status
                                                    && $skillTest->start_date
                                                    && $skillTest->end_date
                                                    && now()->gte($skillTest->start_date)
                                                    && now()->lt($skillTest->end_date);
                                                $skillModes = $skillTest->submission_modes ?: ['inline'];
                                                $skillExtensions = $skillTest->allowed_extensions ?: [];
                                            @endphp
                                            @if(!$skillAttempt && $hasAssessmentRel && $skillOpen)
                                                <div class="modal fade" id="skillStartModal{{ $skillTest->id }}" tabindex="-1" aria-labelledby="skillStartLabel{{ $skillTest->id }}" aria-hidden="true">
                                                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                                        <div class="modal-content">
                                                            <div class="modal-header bg-light">
                                                                <div>
                                                                    <h5 class="modal-title mb-0" id="skillStartLabel{{ $skillTest->id }}">
                                                                        <i class="fas fa-tools text-primary mr-1"></i>
                                                                        Before You Start the Skills Test
                                                                    </h5>
                                                                    <small class="text-muted">{{ $skillTest->skillTestGroup?->title ?: $skillTest->title }}</small>
                                                                </div>
                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="row mb-3">
                                                                    <div class="col-md-4 mb-2">
                                                                        <div class="border rounded p-3 h-100">
                                                                            <small class="text-muted d-block">Time limit</small>
                                                                            <strong>{{ $skillTest->duration }} minutes</strong>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-4 mb-2">
                                                                        <div class="border rounded p-3 h-100">
                                                                            <small class="text-muted d-block">Submission</small>
                                                                            <strong>{{ collect($skillModes)->map(fn($mode) => ucfirst($mode))->join(' + ') }}</strong>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-4 mb-2">
                                                                        <div class="border rounded p-3 h-100">
                                                                            <small class="text-muted d-block">Closes</small>
                                                                            <strong>{{ optional($skillTest->end_date)->format('M d, Y h:i A') }}</strong>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="alert alert-warning">
                                                                    <strong>Your timer starts only when you click “Start Skills Test Now” below.</strong>
                                                                    Make sure you have enough uninterrupted time to finish.
                                                                </div>

                                                                <div class="alert alert-danger">
                                                                    <strong><i class="fas fa-shield-alt mr-1"></i> Assessment integrity notice</strong>
                                                                    <div class="mt-1">
                                                                        RMS records and monitors technical and activity information associated with your attempt, including device/browser details, IP address, session activity, tab/app visibility events, connection events, and other assessment-integrity signals. Attempts showing unusual or suspicious patterns may be flagged for investigation and, when validated as a violation of assessment rules, may be invalidated or assigned a score of zero.
                                                                    </div>
                                                                </div>

                                                                @if($skillTest->expected_output)
                                                                    <div class="alert alert-light border">
                                                                        <strong>Expected output</strong>
                                                                        <div class="mt-1">{{ $skillTest->expected_output }}</div>
                                                                    </div>
                                                                @endif

                                                                <h6 class="font-weight-bold">Important instructions</h6>
                                                                <ol class="pl-4">
                                                                    <li class="mb-2">Read the complete task instructions on the assessment screen before preparing your response.</li>
                                                                    @if(in_array('inline',$skillModes,true))
                                                                        <li class="mb-2">For inline responses, type directly in the response box. Your work is saved automatically as you type.</li>
                                                                        <li class="mb-2">Pasting or dragging/dropping text into the inline response is disabled. Type your response directly in RMS.</li>
                                                                    @endif
                                                                    @if(in_array('file',$skillModes,true))
                                                                        <li class="mb-2">
                                                                            File submission is allowed.
                                                                            @if(count($skillExtensions))
                                                                                Accepted file type(s): <strong>{{ implode(', ', $skillExtensions) }}</strong>.
                                                                            @endif
                                                                            @if($skillTest->max_file_size_kb)
                                                                                Maximum file size: <strong>{{ number_format($skillTest->max_file_size_kb / 1024, 1) }} MB</strong>.
                                                                            @endif
                                                                        </li>
                                                                        <li class="mb-2">If you upload a replacement file before submission, RMS retains prior versions in the audit history.</li>
                                                                    @endif
                                                                    <li class="mb-2">Use a stable connection. Connectivity and page-visibility events may be recorded for audit purposes.</li>
                                                                    <li class="mb-2">Submit only when your work is final. After submission, you can no longer edit the response or replace the file.</li>
                                                                    @if($skillTest->skill_test_group_id)
                                                                        <li class="mb-2">The equivalent set is determined by the schedule in effect when you start. Once started, you will be locked to that set.</li>
                                                                    @endif
                                                                </ol>

                                                                <div class="custom-control custom-checkbox mt-3">
                                                                    <input type="checkbox" class="custom-control-input assessment-start-ack"
                                                                           id="skillAck{{ $skillTest->id }}"
                                                                           data-target="#skillStartBtn{{ $skillTest->id }}">
                                                                    <label class="custom-control-label" for="skillAck{{ $skillTest->id }}">
                                                                        I have read the instructions and I am ready to begin.
                                                                    </label>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Not Yet</button>
                                                                <form method="post" action="{{ route('guest.skills.start',[$application,$skillTest]) }}" class="d-inline">
                                                                    @csrf
                                                                    <button type="submit" id="skillStartBtn{{ $skillTest->id }}" class="btn btn-primary" disabled>
                                                                        <i class="fas fa-play mr-1"></i> Start Skills Test Now
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        @endforeach

                                        @foreach($exams as $exam)
                                            @php $attempt = $exam->attempts->first(); @endphp
                                            @if($attempt && $attempt->auto_submitted)
                                                <div class="modal fade" id="attemptNoteModal{{ $attempt->id }}" tabindex="-1" aria-labelledby="attemptNoteLabel{{ $attempt->id }}" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="attemptNoteLabel{{ $attempt->id }}">Auto-submit details</h5>
                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><strong>Exam:</strong> {{ $exam->title }}</p>
                                                                <p><strong>Reason:</strong> {{ $attempt->auto_submit_reason ?? 'Unknown' }}</p>
                                                                <p><strong>Submitted at:</strong> {{ $attempt->ended_at }}</p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        @endforeach
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

    // Acknowledge test instructions before enabling the final start action.
    $(document).on('change', '.assessment-start-ack', function () {
        var target = $(this).data('target');
        $(target).prop('disabled', !this.checked);
    });

    // Reset acknowledgement if the applicant closes a start modal and reopens it later.
    $('.modal').on('hidden.bs.modal', function () {
        var ack = $(this).find('.assessment-start-ack');
        if (ack.length) {
            ack.prop('checked', false);
            $(ack.data('target')).prop('disabled', true);
        }
    });
</script>
@endsection
