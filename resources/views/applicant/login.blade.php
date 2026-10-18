@extends('layouts.guest')
@section('title', 'RMS Applicant Login')
@section('main')
<div class="container py-5"><div class="card mx-auto" style="max-width:520px">
<div class="card-body">
<h1 class="h4">Applicant Portal</h1>
<p>Sign in with your Google account to manage your applicant profile and applications.</p>
@if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif
<a class="btn btn-primary" href="{{ route('applicant.google') }}">Sign in with Google</a>
<p class="mt-3"><a href="{{ url('/login') }}">Staff login</a></p>
</div></div></div>
@endsection
