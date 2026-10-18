@extends('layouts.guest')
@section('title', 'Applicant Dashboard')
@section('main')
<div class="container py-4">
<div class="d-flex justify-content-between align-items-center mb-3">
<h1 class="h3">Applicant Dashboard</h1>
<div>
@if(auth()->user()->role && auth()->user()->role->status)
<a class="btn btn-outline-secondary" href="{{ route('admin.index') }}">Switch to Staff Portal</a>
@if(!\App\Models\OAuthIdentity::where('user_id', auth()->id())->where('provider', 'google')->exists())
<a class="btn btn-outline-primary" href="{{ route('applicant.staff.google.link') }}">Link Staff Google Account</a>
@endif
@endif
<form class="d-inline" action="{{ route('logout') }}" method="POST">@csrf<button class="btn btn-outline-secondary">Sign out</button></form>
</div></div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<div class="card mb-3"><div class="card-body">
<h2 class="h5">Master Profile</h2>
<p>{{ $profile->first_name }} {{ $profile->last_name }} — {{ $profile->email ?: auth()->user()->email }}</p>
<a href="{{ route('applicant.profile.edit') }}" class="btn btn-primary">Edit master profile</a>
</div></div>
<div class="card mb-3"><div class="card-body">
<h2 class="h5">Available Vacancies</h2>
<p>Browse currently open recruitment opportunities. Your master profile will fill in your next application.</p>
<a class="btn btn-outline-primary" href="{{ route('guest.vacancies.index') }}">Browse vacancies</a>
</div></div>
<div class="card mb-3"><div class="card-body">
<h2 class="h5">My Applications</h2>
@if($links->isEmpty())<p>No linked applications yet.</p>@endif
<ul class="list-group">@foreach($links as $link)
<li class="list-group-item">
<strong>{{ $link->application?->application_code }}</strong>
— {{ $link->application?->vacancy?->position_title ?? 'Application' }}
<span class="badge badge-secondary">{{ str_replace('_',' ', $link->link_method) }}</span>
</li>@endforeach</ul></div></div>
<div class="card"><div class="card-body">
<h2 class="h5">Claim Historical Application</h2>
<p>For applications submitted with another email address, enter the original application code and email. HR approval is required.</p>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form action="{{ route('applicant.claims.store') }}" method="POST">@csrf
<div class="form-group"><label>Application code<input class="form-control" name="application_code" required></label></div>
<div class="form-group"><label>Previous email<input class="form-control" type="email" name="email" required></label></div>
<button class="btn btn-primary">Request HR review</button>
</form>
<h3 class="h6 mt-3">Claim Requests</h3>
<ul>@foreach($claims as $claim)<li>{{ $claim->claimed_application_code }} — {{ ucfirst($claim->status) }}</li>@endforeach</ul>
</div></div></div>
@endsection
