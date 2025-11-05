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
        .section {
            page-break-after: always;
        }
    </style>
</head>
<body>
    @if($level == "division")
        <div class="container-fluid mt-0">
            <table width="100%" border="0">
                <tr><td align="center"><image src="{{url('/')}}/images/header.png" height="100"></td</tr>
            </table>
            <h4 class="text-center mb-3" align="center">COMPARATIVE ASSESSMENT RESULT FOR EXPANDED RECLASSIFICATION (CAReER)</h4>

            <small>
            <table>
                <tr>
                    <td width="40%" align="left">Position: <strong>{{ $vacancy->position_title }}</strong></td>
                    <td width="40%"></td>
                    <td width="20%">Date of Final Deliberation: <strong>___________</strong></td>
                </tr>
                <tr>
                    <td align="left">Schools Division Office: <strong>DepEd Bohol</strong></td>
                    <td></td>
                    <td></td>
                </tr>
            </table>
            </small>

            <br>
            <small>
            <table border="1">
                <thead>
                    <tr class="table-danger" align="center">
                        <td scope="col" colspan="2" rowspan="2" width="18%"><strong>Name of Applicant<strong><br><em>(in no particular order)</em></td>
                        <td scope="col" rowspan="2" width="8%"><strong>Application Code<strong></td>
                        @php 
                            $template_details = json_decode($vacancy->template->template, true); 
                            $field_count = count($template_details);
                        @endphp
                        <td scope="col" colspan="{{ $field_count }}"><strong>COMPARATIVE ASSESSMENT RESULTS<strong></td>
                        <td scope="col" rowspan="2" width="8%"><strong>Remarks<strong></td>
                        <td scope="col" colspan="2" width="5%"><strong><small>For Background Investigation (Y/N)</small><strong></td>
                        <td scope="col" rowspan="2" width="7%">
                            <strong><small><small>For Recommendation</strong><br>To filed-out by the Schools Division Superintendent; Please sign opposite the name of the applicant)</small></small>
                        </td>
                    </tr>
                    <tr class="table-danger" align="center">
                        @php $trimmed = array_slice($template_details, 0, -1, true); @endphp
                        @foreach($trimmed as $key => $value)
                            <th valign="top" scope="col" width="4%">{!! str_replace('_', '<br>', $key) !!}<br><small>({{ $value }} pts)</small></th>
                        @endforeach
                        <th valign="top" scope="col" width="4%">Total<br><small>(100 pts)</small></th>
                        <th valign="top" scope="col" width="3%">Yes</th>
                        <th valign="top" scope="col" width="3%">No</th>
                    </tr>
                </thead>
                <tbody>
                    @php $i=1; @endphp 

                    @foreach($assessments as $assessment)
                        @php 
                            $assessment_details = json_decode($assessment->assessment, true); 
                            $application = App\Models\Application::where('id', '=', $assessment->application_id)->get()->first();
                            $total_points = 0;
                        @endphp 
                        <tr>
                            <td width="2%">{{ $i }}</td>
                            <td><em>[redacted due to data privacy]</em></td>
                            <td>{{ $application->application_code }}</td>
                            @foreach($assessment_details as $key => $value)
                                    @php $total_points += is_numeric($value) ? $value : 0; @endphp
                                    <td align="right">{{ is_numeric($value) ? number_format($value,3) : number_format($total_points,3) }}</td>
                            @endforeach
                            <td align="left">{{ $assessment->status == 2 ? 'Initial only. / ' . end($assessment_details) :  end($assessment_details) }}</td>
                            @php $school = App\Models\Station::find($application->station_id); @endphp
                            <td><small></small></td>
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
            <table border="0">
                <tr>
                    <td width=30%" colspan="2" align="left">Prepared by the HRMPSB
                        <br><em>(All members should affix signature)</em>
                        <br>
                        <br>
                        <br>
                        <br>
                    </td>
                    <td width="15%"></td>
                    <td width="15%"></td>
                    <td width="15%"></td>
                    <td width="8%"></td>
                    <td width="20%">Approved by:</td>     
                </tr>
                <tr align="center">
                    <td><strong>_____________________<br></strong>Name and Position<br>HRMPSB Member</td>
                    <td><strong>_____________________<br></strong>Name and Position<br>HRMPSB Member</td>
                    <td><strong>_____________________<br></strong>Name and Position<br>HRMPSB Member</td>
                    <td><strong>_____________________<br></strong>Name and Position<br>HRMPSB Member</td>
                    <td><strong>_____________________<br></strong>Name and Position<br>HRMPSB Chairperson</td>
                    <td><strong></strong></td>
                    <td valign="top"><strong>___________________________<br></strong>Schools Division Superintendent</td>
                </tr>

            </table>
            </small>
        </div>
    @else 
        @foreach($offices as $office)

        @php 
            $station_ids = App\Models\Station::where('office_id', '=', $office->id)->pluck('id');

            $assessments = App\Models\Assessment::join('applications', 'applications.id', '=', 'assessments.application_id')
                ->whereIn('applications.station_id', $station_ids)
                ->where('applications.vacancy_id', '=', $vacancy->id)
                ->where('assessments.status', '=', 3)
                ->orderBy('assessments.application_id', 'ASC')
                ->get();
        @endphp
        <div class="container-fluid mt-0 section">
        <table width="100%" border="0">
            <tr><td align="center"><image src="{{url('/')}}/images/header.png" height="100"></td</tr>
        </table>
        <h4 class="text-center mb-3" align="center">COMPARATIVE ASSESSMENT RESULT FOR EXPANDED RECLASSIFICATION (CAReER)</h4>

        <small>
        <table>
            <tr>
                <td width="40%" align="left">Position: <strong>{{ $vacancy->position_title }}</strong></td>
                <td width="40%"></td>
                <td width="20%">Date of Final Deliberation: <strong>___________</strong></td>
            </tr>
            <tr>
                <td align="left">District Office: <strong>{{ $office->name }}</strong></td>
                <td></td>
                <td></td>
            </tr>
        </table>
        </small>

        <br>
        <small>
        <table border="1">
            <thead>
                <tr class="table-danger" align="center">
                    <td scope="col" colspan="2" rowspan="2" width="18%"><strong>Name of Applicant<strong><br><em>(in no particular order)</em></td>
                    <td scope="col" rowspan="2" width="8%"><strong>Application Code<strong></td>
                    @php 
                        $template_details = json_decode($vacancy->template->template, true); 
                        $field_count = count($template_details);
                    @endphp
                    <td scope="col" colspan="{{ $field_count }}"><strong>COMPARATIVE ASSESSMENT RESULTS<strong></td>
                    <td scope="col" rowspan="2" width="8%"><strong>Remarks<strong></td>
                    <td scope="col" colspan="2" width="5%"><strong><small>For Background Investigation (Y/N)</small><strong></td>
                    <td scope="col" rowspan="2" width="7%">
                        <strong><small><small>For Recommendation</strong><br>To filed-out by the Schools Division Superintendent; Please sign opposite the name of the applicant)</small></small>
                    </td>
                </tr>
                <tr class="table-danger" align="center">
                    @php $trimmed = array_slice($template_details, 0, -1, true); @endphp
                    @foreach($trimmed as $key => $value)
                        <th valign="top" scope="col" width="4%">{!! str_replace('_', '<br>', $key) !!}<br><small>({{ $value }} pts)</small></th>
                    @endforeach
                    <th valign="top" scope="col" width="4%">Total<br><small>(100 pts)</small></th>
                    <th valign="top" scope="col" width="3%">Yes</th>
                    <th valign="top" scope="col" width="3%">No</th>
                </tr>
            </thead>
            <tbody>
                @php $i=1; @endphp 

                @forelse($assessments as $assessment)
                    @php 
                        $assessment_details = json_decode($assessment->assessment, true); 
                        $application = App\Models\Application::where('id', '=', $assessment->application_id)->get()->first();
                        $total_points = 0;
                    @endphp 
                    <tr>
                        <td width="2%">{{ $i }}</td>
                        <td><em>[redacted due to data privacy]</em></td>
                        <td>{{ $application->application_code }}</td>
                        @foreach($assessment_details as $key => $value)
                                @php $total_points += is_numeric($value) ? $value : 0; @endphp
                                <td align="right">{{ is_numeric($value) ? number_format($value,3) : number_format($total_points,3) }}</td>
                        @endforeach
                        <td align="left">{{ $assessment->status == 2 ? 'Initial only. / ' . end($assessment_details) :  end($assessment_details) }}</td>
                        @php $school = App\Models\Station::find($application->station_id); @endphp
                        <td><small></small></td>
                        <td></td>
                        <td>{{ $application->age >= 55 ? "Retirable" : "" }}</td>

                    </tr>
                    @php $i++; @endphp 
                @empty
                    <tr><td><strong>***No applications found.***</strong></td></tr>
                @endforelse
            </tbody>
        </table>
        </small>

        <br>
        <small>
        <table border="0">
            <tr>
                <td width=30%" colspan="2" align="left">Prepared by the HRMPSB
                    <br><em>(All members should affix signature)</em>
                    <br>
                    <br>
                    <br>
                    <br>
                </td>
                <td width="15%"></td>
                <td width="15%"></td>
                <td width="15%"></td>
                <td width="8%"></td>
                <td width="20%">Approved by:</td>     
            </tr>
            <tr align="center">
                <td><strong>_____________________<br></strong>Name and Position<br>HRMPSB Member</td>
                <td><strong>_____________________<br></strong>Name and Position<br>HRMPSB Member</td>
                <td><strong>_____________________<br></strong>Name and Position<br>HRMPSB Member</td>
                <td><strong>_____________________<br></strong>Name and Position<br>HRMPSB Member</td>
                <td><strong>_____________________<br></strong>Name and Position<br>HRMPSB Chairperson</td>
                <td><strong></strong></td>
                <td valign="top"><strong>___________________________<br></strong>Schools Division Superintendent</td>
            </tr>

        </table>
        </small>
        </div>
        @endforeach
    @endif


    <script src="{{ asset('js/app.js') }}" type="text/js"></script>
</body>
</html>