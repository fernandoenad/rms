<!DOCTYPE html>
<html>
<head>
    <title>Message from RMS (for Applcation ID: {{ $data['subject'] }})</title>
</head>
<body>
    <p>Re: <strong>Application ID# {{ $data['application'] }}<strong></p>
    <p>Hello, <strong>{{ $data['name'] }}</strong>!</p>
    <p>{{ $data['message'] }}</p>
    <p>
        Best,<br>
        <br>
        <strong>Secretariat</strong>
        <br>
        HRMPSB
    </p>
</body>
</html>