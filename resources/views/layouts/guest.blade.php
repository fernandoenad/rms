
<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <link rel="stylesheet" href="{{url('/')}}/vendor/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="{{url('/')}}/vendor/overlayScrollbars/css/OverlayScrollbars.min.css">
    <link rel="stylesheet" href="{{url('/')}}/vendor/adminlte/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic">
    <link rel="stylesheet" href="//cdn.datatables.net/1.10.19/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.css">
    @yield('css')
    <link rel="icon" type="image/x-icon" href="{{url('/')}}/favicons/favicon.ico">
    <script nonce="f8c3cc36-9b6c-4e47-a887-d4020a95da31">(function(w,d){!function(dp,dq,dr,ds){dp[dr]=dp[dr]||{};dp[dr].executed=[];dp.zaraz={deferred:[],listeners:[]};dp.zaraz.q=[];dp.zaraz._f=function(dt){return async function(){var du=Array.prototype.slice.call(arguments);dp.zaraz.q.push({m:dt,a:du})}};for(const dv of["track","set","debug"])dp.zaraz[dv]=dp.zaraz._f(dv);dp.zaraz.init=()=>{var dw=dq.getElementsByTagName(ds)[0],dx=dq.createElement(ds),dy=dq.getElementsByTagName("title")[0];dy&&(dp[dr].t=dq.getElementsByTagName("title")[0].text);dp[dr].x=Math.random();dp[dr].w=dp.screen.width;dp[dr].h=dp.screen.height;dp[dr].j=dp.innerHeight;dp[dr].e=dp.innerWidth;dp[dr].l=dp.location.href;dp[dr].r=dq.referrer;dp[dr].k=dp.screen.colorDepth;dp[dr].n=dq.characterSet;dp[dr].o=(new Date).getTimezoneOffset();if(dp.dataLayer)for(const dC of Object.entries(Object.entries(dataLayer).reduce(((dD,dE)=>({...dD[1],...dE[1]})),{})))zaraz.set(dC[0],dC[1],{scope:"page"});dp[dr].q=[];for(;dp.zaraz.q.length;){const dF=dp.zaraz.q.shift();dp[dr].q.push(dF)}dx.defer=!0;for(const dG of[localStorage,sessionStorage])Object.keys(dG||{}).filter((dI=>dI.startsWith("_zaraz_"))).forEach((dH=>{try{dp[dr]["z_"+dH.slice(7)]=JSON.parse(dG.getItem(dH))}catch{dp[dr]["z_"+dH.slice(7)]=dG.getItem(dH)}}));dx.referrerPolicy="origin";dx.src="/cdn-cgi/zaraz/s.js?z="+btoa(encodeURIComponent(JSON.stringify(dp[dr])));dw.parentNode.insertBefore(dx,dw)};["complete","interactive"].includes(dq.readyState)?zaraz.init():dp.addEventListener("DOMContentLoaded",zaraz.init)}(w,d,"zarazData","script");})(window,document);</script>
</head>

<body class="hold-transition layout-top-nav layout-footer-fixed layout-navbar-fixed">
    <div class="wrapper">
        <nav class="main-header navbar navbar-expand-md navbar-light navbar-white">
            <div class="container">
                <a href="/" class="navbar-brand">
                    <img src="{{url('/')}}/images/bohol.png" alt="DepEd Bohol" class="brand-image img-circle elevation-3" style="opacity: .8">
                    <span class="brand-text font-weight-light">@yield('navTitle')</span>
                </a>
                <button class="navbar-toggler order-1" type="button" data-toggle="collapse" data-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse order-3" id="navbarCollapse">
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item">
                            <a class="nav-link {{ Route::currentRouteName() == 'guest.applications.index' ? 'active' : ''}}" href="{{ route('guest.index') }}">Home</a>
                        </li> 
                        <li class="nav-item">
                            <a class="nav-link {{ Route::currentRouteName() == 'guest.vacancies.index' || Route::currentRouteName() == 'guest.vacancies.apply'  ? 'active' : ''}}" href="{{ route('guest.vacancies.index') }}">Vacancies</a>
                        </li> 
                        <!--
                        <li class="nav-item">
                            <a class="nav-link {{ Route::currentRouteName() == 'guest.reports.index' ? 'active' : ''}}" href="{{ route('guest.reports.index') }}">Reports</a>
                        </li> 
                        -->
                        <li class="nav-item">
                            <a class="nav-link {{ Route::currentRouteName() == 'guest.rqas.index' ? 'active' : ''}}" href="{{ route('guest.rqas.index') }}">CAR-RQAs</a>
                        </li> 
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
                <a href="{{route('admin.index')}}">Administration</a> | 
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
    @yield('js')
</body>
</html>
