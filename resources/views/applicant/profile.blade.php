@extends('layouts.guest')
@section('title', 'Applicant Master Profile')
@section('main')
<div class="container py-4"><h1 class="h3">Master Applicant Profile</h1>
<p>Profile changes apply to future applications only. Submitted application records will not change.</p>
@if($errors->any())<div class="alert alert-danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ route('applicant.profile.update') }}">@csrf @method('PUT')
<div class="row">
@foreach(['first_name'=>'First name','middle_name'=>'Middle name','last_name'=>'Last name','sitio'=>'Sitio / Street','barangay'=>'Barangay','municipality'=>'Municipality','zip'=>'Postal code','age'=>'Age','gender'=>'Gender','civil_status'=>'Civil status','religion'=>'Religion','disability'=>'Disability','ethnic_group'=>'Ethnic group','email'=>'Contact email','phone'=>'Mobile number'] as $field=>$label)
<div class="col-md-6 mb-3"><label for="{{ $field }}">{{ $label }}</label>
<input class="form-control" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $profile->$field) }}" {{ 'required' }}></div>
@endforeach
</div>
<button class="btn btn-primary">Save profile</button>
<a class="btn btn-outline-secondary" href="{{ route('applicant.dashboard') }}">Cancel</a>
</form></div>
@endsection
