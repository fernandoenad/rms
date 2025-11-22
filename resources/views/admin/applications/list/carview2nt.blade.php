<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ config('app.name', '') }} | CAR Sheet</title>
    <style>
        @page {
            size: legal landscape; /* Set the page size to landscape orientation */
            margin: .5cm; /* Optionally adjust the page margins */
        }

        /* Add any additional styles for your content */
        body {
            font-family: Arial, sans-serif;
            padding: 0px;
        }

        /* Example styles for a table */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        td{
            padding: 5px;
        }
        <style>
        /* Add a page break before each h1 element */
        h1 {
            page-break-before: always;
        }

        /* Add a page break after each section div */
        .section {
            page-break-after: always;
        }
    </style>
</head>
<body>
    @forelse($offices as $office)
        <div class="container-fluid mt-0 section">
            <table width="100%" border="0">
                <tr><td align="center"><image src="{{url('/')}}/images/header.png" height="100"></td</tr>
            </table>
            <h4 class="text-center mb-3" align="center">COMPARATIVE ASSESSMENT RESULT (CAR)</h4>

            <small>
            <table>
                <tr>
                    <td width="40%" align="left">Position: <strong>{{ $vacancy->position_title }}</strong></td>
                    <td width="40%"></td>
                    <td width="20%">Date of Final Deliberation: <strong>___________</strong></td>
                </tr>
                <tr>
                    <td align="left">Office: <strong>{{$office->name}} District (DepEd Bohol)</strong></td>
                    <td></td>
                    <td></td>
                </tr>
            </table>
            </small>
            <br>
            <small>
            @php $officeAssessments = $assessmentsByOffice->get($office->id, collect()); @endphp
            @continue($officeAssessments->isEmpty())

            <table border="1">
                <thead>
                    <tr class="table-danger" align="center">
                        <td scope="col" colspan="2" rowspan="2" width="20%"><strong>Name of Application<strong></td>
                        <td scope="col" rowspan="2" width="8%"><strong>Application Code<strong></td>
                        <td scope="col" colspan="11" width="8%"><strong>COMPARATIVE ASSESSMENT RESULTS<strong></td>
                        <td scope="col" rowspan="2" width="8%"><strong>Remarks<strong></td>
                        <td scope="col" rowspan="2" width="14%"><strong>School Applied for<strong></td>
                        <td scope="col" colspan="2" width="5%"><strong><small>For Background Investigation (Y/N)</small><strong></td>
                        <td scope="col" rowspan="2" width="7%">
                            <strong><small><small>For Appointment</strong><br>To filed-out by the Appointing Officer/ Authority; Please sign opposite the name of the applicant)</small></small></td>
                        <td scope="col" rowspan="2" width="7%">
                            <strong><small>Status of Appointment</strong><br>(Based on availability of PBET/ LET/LEPT)</small></td>
                    </tr>
                    <tr class="table-danger" align="center">
                        @php $template = json_decode($vacancy->template->template, true); array_pop($template); @endphp 
                        @foreach($template as $key => $value)
                            <th scope="col" width="4%"><small><small>{{str_replace('_', ' ', $key)}} <br>({{$value}} pts)</small></small></th>

                        @endforeach
                        <th scope="col" width="4%">Total <small>(100 pts)</small></th>
                        <th scope="col" width="3%">Yes</th>
                        <th scope="col" width="3%">No</th>
                    </tr>
                </thead>
                <tbody>
                    @php $i=1; @endphp 

                    @foreach($officeAssessments as $assessment)
                        @php 
                            $assessment_details = json_decode($assessment->assessment, true); 
                            $application = $assessment->application;
                            $total_points = 0;
                        @endphp 
                        <tr>
                            <td width="2%">{{ $i }}</td>
                            <td>{{ strtoupper(optional($application)->getFullname()) }}</td>
                            <td>{{ $application?->application_code }}</td>
                            @foreach($assessment_details as $key => $value)
                                    @php $total_points += is_numeric($value) ? $value : 0; @endphp
                                    <td align="right">{{ is_numeric($value) ? number_format($value,2) : number_format($total_points,2) }}</td>
                            @endforeach
                            <td align="left">{{ $assessment->status == 2 ? 'Initial only. / ' . end($assessment_details) :  end($assessment_details) }}</td>
                            @php $school = optional($application)->station; @endphp
                            <td><small>{{ $school?->code }}-{{ substr($school?->name, 0, 20) }}</small></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        @php $i++; @endphp 
                    @endforeach
                </tbody>
            </table>

            </small>

            <br>
            <small>
                @if($vacancy->level2_status==3)
                    @include('admin.applications.list.footer2')
                @else 
                    @include('admin.applications.list.footer')
                @endif
            </small>
        </div>
    @empty
    @endforelse
    <script src="{{ asset('js/app.js') }}" type="text/js"></script>
</body>
</html>
