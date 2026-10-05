@extends('adminlte::page')
@section('title','New Scoring Template')
@section('content_header')
<div class="d-flex justify-content-between align-items-center">
    <div><h1 class="mb-0">New Scoring Template</h1><small class="text-muted">Create a reusable 100-point comparative assessment structure.</small></div>
    <a href="{{ route('admin.scoring_templates.index') }}" class="btn btn-default"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</div>
@stop
@section('content')
<form method="post" action="{{ route('admin.scoring_templates.store') }}">
    @csrf
    @include('admin.scoring_templates._form')
    <div class="card"><div class="card-body d-flex justify-content-end">
        <button class="btn btn-primary"><i class="fas fa-save mr-1"></i> Create Scoring Template</button>
    </div></div>
</form>
@stop
