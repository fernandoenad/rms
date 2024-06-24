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
            <h4 class="text-center mb-3" align="center">COMPARATIVE ASSESSMENT RESULT - REGISTRY OF QUALIFIED APPLICANTS (CAR-RQA)</h4>

            <small>
            <table>
                <tr>
                    <td width="40%" align="left">Position: <strong>{{ $vacancy->position_title }}</strong></td>
                    <td width="40%"></td>
                    <td width="20%">Date of Final Deliberation: <strong>N/A</strong></td>
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

            </small>

            <br>
            <small>
            <table>
                <tr>
                    <td width="25%" align="left">Prepared by the Division Ranking Committee
                        <br><em>(All members should affix signature)</em>
                        <br>
                        <br>
                        <br>

                    </td>
                    <td width="25%"></td>
                    <td width="25%"></td>
                    <td width="25%"></td>
                </tr>
                <tr align="center">
                    <td>_______________________________<br>Name and Position<br>Member</td>
                    <td>_______________________________<br>Name and Position<br>Member</td>
                    <td>_______________________________<br>Name and Position<br>Member</td>
                    <td>_______________________________<br>Name and Position<br>Chairperson</td>
                </tr>
                <tr align="center">
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
                <tr align="center">
                    <td>_______________________________<br>Name and Position<br>Member</td>
                    <td>_______________________________<br>Name and Position<br>Member</td>
                    <td>_______________________________<br>Name and Position<br>Member</td>
                </tr>
            </table>
            </small>
        </div>
    @empty
    @endforelse
    <script src="{{ asset('js/app.js') }}" type="text/js"></script>
</body>
</html>