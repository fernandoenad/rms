@extends('layouts.guest')

@section('title')
    {{ config('app.name', '') }} | Vacancy Details
@endsection

@section('css')
@endsection

@section('navTitle')
    {{ config('app.name', '') }}
@endsection

@section('main')
    <section class="content-header">
        <div class="container">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Vacancy Details</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{route('guest.index')}}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{route('guest.vacancies.index', $vacancy)}}">Vacancies</a></li>
                        <li class="breadcrumb-item active">Details</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-clipboard mr-1"></i>{{$vacancy->position_title}}</h3>
                            <div class="card-tools">
                                <div class="btn-group">
                                    <a href="{{route('guest.vacancies.index')}}" class="btn btn-default btn-sm">
                                        <i class="fas fa-reply"></i> Back
                                    </a>
                                    <a href="{{route('guest.vacancies.apply', $vacancy)}}" class="btn btn-primary btn-sm">
                                        <i class="fas fa-file-signature"></i> Apply
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <h3>Salary Grade: <small><small>{{$vacancy->salary_grade}}</small></small></h3>
                            <h3>Base Pay: <small><small>{{number_format($vacancy->base_pay,2)}}</small></small></h3>
                            <h3>Office: <small><small>{{$vacancy->getOffice()}}</small></small></h3>
                            <h3>Qualifications:</h3>
                            {!!nl2br($vacancy->qualifications)!!}
                            <h3>Vacancy: <small><small>{{$vacancy->vacancy}}</small></small></h3>
                            <h3>Cycle: <small><small>{{$vacancy->cycle}}</small></small></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('js')
    <script> console.log('Hi!'); </script>
    <script>
        $(function () {
            $("#vacancies").DataTable({
            "responsive": true, "lengthChange": false, "autoWidth": false, "pageLength": 7,
            "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
            }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
        });
    </script>
@endsection
