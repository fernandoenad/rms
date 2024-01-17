@extends('adminlte::page')

@section('title', 'RMS v1.0 | Show Application')

@section('content_header')
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1 class="m-0">Show Application</h1>
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
                        <hr>
                        <a href="{{route('admin.applications.edit', ['application' => $application])}}" class="btn btn-sm btn-warning" title="Modify">
                            <span class="fas primary fa-fw fa-edit"></span> Edit 
                        </a>
                        <a href="{{route('admin.applications.delete', ['application' => $application])}}" class="btn btn-sm btn-danger float-right" title="Delete">
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
                        <h7>Show inquiry history of ID# <strong>{{$application->id}}</strong></h7>
                        <a href="{{route('admin.applications.index')}}" class="btn btn-sm btn-default float-right" title="Back">
                            <span class="fas fa-fw fa-arrow-left"></span> Back
                        </a>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <div class="direct-chat-messages" style="min-height: 480px">
                                @if(sizeof($applicationInquiries) > 0)
                                    @foreach($applicationInquiries as $applicationInquiry)
                                        <!-- Post -->
                                        <div class="direct-chat-msg {{$applicationInquiry->author == $application->applicant_fullname ?'left':'right'}}">
                                            <div class="direct-chat-infos clearfix">
                                                <span class="direct-chat-name float-{{$applicationInquiry->author == $application->applicant_fullname ?'left':'right'}}">{{$applicationInquiry->author}}</span>
                                                <span class="direct-chat-timestamp float-{{$applicationInquiry->author == $application->applicant_fullname ?'right':'left'}}">{{$applicationInquiry->created_at->setTimezone('Asia/Shanghai')->toDayDateTimeString();}}</span>
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
                <!-- /.row -->
    </div><!-- /.container-fluid -->
@stop

@section('footer')
    <div class="float-right d-none d-sm-inline">
        Developed by Dr. Fernando B. Enad
    </div>
    <strong>Copyright &copy; 2023 <a href="/">{{ config('app.name', '') }}</a>.</strong> All rights reserved.
@stop


@section('css')
    <link rel="stylesheet" href="/css/admin_custom.css">
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