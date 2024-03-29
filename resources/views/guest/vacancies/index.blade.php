@extends('layouts.guest')

@section('title')
    {{ config('app.name', '') }} | Vacancies
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
                    <h1 class="m-0">Vacancies</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{route('guest.index')}}">Home</a></li>
                        <li class="breadcrumb-item active">Vacancies</li>
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
                            <h3 class="card-title">List</h3>
                        </div>
                        <div class="card-body">
                            <div class="panel-group" id="accordion">
                                @if(sizeof($vacancies))
                                    @foreach($vacancies as $vacancy)
                                        <div class="card">
                                            <div class="card-header">
                                                <h4 class="panel-title">
                                                <a data-toggle="collapse" data-parent="#accordion" href="#collapse{{ $vacancy->id }}">{{ $vacancy->position_title }}</a>
                                                </h4>
                                            </div>
                                            <div id="collapse{{ $vacancy->id }}" class="panel-collapse collapse">
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-lg-4">
                                                            <h4>Salary Grade: <small><small>{{$vacancy->salary_grade}}</small></small></h4>
                                                            <h4>Base Pay: <small><small>{{number_format($vacancy->base_pay,2)}}</small></small></h4>
                                                            <h4>Office: <small><small>{{$vacancy->getOffice()}}</small></small></h4>
                                                            <h4>Vacancy: <small><small>{{$vacancy->vacancy}}</small></small></h4>
                                                            <h4>Cycle: <small><small>{{$vacancy->cycle}}</small></small></h4>
                                                        </div>
                                                        <div class="col-lg-6">
                                                            <h4>Qualifications:</h4>
                                                            {!!nl2br($vacancy->qualifications)!!}
                                                            
                                                        </div>
                                                        <div class="col-lg-2">
                                                        <a href="{{route('guest.vacancies.apply', $vacancy)}}" class="btn btn-success btn-lg">
                                                                <i class="fas fa-file-signature"></i> Apply
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach 
                                @else 
                                    0 vacancies found.
                                @endif 
                            </div>
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
        $(document).ready(function(){
            // Collapse all panels initially
            $('.panel-collapse in').collapse();

            // Handle accordion behavior
            $('#accordion').on('show.bs.collapse', function (e) {
                // Hide all other panels when one is shown
                $('#accordion .panel-collapse').not($(e.target)).collapse('hide');
            });
        });
    </script>
@endsection
