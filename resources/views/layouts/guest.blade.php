
<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- DNS Prefetch & Preconnect for faster external resource loading -->
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="dns-prefetch" href="//cdn.datatables.net">
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://cdn.datatables.net" crossorigin>
    
    <title>@yield('title')</title>
    <link rel="stylesheet" href="{{url('/')}}/vendor/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="{{url('/')}}/vendor/overlayScrollbars/css/OverlayScrollbars.min.css">
    <link rel="stylesheet" href="{{url('/')}}/vendor/adminlte/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic&display=swap">
    <link rel="stylesheet" href="//cdn.datatables.net/1.10.19/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.css">
    <style>
        html { -webkit-text-size-adjust: 100%; }
        body { overflow-x: hidden; }
        img, video, canvas { max-width: 100%; height: auto; }
        .table-responsive { -webkit-overflow-scrolling: touch; }
        input, select, textarea, button { max-width: 100%; }
        /* Single, context-aware public navigation; no separate action strip. */
        .rms-main-nav { border-bottom: 1px solid #e4ebf2; background: #fff; box-shadow: 0 2px 12px rgba(35,66,99,.035); }
        .rms-main-nav .navbar-brand { display:flex;align-items:center;gap:8px;max-width:calc(100% - 64px); }
        .rms-main-nav .brand-text { font-size: 1.06rem;font-weight:700!important;color:#284967;white-space:nowrap; }
        .rms-main-nav .nav-link { border-radius:7px;color:#465d71!important;font-weight:600;font-size:.93rem;padding:10px 13px!important; }
        .rms-main-nav .nav-link:hover,.rms-main-nav .nav-link:focus { color:#18578a!important;background:#eef5fb; }
        .rms-main-nav .nav-link.active { color:#175c94!important;background:#eaf3fa;font-weight:700; }
        .rms-main-nav .rms-nav-sign-in { display:inline-flex;align-items:center;justify-content:center;border-radius:8px;padding:9px 17px;min-height:42px;font-weight:700;white-space:nowrap; }
        .rms-main-nav .rms-user-trigger { border:1px solid #cddfea;background:#f5f9fc;color:#285477!important;display:flex;align-items:center;gap:9px;padding:8px 12px!important;max-width:250px;min-height:43px; }
        .rms-main-nav .rms-user-name { overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:160px; }
        .rms-main-nav .rms-user-dropdown { min-width:225px;padding:8px;border:1px solid #e0e9f1;border-radius:11px;box-shadow:0 12px 29px rgba(22,56,83,.14); }
        .rms-main-nav .rms-user-dropdown .dropdown-item { border-radius:7px;padding:10px 11px;color:#2f4c65;font-size:.9rem; }
        .rms-main-nav .rms-user-dropdown .dropdown-item:hover,.rms-main-nav .rms-user-dropdown .dropdown-item:focus { background:#edf5fb; }
        .rms-main-nav .rms-menu-email { padding:7px 11px;color:#718292;font-size:.76rem;overflow-wrap:anywhere; }
        .rms-main-nav .rms-logout-form { margin:0; }
        .rms-main-nav .rms-logout-form button { border:0;background:transparent;width:100%;text-align:left;cursor:pointer; }
        @media (max-width:767px) {
            .rms-main-nav .navbar-collapse { padding:10px 0; }
            .rms-main-nav .navbar-nav { gap:5px;align-items:stretch; }
            .rms-main-nav .rms-nav-sign-in,.rms-main-nav .rms-user-trigger { width:100%;justify-content:flex-start;max-width:none; }
            .rms-main-nav .rms-user-dropdown { box-shadow:none;width:100%;max-width:none;position:static; }
            .rms-main-nav .rms-user-name { max-width:none; }
        }
        @media (max-width: 576px) {
            body { font-size: 16px; }
            .content-wrapper { padding-top: .25rem; }
            .container, .container-fluid { padding-left: 12px; padding-right: 12px; }
            .card-body { padding: 1rem; }
            .form-control, .custom-select { min-height: 44px; font-size: 16px; }
            textarea.form-control { min-height: 120px; }
            .btn { min-height: 44px; }
            .input-group > .form-control { min-width: 0; }
            .main-footer { font-size: .85rem; }
            .main-footer .float-right { float: none !important; display: block !important; margin-bottom: .35rem; }
            .nav-tabs, .nav-pills { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .nav-tabs .nav-link, .nav-pills .nav-link { white-space: nowrap; }
            table.table { font-size: .92rem; }
        }
    </style>
    @yield('css')
    <link rel="icon" type="image/x-icon" href="{{url('/')}}/favicons/favicon.ico">
    <script nonce="f8c3cc36-9b6c-4e47-a887-d4020a95da31">(function(w,d){!function(dp,dq,dr,ds){dp[dr]=dp[dr]||{};dp[dr].executed=[];dp.zaraz={deferred:[],listeners:[]};dp.zaraz.q=[];dp.zaraz._f=function(dt){return async function(){var du=Array.prototype.slice.call(arguments);dp.zaraz.q.push({m:dt,a:du})}};for(const dv of["track","set","debug"])dp.zaraz[dv]=dp.zaraz._f(dv);dp.zaraz.init=()=>{var dw=dq.getElementsByTagName(ds)[0],dx=dq.createElement(ds),dy=dq.getElementsByTagName("title")[0];dy&&(dp[dr].t=dq.getElementsByTagName("title")[0].text);dp[dr].x=Math.random();dp[dr].w=dp.screen.width;dp[dr].h=dp.screen.height;dp[dr].j=dp.innerHeight;dp[dr].e=dp.innerWidth;dp[dr].l=dp.location.href;dp[dr].r=dq.referrer;dp[dr].k=dp.screen.colorDepth;dp[dr].n=dq.characterSet;dp[dr].o=(new Date).getTimezoneOffset();if(dp.dataLayer)for(const dC of Object.entries(Object.entries(dataLayer).reduce(((dD,dE)=>({...dD[1],...dE[1]})),{})))zaraz.set(dC[0],dC[1],{scope:"page"});dp[dr].q=[];for(;dp.zaraz.q.length;){const dF=dp.zaraz.q.shift();dp[dr].q.push(dF)}dx.defer=!0;for(const dG of[localStorage,sessionStorage])Object.keys(dG||{}).filter((dI=>dI.startsWith("_zaraz_"))).forEach((dH=>{try{dp[dr]["z_"+dH.slice(7)]=JSON.parse(dG.getItem(dH))}catch{dp[dr]["z_"+dH.slice(7)]=dG.getItem(dH)}}));dx.referrerPolicy="origin";dx.src="/cdn-cgi/zaraz/s.js?z="+btoa(encodeURIComponent(JSON.stringify(dp[dr])));dw.parentNode.insertBefore(dx,dw)};["complete","interactive"].includes(dq.readyState)?zaraz.init():dp.addEventListener("DOMContentLoaded",zaraz.init)}(w,d,"zarazData","script");})(window,document);</script>
</head>

<body class="hold-transition layout-top-nav layout-footer-fixed layout-navbar-fixed">
    <div class="wrapper">
        <nav class="main-header navbar navbar-expand-md navbar-light navbar-white rms-main-nav" aria-label="Main navigation">
            <div class="container">
                <a href="{{ route('guest.index') }}" class="navbar-brand">
                    <img src="{{ url('/') }}/images/bohol.png" alt="DepEd Bohol" class="brand-image img-circle elevation-3" style="opacity:.8">
                    <span class="brand-text">DepEd Bohol RMS</span>
                </a>
                <button class="navbar-toggler order-1" type="button" data-toggle="collapse" data-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse order-3" id="navbarCollapse">
                    <ul class="navbar-nav ml-auto align-items-md-center">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('guest.index') ? 'active' : '' }}"
                               href="{{ route('guest.index') }}" @if(request()->routeIs('guest.index')) aria-current="page" @endif>Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('guest.vacancies.*', 'applicant.apply.*') ? 'active' : '' }}"
                               href="{{ route('guest.vacancies.index') }}">Vacancies</a>
                        </li>
                        @guest
                            <li class="nav-item ml-md-2">
                                <a class="btn btn-primary rms-nav-sign-in" href="{{ route('applicant.login') }}">
                                    <i class="fas fa-sign-in-alt mr-2" aria-hidden="true"></i>Sign In
                                </a>
                            </li>
                        @else
                            @php
                                $rmsNavUser = auth()->user();
                                $rmsNavName = trim((string) $rmsNavUser->name);
                                if ($rmsNavName === '') {
                                    $rmsNavName = \Illuminate\Support\Str::before((string) $rmsNavUser->email, '@');
                                }
                            @endphp
                            <li class="nav-item dropdown ml-md-2">
                                <a id="rmsUserMenu" class="nav-link dropdown-toggle rms-user-trigger"
                                   href="#" role="button" data-toggle="dropdown" aria-haspopup="true"
                                   aria-expanded="false" aria-label="Account menu for {{ $rmsNavName }}">
                                    <i class="fas fa-user-circle" aria-hidden="true"></i>
                                    <span class="rms-user-name">{{ $rmsNavName }}</span>
                                </a>
                                <div class="dropdown-menu dropdown-menu-right rms-user-dropdown" aria-labelledby="rmsUserMenu">
                                    <div class="rms-menu-email">{{ $rmsNavUser->email }}</div>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="{{ route('applicant.dashboard') }}">
                                        <i class="fas fa-tachometer-alt mr-2" aria-hidden="true"></i>Dashboard
                                    </a>
                                    <a class="dropdown-item" href="{{ route('applicant.profile.edit') }}">
                                        <i class="fas fa-user-edit mr-2" aria-hidden="true"></i>My Profile
                                    </a>
                                    <a class="dropdown-item" href="{{ route('applicant.dashboard') }}#applicationsTitle">
                                        <i class="fas fa-clipboard-list mr-2" aria-hidden="true"></i>My Applications
                                    </a>
                                    @if($rmsNavUser->role?->status == 1)
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item" href="{{ route('admin.index') }}">
                                            <i class="fas fa-user-shield mr-2" aria-hidden="true"></i>Staff Portal
                                        </a>
                                    @endif
                                    <div class="dropdown-divider"></div>
                                    <form class="rms-logout-form" method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item text-danger">
                                            <i class="fas fa-sign-out-alt mr-2" aria-hidden="true"></i>Sign Out
                                        </button>
                                    </form>
                                </div>
                            </li>
                        @endguest
                    </ul>
                </div>
            </div>
        </nav>

        <div class="content-wrapper">
            @yield('main')
        </div>

        <aside class="control-sidebar control-sidebar-dark">
        </aside>

        <footer class="main-footer">
            <div class="float-right d-none d-sm-inline">
                @if(auth()->check() && auth()->user()->role?->status == 1)
                    <a href="{{ route('admin.index') }}">Administration</a> |
                @endif
                
                Developed by Dr. Fernando B. Enad
            </div>
            Copyright &copy; 2023. <strong><a href="/">DepEd Bohol {{ config('app.name', '') }} v1.0</a>.</strong> All rights reserved.
        </footer>
    </div>

    <script src="{{url('/')}}/vendor/jquery/jquery.min.js"></script>
    <script src="{{url('/')}}/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="{{url('/')}}/vendor/overlayScrollbars/js/jquery.overlayScrollbars.min.js"></script>
    <script src="{{url('/')}}/vendor/adminlte/dist/js/adminlte.min.js"></script>
    <script src="//cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js" ></script>
    <script src="//cdn.datatables.net/1.10.19/js/dataTables.bootstrap4.min.js" ></script>
    <script src="//cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js" ></script>
    {{-- Loaded before page scripts, so AJAX handlers can use one consistent
         401/419 recovery action without leaving their page automatically. --}}
    <script src="{{ asset('js/applicant-session.js') }}" data-sign-in-url="{{ route('applicant.login') }}"></script>
    @yield('js')
</body>
</html>
