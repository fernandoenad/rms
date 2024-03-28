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
                            <table id="vacancies" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Position name</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if(sizeof($vacancies) > 0)
                                        @foreach($vacancies as $vacancy)
                                            <tr>
                                                <td>
                                                    <a href="{{route('guest.vacancies.show', $vacancy)}}" title="View">
                                                        {{$vacancy->position_title}}
                                                    </a>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="5">0 applications found.</td>
                                        </tr>
                                    @endif
                                </tbody>
                        </table>
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
