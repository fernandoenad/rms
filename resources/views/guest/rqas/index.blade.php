@extends('layouts.guest')

@section('title')
    {{ config('app.name', '') }} | CAR-RQAs
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
                    <h1 class="m-0">CAR-RQAs</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{route('guest.index')}}">Home</a></li>
                        <li class="breadcrumb-item active">CAR-RQAs</li>
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
                            <table id="applications" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Cycle</th>
                                        <th>Position title</th>
                                        <th>Office level</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if(sizeof($vacancies) > 0)
                                        @foreach($vacancies as $vacancy)
                                            <tr>
                                                <td>{{$vacancy->cycle}}</td>
                                                <td>{{$vacancy->position_title}}</a>
                                                </td>
                                                <td>{{$vacancy->getOffice()}}</td>
                                                <td>
                                                    @if($vacancy->level2_status == 2)
                                                        Completed 
                                                    @else 
                                                        Pending 
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($vacancy->level2_status == 2)
                                                        <a class="btn btn-xs btn-success" href="{{route('guest.rqas.show', $vacancy)}}" target="_blank">
                                                            <i class="fas fa-eye"></i> View
                                                        </a>
                                                    @else 
                                                        N/A 
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="6">0 vacancies found.</td>
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
            $("#applications").DataTable({
                "responsive": true,
                "lengthChange": true,
                "autoWidth": false,
                "pageLength": 50,
                "lengthMenu": [50, 100, 1000, 2000, 3000, 4000, 5000], // You can customize these values
                "ordering": false, // Disable initial sorting
                "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
            }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
        });
    </script>
@endsection
