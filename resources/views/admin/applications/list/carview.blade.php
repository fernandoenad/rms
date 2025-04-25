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
    </style>
</head>
<body>
    <div class="container-fluid mt-0">
        <table width="100%" border="0">
            <tr><td align="center"><image src="{{url('/')}}/images/header.png" height="100"></td</tr>
        </table>
        <h4 class="text-center mb-3" align="center">COMPARATIVE ASSESSMENT RESULT - REGISTRY OF QUALIFIED APPLICANTS (CAR-RQA)</h4>

        <small>
        <table>
            <tr>
                <td width="40%" align="left">Position: <strong>{{ $vacancy->position_title }}</strong></td>
                <td width="40%"></td>
                <td width="20%">Date of Final Deliberation: <strong>___________</strong></td>
            </tr>
            <tr>
                <td align="left">Office: <strong>DepEd Bohol</strong></td>
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
                    <td scope="col" colspan="2" rowspan="2"><strong>Name of Application<strong></td>
                    <td scope="col" rowspan="2" width="8%"><strong>Application Code<strong></td>
                    <td scope="col" colspan="7" width="8%"><strong>COMPARATIVE ASSESSMENT RESULTS<strong></td>
                    <td scope="col" rowspan="2" width="8%"><strong>Remarks<strong></td>
                    <td scope="col" rowspan="2" width="14%"><strong>School Applied for<strong></td>
                    <td scope="col" colspan="2" width="5%"><strong><small>For Background Investigation (Y/N)</small><strong></td>
                    <td scope="col" rowspan="2" width="7%">
                        <strong><small><small>For Appointment</strong><br>To filed-out by the Appointing Officer/ Authority; Please sign opposite the name of the applicant)</small></small></td>
                    <td scope="col" rowspan="2" width="7%">
                        <strong><small>Status of Appointment</strong><br>(Based on availability of PBET/ LET/LEPT)</small></td>
                </tr>
                <tr class="table-danger" align="center">
                    <th scope="col" width="4%">Education <small>(10 pts)</small></th>
                    <th scope="col" width="4%">Training <small>(10 pts)</small></th>
                    <th scope="col" width="4%">Experience <small>(10 pts)</small></th>
                    <th scope="col" width="4%">Rating <small>(10 pts)</small></th>
                    <th scope="col" width="4%">COI<br><small>(35 pts)</small></th>
                    <th scope="col" width="4%">NCOI<br><small>(25 pts)</small></th>
                    <th scope="col" width="4%">Total <small>(100 pts)</small></th>
                    <th scope="col" width="3%">Yes</th>
                    <th scope="col" width="3%">No</th>
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
                        <td>{{ strtoupper($application->getFullname()) }}</td>
                        <td>{{ $application->application_code }}</td>
                        @foreach($assessment_details as $key => $value)
                                @php $total_points += is_numeric($value) ? $value : 0; @endphp
                                <td align="right">{{ is_numeric($value) ? number_format($value,2) : number_format($total_points,2) }}</td>
                        @endforeach
                        <td align="left">{{ $assessment->status == 2 ? 'Initial only. / ' . end($assessment_details) :  end($assessment_details) }}</td>
                        @php $school = App\Models\Station::find($application->station_id); @endphp
                        @php $district = App\Models\Office::find($school->office_id); @endphp
                        <td><small>{{ $school->code }}-{{ substr($school->name, 0, 20) }}</small></td>
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
        <table border="0">
            <tr>
                <td width="22%" align="left">Prepared by the Division Ranking Committee
                    <br><em>(All members should affix signature)</em>
                    <br>
                    <br>
                </td>
                <td width="18%"></td>
                <td width="28%"></td>
                <td width="4%"></td>
                <td width="28%">Approved by:</td>
                           
            </tr>
            <tr align="center">
                <td><img src="{{url('images/signatures/enad.png')}}" height="50"><br><strong>FERNANDO B. ENAD PhD</strong><br>Master Teacher II<br>HRMPSB Secretariat Head</td>
                <td><img src="{{url('images/signatures/apale.png')}}" height="50"><br><strong>JUDITH S. APALE</strong><br>Administrative Officer IV<br>HRMPSB Member</td>
                <td><img src="{{url('images/signatures/albutra.png')}}" height="50"><br><strong>FERMIN M. ALBUTRA MPA</strong><br>Administrative Officer V<br>HRMPSB Member</td>
                <td>&nbsp;</td>
                <td rowspan="4">
                    <img src="{{url('images/signatures/luarez.png')}}" height="50"><br>
                    <strong>FAY C. LUAREZ EdD, PhD-TM, CESO VI</strong><br>Asst. Schools Division Superintendent<br>OIC, Office of the Schools Division Superintendent
                </td> 
            </tr>
            <tr align="center">
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <tr align="center">
                <td>&nbsp;</td>
                <td><img src="{{url('images/signatures/calamba.png')}}" height="50"><br><strong>RENATO D. CALAMBA PhD</strong><br>President, PSDSA<br>HRMPSB Member</td>
                <td><img src="{{url('images/signatures/ompad.png')}}" height="50"><br><strong>EDUARDO A. OMPAD EdD, CESE</strong><br>Asst. Schools Division Superintendent<br>HRMPSB Chairman</td>
                <td>&nbsp;</td>

            </tr>
        </table>
    </small>
    </div>
    <script src="{{ asset('js/app.js') }}" type="text/js"></script>
</body>
</html>