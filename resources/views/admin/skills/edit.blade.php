@extends('adminlte::page')
@section('title','Skills Test')
@section('content_header')<h1>{{ $skillTest->title }}</h1>
@stop
@section('content')
@if($skillTest->access_mode==='selected_applicants')
<div class="card border-info"><div class="card-header"><strong>Optional Selected-Applicant Access</strong></div><div class="card-body">
<form method="post" action="{{ route('admin.skills.assign',$skillTest) }}">@csrf
<textarea name="application_codes" class="form-control" rows="4" placeholder="Paste application codes, one per line or comma-separated" required></textarea>
<small class="text-muted">Only taken-in applicants for this position are accepted.</small><br>
<button class="btn btn-info mt-2">Add Assignments</button>
</form></div></div>
@endif
@if(session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif
<div class="row">
<div class="col-lg-5">
<div class="card"><div class="card-header"><strong>Task</strong></div><div class="card-body">
<p><strong>Position:</strong> {{ optional($skillTest->vacancy)->position_title }}</p>
<p>{!! nl2br(e($skillTest->instructions)) !!}</p>
@if($skillTest->expected_output)<p><strong>Expected output</strong><br>{!! nl2br(e($skillTest->expected_output)) !!}</p>@endif
<hr>
<form method="post" action="{{ route('admin.skills.ai_generate',$skillTest) }}">@csrf
<button class="btn btn-outline-primary" {{ $skillTest->attempts()->exists() ? 'disabled' : '' }}><i class="fas fa-magic"></i> Generate/Replace Task & Rubric with AI</button>
</form>
</div></div>
</div>
<div class="col-lg-7">
<div class="card"><div class="card-header"><strong>Rubric</strong></div><div class="card-body">
<form method="post" action="{{ route('admin.skills.rubric',$skillTest) }}">@csrf
@php $criteria=$skillTest->rubricCriteria; @endphp
@for($i=0;$i<max(5,$criteria->count());$i++)
@php $criterion=$criteria->get($i); @endphp
<div class="border rounded p-2 mb-2">
<div class="form-row">
<div class="col-md-7"><input name="criteria[{{ $i }}][criterion]" class="form-control" placeholder="Criterion" value="{{ optional($criterion)->criterion }}" {{ $i < $criteria->count() ? 'required' : '' }}></div>
<div class="col-md-5"><input type="number" step=".01" name="criteria[{{ $i }}][max_points]" class="form-control" placeholder="Points" value="{{ optional($criterion)->max_points }}"></div>
</div>
<textarea name="criteria[{{ $i }}][description]" class="form-control mt-2" placeholder="Description">{{ optional($criterion)->description }}</textarea>
</div>
@endfor
<small class="text-muted">Use only populated rows. Total must equal 100.</small><br>
<button class="btn btn-primary mt-2">Save Rubric</button>
</form>
</div></div>
</div></div>
@stop
