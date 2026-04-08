@extends('adminlte::page')

@php
    $title = "Application Details";
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
                <li class="breadcrumb-item"><a href="{{route('admin.applications.index')}}">Applications</a></li>
                <li class="breadcrumb-item active">{{$application->application_code}}</li>
            </ol>
        </div>
    </div>
@stop

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3">
                <!-- Profile Image -->
                <div class="card card-primary card-outline">
                    <div class="card-body box-profile">
                        <div class="text-center">
                            <img class="profile-user-img img-fluid img-circle"
                                src="{{url('/')}}/images/user.png"
                                alt="User profile picture">
                        </div>
                        <h3 class="profile-username text-center">{{$application->getFullname()}}</h3>
                        <p class="text-muted text-center">
                            {{$application->vacancy->position_title}}<br>
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
                        <strong><i class="fas fa-id-card mr-1"></i> Application ID</strong> 
                        <p class="text-muted">{{$application->application_code}}</p>
                        <hr>
                        <strong><i class="fas fa-at mr-1"></i> Email</strong>
                        <p class="text-muted">{{$application->email}}</p>
                        <hr>
                        <a href="{{route('admin.applications.edit', ['application' => $application])}}" class="btn btn-sm btn-warning" title="Modify application">
                            <span class="fas primary fa-fw fa-edit"></span>  
                        </a>
                        @if($application->assessment !== null)
                            <a href="{{route('admin.applications.edit_scores', ['application' => $application])}}" class="btn btn-sm btn-primary" title="Modify assessment">
                                <span class="fas primary fa-fw fa-list"></span>  
                            </a>
                        @else 
                        <a href="#" class="btn btn-sm btn-primary" title="Modify assessment" onClick="return confirm('Action not permitted! This application was not taken-in yet. Take in the application first via the School/Office portal.')">
                            <span class="fas primary fa-fw fa-list"></span>  
                        </a>
                        @endif
                        <a href="{{route('admin.applications.delete', ['application' => $application])}}" class="btn btn-sm btn-danger float-right {{ $application->station_id != -1 ? 'disabled' : '' }}" 
                            onclick="return confirm('This will delete the application. Are you sure?')" title="Delete">
                            <span class="fas fa-fw fa-trash"></span> 
                        </a>
                        <a href="{{route('admin.applications.revert', $application)}}" class="btn btn-sm btn-info {{ isset($application->assessment) && $application->assessment->count() > 0 ? '' : 'disabled' }}  {{$application->vacancy->level2_status == 3 ? 'disabled' :'' }}" 
                            onclick="return confirm('This will revert status to new deleting the existing scores in the process. Are you sure?')" title="Revert to new">
                            <span class="fas fa-fw fa-reply"></span> 
                        </a>
                        
                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->
            </div>
            <!-- /.col -->
            <div class="col-md-9">
                <div class="card card-default direct-chat direct-chat-primary">
                    <div class="card-header p-2">
                        <div class="card-tools">
                            <ul class="nav nav-pills ml-auto">
                                <li class="nav-item">
                                    <a class="nav-link active" href="#queries" data-toggle="tab">Queries</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="#profile" data-toggle="tab">Profile</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="#scores" data-toggle="tab">Scores</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                            <div class="tab-content p-0">
                                <div class="chart tab-pane active" id="queries">
                                    <div class="card-body">
                                        <div class="direct-chat-messages" style="min-height: 480px">
                                            <div class="direct-chat-msg right">
                                                <div class="direct-chat-infos clearfix">
                                                    <span class="direct-chat-name float-right">{{ config('app.name', '') }} System</span>
                                                    <span class="direct-chat-timestamp float-right"></span>
                                                </div>
                                                <img class="direct-chat-img" src="{{url('/')}}/images/user.png" alt="user image">
                                                <div class="direct-chat-text">
                                                    Application was created on {{ $application->created_at->format('M d, Y @ h:ia') }}.
                                                </div>
                                            </div>
                                        @if(sizeof($applicationInquiries) > 0)
                                            @foreach($applicationInquiries as $applicationInquiry)
                                                <!-- Post -->
                                                <div class="direct-chat-msg {{$applicationInquiry->author == $application->getFullname() ?'left':'right'}}">
                                                    <div class="direct-chat-infos clearfix">
                                                        <span class="direct-chat-name float-{{$applicationInquiry->author == $application->getFullname() ?'left':'right'}}">{{$applicationInquiry->author}}</span>
                                                        <span class="direct-chat-timestamp float-{{$applicationInquiry->author == $application->getFullname() ?'right':'left'}}">{{$applicationInquiry->created_at->setTimezone('Asia/Shanghai')->toDayDateTimeString();}}</span>
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
                                        <div class="card-footer">
                                            <form class="form-horizontal" method="post" action="{{route('admin.applications.saveInquiry', $application)}}">
                                                @csrf 
                                                @method('patch')
                                                <div class="input-group input-group-sm mb-0">
                                                    <textarea class="form-control form-control-sm" name="message" required placeholder="Message will saved and emailed to the applicant."></textarea>
                                                    <div class="input-group-append">
                                                        <button type="submit"  class="btn btn-danger">Send</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                <div class="chart tab-pane" id="profile">
                                    <div class="card-body table-responsive p-0">
                                        <table class="table table-hover text-nowrap table-borderless">
                                            <tbody>
                                                <tr>
                                                    <th width="20%">Name</th>
                                                    <td>{{ $application->getFullname2() }}</td>
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
                                                        <em>For corrections on profile entries, it should be requested 
                                                            to the school where you're applying to.</em>
                                                    </td>
                                                </tr>
                                     
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="chart tab-pane" id="scores">
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
                                                            {{$application->assessment->status >= 3 ? $application->assessment->updated_at->format('M d, Y h:ia') : 'TBA'}}
                                                        </td>                                                  
                                                    </tr>
                                                @else 
                                                    <tr>
                                                        <td>
                                                            Status<br>
                                                        </td>
                                                        <td>                                                            
                                                            <span class="badge bg-warning">Please contact school to have it marked as COMPLETED</span>
                                                        </td>                                                  
                                                    </tr>
                                                @endif
                                            
                                     
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>                    
                </div>
                <!-- /.row -->
    </div><!-- /.container-fluid -->
@stop

@section('footer')
    @include('layouts.footer')
@stop

@section('css')
@stop

@section('js')
@stop
