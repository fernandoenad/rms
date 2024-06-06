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
                        <strong><i class="fas fa-id-card mr-1"></i> Application ID</strong> 
                        <p class="text-muted">{{$application->application_code}}</p>
                        <hr>
                        <strong><i class="fas fa-at mr-1"></i> Email</strong>
                        <p class="text-muted">{{$application->email}}</p>
                        <hr>
                        <a href="{{route('admin.applications.edit', ['application' => $application])}}" class="btn btn-sm btn-warning" title="Modify">
                            <span class="fas primary fa-fw fa-edit"></span> Edit 
                        </a>
                        <a href="{{route('admin.applications.delete', ['application' => $application])}}" class="btn btn-sm btn-danger float-right {{ $application->station_id != -1 ? 'disabled' : '' }}" title="Delete">
                            <span class="fas fa-fw fa-trash"></span> Delete
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
                                                    <textarea class="form-control form-control-sm" name="message" required placeholder="Inquiry message"></textarea>
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

@section('plugins.Datatables', true)

@section('js')
    <script> console.log('Hi!'); </script>
    <script>
        $(function () {
            $("#applications").DataTable({
            "responsive": true, "lengthChange": false, "autoWidth": false,
            "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
            }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
        });
    </script>
@stop