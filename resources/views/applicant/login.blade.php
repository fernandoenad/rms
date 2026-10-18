@extends('layouts.guest')
@section('title', 'RMS Applicant Login')
@section('navTitle', 'Recruitment Management System')

@section('css')
<style>
    .applicant-login-wrap { padding: 12px 12px 28px; background: #f4f6f9; }
    .applicant-login-card { width: 100%; max-width: 390px; margin: 0 auto; background: #fff; border: 1px solid #e1e3e5; border-radius: 0; box-shadow: 0 1px 4px rgba(0,0,0,.11); }
    .applicant-login-inner { padding: 23px 17px 27px; text-align: center; color: #666; }
    .applicant-login-title { margin: 0 0 24px; font-size: 16px; font-weight: 400; line-height: 1.4; }
    .applicant-google-link { display: inline-flex; align-items: center; justify-content: center; gap: 6px; color: #007bff; font-size: 16px; font-weight: 400; text-decoration: none; margin-bottom: 25px; line-height: 1.3; }
    .applicant-google-link:hover, .applicant-google-link:focus { color: #0056b3; text-decoration: underline; }
    .applicant-google-mark { width: 35px; height: 35px; flex: 0 0 35px; object-fit: contain; }
    .applicant-login-help { margin: 0; font-size: 13px; line-height: 1.6; font-weight: 400; }
    @media (max-width: 576px) { .applicant-login-wrap { padding: 10px 12px 24px; } .applicant-login-card { max-width: 390px; } .applicant-login-inner { padding: 20px 12px 24px; } }
</style>
@endsection

@section('main')
<main class="applicant-login-wrap">
    <div class="applicant-login-card">
        <div class="applicant-login-inner">
            <h1 class="applicant-login-title">Sign in to start your session</h1>

            @if($errors->any())
                <div class="alert alert-danger text-left" role="alert">{{ $errors->first() }}</div>
            @endif

            <a class="applicant-google-link" href="{{ route('applicant.google') }}">
                <img class="applicant-google-mark"
                     src="https://developers.google.com/identity/images/g-logo.png"
                     alt="" aria-hidden="true">
                <span>Sign in with Google</span>
            </a>

            <p class="applicant-login-help">
                Forgot your DepEd GMail/Microsoft password?<br>
                Request for reset
                <a href="https://hrms.depedbohol.org/help/reset" target="_blank" rel="noopener noreferrer">here</a>.
            </p>
        </div>
    </div>
</main>
@endsection
