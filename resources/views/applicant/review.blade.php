@extends('layouts.guest')
@section('title', 'Review Application')
@section('main')
<div class="container py-4">
<h1 class="h3">Review Application — {{ $vacancy->position_title }}</h1>
<p>RMS will use your saved master profile. You cannot edit your details on this screen.</p>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="card mb-3"><div class="card-body">
<dl class="row">
@foreach(['first_name','middle_name','last_name','sitio','barangay','municipality','zip','age','gender','civil_status','religion','disability','ethnic_group','email','phone'] as $field)
<dt class="col-sm-4">{{ ucfirst(str_replace('_',' ', $field)) }}</dt>
<dd class="col-sm-8">{{ $profile->$field }}</dd>
@endforeach
</dl>
<a href="{{ route('applicant.profile.edit') }}" class="btn btn-outline-secondary">Edit Master Profile</a>
</div></div>
<form method="POST" action="{{ route('applicant.apply.store', $vacancy) }}">@csrf
<label><input type="checkbox" name="confirm" value="1" required> I confirm the current information and wish to submit this application.</label>
<div class="mt-3"><button class="btn btn-primary">Submit Application</button>
<a href="{{ route('applicant.dashboard') }}" class="btn btn-outline-secondary">Cancel</a></div>
</form></div>
@endsection
