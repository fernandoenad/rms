@extends('layouts.guest')

@section('title')
    {{ config('app.name', '') }} | Administration - Login
@endsection

@section('navTitle')
    {{ config('app.name', '') }}
@endsection

@section('main')
  <!-- /.login-logo -->
  <section class="content-header">
        <div class="container">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Administration - Login</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/">Recruitment Management System</a></li>
                        <li class="breadcrumb-item active">Administration - Login</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>
    
    <section class="content">
        <div class="container">
            <div class="row">
                <div class="col-md-4 offset-md-4">
                    <div class="card">
                        <div class="card-body login-card-body">
                            <p class="login-box-msg">Sign in to start your session</p>
                            
                            @if(app()->environment('local'))
                            {{-- Local/Development Login Form --}}
                            <form action="{{route('login')}}" method="post">
                                @csrf 
                                @method('post')
                                <div class="input-group mb-3">
                                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" placeholder="Email" value="{{ old('email')}}" autofocus>
                                    <div class="input-group-append">
                                        <div class="input-group-text">
                                            <span class="fas fa-envelope"></span>
                                        </div>
                                    </div>
                                </div>
                                @error('email')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                            
                                @if (session('status'))
                                    <span class="text-danger"><small>{{ session('status') }}</small></span>
                                @endif

                                <div class="input-group mb-3">
                                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="Password">
                                    <div class="input-group-append">
                                        <div class="input-group-text">
                                            <span class="fas fa-lock"></span>
                                        </div>
                                    </div>
                                </div>
                                @error('password')
                                    <span class="text-danger"><small>{{ $message }}</small></span>
                                @enderror
                                
                                <div class="row">
                                    <div class="col-8">
                                        <div class="icheck-primary">
                                            <input type="checkbox" name="remember" id="remember">
                                            <label for="remember">
                                                Remember Me
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <button type="submit" class="btn btn-primary btn-block">Sign In</button>
                                    </div>
                                </div>
                            </form>
                            
                            <hr>
                            @endif
                            
                            <div class="social-auth-links text-center mb-3">
                                <a href="{{ url('/auth/google') }}" class="google-btn">
									<img src="https://developers.google.com/identity/images/g-logo.png" alt="Google Logo" width="10%">
									Sign in with DepEd GMail
								</a>
                                <br>
                                <br>
                                
                                @if (session('not_reg'))
									<div class="alert alert-warning">
										<strong>{{ session('not_reg') }}</strong>
										<a href="./rms/register">Register?</a>
									</div>
									<br>
								@endif
								@if (session('not_deped'))
									<div class="alert alert-warning">
										<strong>{{ session('not_deped') }}</strong>										
                                    </div>
                                    <br>
								@endif

                                
                                Forgot your DepEd GMail/Microsoft password? <br>
								Request for reset
								<a href="https://hrms.depedbohol.org/help/reset" target="_blank" class="google-btn">here</a>.
                            </div>
                            
                            <!-- /.social-auth-links -->
                            <!--
                            <p class="mb-1">
                                <a href="#">I forgot my password</a>
                            </p>
                            <p class="mb-0">
                                <a href="#" class="text-center">Register a new membership</a>
                            </p>
                            -->
                        </div>
                        <!-- /.login-card-body -->
                    </div>
                </div>
                <!-- /.login-box -->
                </div>
            </div>
        </div>
    </section>
@endsection
