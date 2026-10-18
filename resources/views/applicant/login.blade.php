@extends('layouts.guest')
@section('title', 'RMS Applicant Login')
@section('navTitle', 'Recruitment Management System')

@section('css')
<style>
    .applicant-login-wrap {
        min-height: calc(100vh - 190px);
        padding: 36px 16px 48px;
        background: #f4f6f9;
    }
    .applicant-login-card {
        max-width: 730px;
        margin: 0 auto;
        background: #fff;
        border: 1px solid #e1e3e5;
        border-radius: 0;
        box-shadow: 0 2px 7px rgba(0,0,0,.12);
    }
    .applicant-login-inner {
        padding: 43px 38px 67px;
        text-align: center;
        color: #666;
    }
    .applicant-login-title {
        margin: 0 0 55px;
        font-size: clamp(1.55rem, 3.5vw, 2rem);
        font-weight: 400;
        line-height: 1.35;
    }
    .applicant-google-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        color: #007bff;
        font-size: clamp(1.45rem, 3.4vw, 2rem);
        font-weight: 400;
        line-height: 1.3;
        text-decoration: none;
        margin-bottom: 49px;
        min-height: 64px;
    }
    .applicant-google-link:hover,
    .applicant-google-link:focus {
        color: #0056b3;
        text-decoration: underline;
    }
    .applicant-google-mark {
        width: 64px;
        height: 64px;
        flex: 0 0 64px;
        object-fit: contain;
    }
    .applicant-login-help {
        margin: 0;
        font-size: clamp(1.25rem, 3.2vw, 1.9rem);
        line-height: 1.55;
        font-weight: 400;
    }
    @media (max-width: 576px) {
        .applicant-login-wrap { padding: 20px 12px 35px; }
        .applicant-login-inner { padding: 30px 18px 42px; }
        .applicant-login-title { margin-bottom: 36px; }
        .applicant-google-link { margin-bottom: 32px; gap: 8px; }
        .applicant-google-mark { width: 50px; height: 50px; flex-basis: 50px; }
    }
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
