@extends('adminlte::page')
@section('title','Manage Scoring Template')
@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">{{ $template->type }} <small class="text-muted">v{{ $template->version }}</small></h1>
        <small class="text-muted">Manage scoring structure and vacancy availability.</small>
    </div>
    <div class="mt-2 mt-md-0">
        <form method="post" action="{{ route('admin.scoring_templates.duplicate',$template) }}" class="d-inline">
            @csrf
            <button class="btn btn-outline-secondary"><i class="fas fa-copy mr-1"></i> Duplicate as New Version</button>
        </form>
        <a href="{{ route('admin.scoring_templates.index') }}" class="btn btn-default"><i class="fas fa-arrow-left mr-1"></i> Back</a>
    </div>
</div>
@stop
@section('content')
@if(session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif
<form method="post" action="{{ route('admin.scoring_templates.update',$template) }}">
    @csrf
    @method('PUT')
    @include('admin.scoring_templates._form')
    <div class="card"><div class="card-body d-flex justify-content-between align-items-center">
        <div class="small text-muted">
            Used by {{ $template->vacancy()->count() }} vacancy/vacancies and {{ $template->assessment()->count() }} applicant assessment record(s).
        </div>
        <button class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save Scoring Template</button>
    </div></div>
</form>
@stop
