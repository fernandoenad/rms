@extends('adminlte::page')

@php
    $title = "Edit Vacancy";
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
                <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.vacancies.index') }}">Vacancies</a></li>
                <li class="breadcrumb-item active">{{ $title }}</li>
            </ol>
        </div>
    </div>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible auto-close">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            {{ session('status') }}
        </div>
    @endif

    @php
        $posting = $vacancy->getPostingStatus();
        $postingClass = $posting==='Open for Applications' ? 'success' : ($posting==='Scheduled' ? 'info' : ($posting==='Closed' ? 'secondary' : 'light border'));
    @endphp
    <div class="card card-outline card-light mb-3">
        <div class="card-body py-2 d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <strong>{{ $vacancy->position_title }}</strong>
                <span class="badge badge-{{ $postingClass }} ml-2">{{ $posting }}</span>
                <div class="small text-muted">Quick posting controls are separate from the detailed vacancy settings below.</div>
            </div>
            <div class="mt-2 mt-md-0">
                <a href="{{ route('admin.applications.vacancy.show',$vacancy) }}" class="btn btn-sm btn-outline-secondary mr-1"><i class="fas fa-users mr-1"></i> Applicants</a>
                @if($posting==='Draft')
                    <form method="post" action="{{ route('admin.vacancies.publish',$vacancy) }}" class="d-inline">@csrf
                        <button class="btn btn-sm btn-success" onclick="return confirm('Publish and open applications now?')"><i class="fas fa-bullhorn mr-1"></i> Publish Now</button>
                    </form>
                @elseif($posting==='Open for Applications')
                    <form method="post" action="{{ route('admin.vacancies.close_posting',$vacancy) }}" class="d-inline">@csrf
                        <button class="btn btn-sm btn-warning" onclick="return confirm('Close applications now?')"><i class="fas fa-door-closed mr-1"></i> Close Applications</button>
                    </form>
                @elseif($posting==='Closed')
                    <form method="post" action="{{ route('admin.vacancies.reopen',$vacancy) }}" class="d-inline">@csrf
                        <button class="btn btn-sm btn-success" onclick="return confirm('Reopen applications now?')"><i class="fas fa-door-open mr-1"></i> Reopen</button>
                    </form>
                @elseif($posting==='Scheduled')
                    <form method="post" action="{{ route('admin.vacancies.publish',$vacancy) }}" class="d-inline">@csrf
                        <button class="btn btn-sm btn-success" onclick="return confirm('Override the schedule and open applications now?')"><i class="fas fa-play mr-1"></i> Open Now</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Edit {{ $vacancy->position_title }} <span class="text-muted">#{{ $vacancy->id }}</span></h3>
                    </div>
                    <form method="post" action="{{ route('admin.vacancies.update', $vacancy) }}">
                        @csrf
                        @method('put')
                        <div class="card-body">
                            <div class="form-group">
                                <label for="#">Cycle</label>
                                <input type="text" class="form-control" placeholder="Enter cycle" 
                                    name="cycle" class="@error('cycle') is-invalid @enderror"
                                    value="{{ $vacancy->cycle }}" readonly>
                                @error('cycle')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="#">Position title</label>
                                <input type="text" class="form-control" placeholder="Enter position title" 
                                    name="position_title" class="@error('position_title') is-invalid @enderror"
                                    value="{{ $vacancy->position_title }}" autofocus>
                                @error('position_title')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="#">Salary grade</label>
                                <select type="text" class="form-control" placeholder="Enter salary grade" 
                                    name="salary_grade" class="@error('salary_grade') is-invalid @enderror"
                                    value="{{ $vacancy->salary_grade }}">
                                    <option value="">---select---</option>
                                    @for($sg = 1; $sg <= 33; $sg++)
                                        <option value="{{$sg}}" {{ $vacancy->salary_grade == $sg ? 'selected' : '' }}>Salary Grade {{$sg}}</option>
                                    @endfor
                                </select>
                                @error('salary_grade')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="#">Base pay</label>
                                <input type="number" class="form-control" placeholder="Enter base pay" 
                                    name="base_pay" class="@error('base_pay') is-invalid @enderror"
                                    value="{{ $vacancy->base_pay }}">
                                @error('base_pay')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="#">Office level</label>
                                <select type="text" class="form-control" placeholder="Enter office level" 
                                    name="office_level" class="@error('office_level') is-invalid @enderror"
                                    value="{{ $vacancy->office_level }}">
                                    <option value="">---select---</option>
                                    <option value="0" {{ $vacancy->office_level == 0 ? 'selected' : '' }}>SDO</option>
                                    <option value="-1" {{ $vacancy->office_level == -1 ? 'selected' : '' }}>Field</option>
                                </select>
                                @error('office_level')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="#">Qualifications</label>
                                <textarea type="text" class="form-control" placeholder="Enter qualifications" 
                                    name="qualifications" class="@error('qualifications') is-invalid @enderror"
                                    value="{{ $vacancy->qualifications }}">{{ $vacancy->qualifications }}</textarea>
                                @error('qualifications')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="#">Number of Vacancies</label>
                                <input type="number" class="form-control" placeholder="Enter vacancy" 
                                    name="vacancy" class="@error('vacancy') is-invalid @enderror"
                                    value="{{ $vacancy->vacancy }}">
                                @error('vacancy')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="card card-outline card-info">
                                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                    <strong>Posting & Application Window</strong>
                                    @php
                                        $postingState = $vacancy->getPostingStatus();
                                        $postingClass = $postingState==='Open for Applications' ? 'success' : ($postingState==='Scheduled' ? 'info' : ($postingState==='Closed' ? 'secondary' : 'light border'));
                                    @endphp
                                    <span class="badge badge-{{ $postingClass }}">{{ $postingState }}</span>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label>Posting Mode</label>
                                        <select class="form-control @error('status') is-invalid @enderror" name="status">
                                            <option value="0" {{ (string)old('status',$vacancy->status)==='0' ? 'selected' : '' }}>Draft — not visible to applicants</option>
                                            <option value="1" {{ (string)old('status',$vacancy->status)==='1' ? 'selected' : '' }}>Published — visibility follows the dates below</option>
                                        </select>
                                        @error('status')<span class="text-danger d-block"><small>{{ $message }}</small></span>@enderror
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label>Applications Open</label>
                                            <input type="datetime-local" name="posting_start_at" class="form-control @error('posting_start_at') is-invalid @enderror"
                                                   value="{{ old('posting_start_at', optional($vacancy->posting_start_at)->format('Y-m-d\TH:i')) }}">
                                            <small class="text-muted">Leave blank to open immediately while published.</small>
                                            @error('posting_start_at')<span class="text-danger"><small>{{ $message }}</small></span>@enderror
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label>Applications Close</label>
                                            <input type="datetime-local" name="posting_end_at" class="form-control @error('posting_end_at') is-invalid @enderror"
                                                   value="{{ old('posting_end_at', optional($vacancy->posting_end_at)->format('Y-m-d\TH:i')) }}">
                                            <small class="text-muted">The public application form closes automatically at this time.</small>
                                            @error('posting_end_at')<span class="text-danger"><small>{{ $message }}</small></span>@enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="#">Scoring Template</label>
                                <select type="text" class="form-control" placeholder="Enter status" 
                                    name="template_id" class="@error('template_id') is-invalid @enderror"
                                    value="{{ old('template_id') }}">
                                    <option value="">---select scoring template---</option>
                                    @foreach($templates as $template)
                                        <option value="{{ $template->id }}" {{ $template->id == $vacancy->template_id ? 'selected' : '' }}>{{ $template->type }} — v{{ $template->version ?? 1 }} ({{ number_format($template->criteria->sum('max_points'),3) }} pts)</option>
                                    @endforeach
                                </select>
                                @error('template_id')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                                <small class="form-text text-muted">
                                    Managed centrally under <a href="{{ route('admin.scoring_templates.index') }}" target="_blank">Scoring Templates</a>.
                                </small>
                            </div> 
                            <div class="form-group">
                                <label for="#">Station Screening Stage</label>
                                <select type="text" class="form-control" placeholder="Enter status" 
                                    name="level1_status" class="@error('level1_status') is-invalid @enderror"
                                    value="{{ $vacancy->level1_status }}">
                                    <option value="">---select---</option>
                                    <option value="0" {{ $vacancy->level1_status == 0 ? 'selected' : '' }}>Closed</option>
                                    <option value="1" {{ $vacancy->level1_status == 1 ? 'selected' : '' }}>Open</option>
                                    <option value="2" {{ $vacancy->level1_status == 2 ? 'selected' : '' }}>Completed</option>
                                </select>
                                @error('level1_status')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="#">Division Screening Stage</label>
                                <select type="text" class="form-control" placeholder="Enter status" 
                                    name="level2_status" class="@error('level2_status') is-invalid @enderror"
                                    value="{{ $vacancy->level2_status }}">
                                    <option value="">---select---</option>
                                    <option value="0" {{ $vacancy->level2_status == 0 ? 'selected' : '' }}>Closed</option>
                                    <option value="1" {{ $vacancy->level2_status == 1 ? 'selected' : '' }}>Open</option>
                                    <option value="2" {{ $vacancy->level2_status == 2 ? 'selected' : '' }}>Completed</option>
                                    <option value="3" {{ $vacancy->level2_status == 3 ? 'selected' : '' }}>Posted</option>
                                </select>
                                @error('level2_status')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            </div>   
                            
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save Changes</button>
                            <button type="reset" class="btn btn-default">Reset</button>
                            <a href="{{ url()->previous() }}" class="btn btn-default float-right">Cancel</a>
                        </div>
                    </form> 
                </div>
            </div>
        </div>
    </div>
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