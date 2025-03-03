@extends('layouts.guest')

@php
    $title = "Completion Report";
    $app_name = config('app.name', '') . ' [Admin]';
@endphp 

@section('title', config('app.name', '') . ' | ' . $title)

@section('main')
    <section class="content-header">
        <div class="container">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">{{$title}}</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{route('guest.index')}}">Home</a></li>
                        <li class="breadcrumb-item active">{{$title}}</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>



    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">List</h3>
                    </div>
                    <div class="card-body">
                        <table id="list" class="table table-sm table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th width="30%">District</th>
                                    <th class="text-right">Untagged applications</th>
                                    <th class="text-right">Tagged applications</th>
                                    <th class="text-right">Pending (SSC)</th>
                                    <th class="text-right">Completed (SSC)</th>
                                    <th class="text-right">Preliminary Performance</th>
                                    <th class="text-right">Pending (DSC)</th>
                                    <th class="text-right">Completed (DSC)</th>
                                    <th class="text-right">Comparative Assessment Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <th>Summary</th>
                                    @php 
                                        $untagged = $applications->where('station_id', '=', -1)->count();
                                        $tagged = $applications->where('station_id', '>', 0)->count()
                                    @endphp
                                    <td class="text-right">{{number_format($untagged, 0) }}</td>
                                    <td class="text-right">{{number_format($tagged, 0) }}</td>
                                    <td class="text-right">{{number_format($src_p, 0) }}</td>
                                    <td class="text-right">{{number_format($src_c, 0) }}</td>
                                    <th class="text-right">
                                        @if($tagged == 0)
                                            N/A
                                        @else
                                            {{number_format($src_c / $tagged * 100, 2) }}%
                                        @endif
                                    </th>
                                    <td class="text-right">{{number_format($drc_p, 0) }}</td>
                                    <td class="text-right">{{number_format($drc_c, 0) }}</td>
                                    <th class="text-right">
                                        @if($tagged == 0)
                                            N/A
                                        @else
                                            {{number_format($drc_c / $tagged * 100, 2) }}%
                                        @endif
                                    </th>

                                </tr>
                                
                                @forelse($offices as $office)
                                    @php 
                                        $stations = App\Models\Station::where('office_id', '=', $office->id)->pluck('id');
                                        
                                        $src_t = App\Models\Application::join('vacancies', 'vacancies.id', '=', 'applications.vacancy_id')
                                            ->where('vacancies.cycle', $cycle)
                                            ->whereIn('station_id', $stations)->count();
                                        $src_p = App\Models\Application::join('vacancies', 'vacancies.id', '=', 'applications.vacancy_id')
                                            ->join('assessments', 'assessments.application_id', '=', 'applications.id')
                                            ->whereIn('applications.station_id', $stations)
                                            ->where('assessments.status', '=', 1)
                                            ->where('vacancies.cycle', $cycle)
                                            ->distinct('applications.id') // Ensure distinct applications are counted
                                            ->count('applications.id');
                                        $src_c = App\Models\Application::join('vacancies', 'vacancies.id', '=', 'applications.vacancy_id')
                                            ->join('assessments', 'assessments.application_id', '=', 'applications.id')
                                            ->whereIn('applications.station_id', $stations)
                                            ->where('assessments.status', '>=', 2)
                                            ->where('vacancies.cycle', $cycle)
                                            ->distinct('applications.id') // Ensure distinct applications are counted
                                            ->count('applications.id');
                                        $drc_p = App\Models\Application::join('vacancies', 'vacancies.id', '=', 'applications.vacancy_id')
                                            ->join('assessments', 'assessments.application_id', '=', 'applications.id')
                                            ->whereIn('applications.station_id', $stations)
                                            ->where('assessments.status', '=', 2)
                                            ->where('vacancies.cycle', $cycle)
                                            ->distinct('applications.id') // Ensure distinct applications are counted
                                            ->count('applications.id');
                                        $drc_c = App\Models\Application::join('vacancies', 'vacancies.id', '=', 'applications.vacancy_id')
                                            ->join('assessments', 'assessments.application_id', '=', 'applications.id')
                                            ->whereIn('applications.station_id', $stations)
                                            ->where('assessments.status', '>=', 3)
                                            ->where('vacancies.cycle', $cycle)
                                            ->distinct('applications.id') // Ensure distinct applications are counted
                                            ->count('applications.id');
                                    @endphp
                                    <tr>
                                        <td><a href="{{route('guest.reports.show', $office)}}">{{$office->name}}</a></td>
                                        <td class="text-right">-</td>
                                        <td class="text-right">{{number_format($src_t, 0) }}</td>
                                        <td class="text-right">{{number_format($src_p, 0) }}</td>
                                        <td class="text-right">{{number_format($src_c, 0) }}</td>
                                        <th class="text-right">
                                            @if ($src_t != 0)
                                                {{ number_format($src_c / $src_t * 100, 2) }}%
                                            @else
                                                N/A
                                            @endif
                                        </th>
                                        <td class="text-right">{{number_format($drc_p, 0) }}</td>
                                        <td class="text-right">{{number_format($drc_c, 0) }}</td>
                                        <th class="text-right">
                                            @if ($src_t != 0)
                                                {{number_format($drc_c / $src_t * 100, 2) }}%
                                            @else
                                                N/A
                                            @endif
                                        </th>
                                    </tr>
                                @empty
                                @endforelse
                            </tbody>

                    </table>
                </div>
            </div>
        </div>
    </div>
@stop

@section('footer')
    @include('layouts.footer')
@stop

@section('css')
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/jquery.dataTables.min.css">
<!-- Buttons CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.1.1/css/buttons.dataTables.min.css">
@stop

@section('plugins.Datatables', true)

@section('js')
    <script> console.log('Hi!'); </script>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
    <!-- Buttons JS -->
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.flash.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.colVis.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>

    <script>
        $(function () {
            $("#list").DataTable({
                "responsive": true,
                "lengthChange": true,
                "autoWidth": false,
                "pageLength": 100,
                "lengthMenu": [5, 10, 25, 50, 100], // You can customize these values
                "ordering": false, // Disable initial sorting
                "dom": 'Blfrtip', // Ensure the buttons are displayed
                "buttons": ["excel", "pdf", "print", "colvis"]
            }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
        });
    </script>
@stop