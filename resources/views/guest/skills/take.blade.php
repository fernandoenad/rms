@extends('layouts.guest')
@section('title'){{ config('app.name') }} | Skills Test@overwrite
@section('main')
<section class="content bg-light min-vh-100"><div class="container py-3">
<div class="sticky-top bg-white border rounded p-2 mb-3 d-flex justify-content-between align-items-center">
<div><strong>{{ $attempt->skillTest->title }}</strong><div class="small text-muted">Skills Test</div></div>
<span id="skillTimer" class="badge badge-info p-2">--:--</span>
</div>
@if(session('status_assessment'))<div class="alert alert-info">{{ session('status_assessment') }}</div>@endif
<div class="card"><div class="card-body">
<h5>Task</h5><p style="white-space:pre-wrap">{{ $attempt->skillTest->instructions }}</p>
@if($attempt->skillTest->expected_output)<div class="alert alert-light border"><strong>Expected output</strong><br>{{ $attempt->skillTest->expected_output }}</div>@endif
</div></div>

@php $modes=$attempt->skillTest->submission_modes ?: ['inline']; @endphp
@if(in_array('inline',$modes,true))
<div class="card"><div class="card-header"><strong>Write Response</strong><span id="inlineSave" class="float-right small text-muted"></span></div>
<div class="card-body"><textarea id="inlineResponse" class="form-control" rows="14" placeholder="Type your response here...">{{ optional($submission)->inline_response }}</textarea></div></div>
@endif

@if(in_array('file',$modes,true))
<div class="card"><div class="card-header"><strong>Upload File</strong></div><div class="card-body">
<form method="post" enctype="multipart/form-data" action="{{ route('guest.skills.attempts.upload',$attempt) }}">@csrf
<input type="file" name="file" class="form-control-file mb-2" required>
@if($submission && $submission->original_filename)<div class="small text-success mb-2">✓ Saved file: {{ $submission->original_filename }}</div>@endif
<button class="btn btn-outline-primary btn-block">Upload / Replace File</button>
</form></div></div>
@endif

<form method="post" action="{{ route('guest.skills.attempts.submit',$attempt) }}" onsubmit="return confirm('Submit your skills test now? You will not be able to edit it afterward.');">@csrf
<button class="btn btn-success btn-lg btn-block mb-5">Submit Skills Test</button>
</form>
</div></section>
@overwrite
@section('css')
<style>@media(max-width:576px){textarea.form-control{font-size:16px}.container{padding-left:12px;padding-right:12px}}</style>
@overwrite
@section('js')
<script>
(() => {
let seconds={{ (int)$remainingSeconds }}, timer=document.getElementById('skillTimer');
function tick(){let m=Math.floor(Math.max(seconds,0)/60),s=Math.max(seconds,0)%60;timer.textContent=String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');if(seconds<=0){location.reload();return;}seconds--;setTimeout(tick,1000)} tick();
const box=document.getElementById('inlineResponse'), state=document.getElementById('inlineSave');
let t;
if(box){box.addEventListener('input',()=>{state.textContent='Saving…';clearTimeout(t);t=setTimeout(async()=>{try{let r=await fetch(@json(route('guest.skills.attempts.inline',$attempt)),{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':@json(csrf_token()),'Accept':'application/json'},body:JSON.stringify({inline_response:box.value})});if(!r.ok)throw 0;state.textContent='✓ Saved';state.className='float-right small text-success';}catch(e){state.textContent='Not saved — check connection';state.className='float-right small text-danger';}},1200)})}
})();
</script>
@overwrite
