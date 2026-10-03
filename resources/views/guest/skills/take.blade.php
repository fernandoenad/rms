@extends('layouts.guest')
@section('title'){{ config('app.name') }} | Skills Test@overwrite
@section('main')
<section class="content bg-light min-vh-100 {{ !empty($largeText) ? 'assessment-large-text' : '' }}"><div class="container py-3">
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
<div class="card-body">
<textarea id="inlineResponse" class="form-control" rows="14" placeholder="Type your response here...">{{ optional($submission)->inline_response }}</textarea>
<div id="pasteNotice" class="small text-muted mt-2">Paste and drag/drop text are disabled for this skills-test response. Type your response directly in the assessment.</div>
</div></div>
@endif

@if(in_array('file',$modes,true))
<div class="card"><div class="card-header"><strong>Upload File</strong></div><div class="card-body">
<form method="post" enctype="multipart/form-data" action="{{ route('guest.skills.attempts.upload',$attempt) }}">@csrf
<input type="file" name="file" class="form-control-file mb-2" required>
@if($submission && $submission->original_filename)
<div class="small text-success mb-2">✓ Latest saved file (v{{ $submission->version }}): {{ $submission->original_filename }}</div>
@endif
<button class="btn btn-outline-primary btn-block">Upload New Version</button>
<small class="text-muted d-block mt-2">Previous uploaded versions are retained in the audit history.</small>
</form></div></div>
@endif

<form method="post" action="{{ route('guest.skills.attempts.submit',$attempt) }}" onsubmit="return confirm('Submit your skills test now? You will not be able to edit it afterward.');">@csrf
<button class="btn btn-success btn-lg btn-block mb-5">Submit Skills Test</button>
</form>
</div></section>
@overwrite
@section('css')
<style>
.assessment-large-text{font-size:1.18rem}
.assessment-large-text textarea.form-control{font-size:1.2rem}
@media(max-width:576px){textarea.form-control{font-size:16px}.assessment-large-text textarea.form-control{font-size:19px}.container{padding-left:12px;padding-right:12px}}
</style>
@overwrite
@section('js')
<script>
(() => {
    let seconds={{ (int)$remainingSeconds }};
    const timer=document.getElementById('skillTimer');
    const box=document.getElementById('inlineResponse');
    const state=document.getElementById('inlineSave');
    const saveUrl=@json(route('guest.skills.attempts.inline',$attempt));
    const csrf=@json(csrf_token());
    const pendingKey='rms_skill_{{ $attempt->id }}_pending_inline';
    const eventUrl=@json(route('guest.skills.attempts.event',$attempt));
    let debounceTimer=null;
    let saving=false;
    let pendingValue=null;

    function tick(){
        const m=Math.floor(Math.max(seconds,0)/60);
        const s=Math.max(seconds,0)%60;
        timer.textContent=String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');

        if(seconds<=0){
            location.reload();
            return;
        }

        seconds--;
        setTimeout(tick,1000);
    }

    async function persistInline(value){
        pendingValue=value;
        localStorage.setItem(pendingKey,value);

        if(saving || !navigator.onLine) {
            if(state){
                state.textContent='Waiting for connection…';
                state.className='float-right small text-warning';
            }
            return;
        }

        saving=true;

        try {
            while(pendingValue !== null && navigator.onLine){
                const current=pendingValue;
                pendingValue=null;

                if(state){
                    state.textContent='Saving…';
                    state.className='float-right small text-warning';
                }

                const response=await fetch(saveUrl,{
                    method:'POST',
                    headers:{
                        'Content-Type':'application/json',
                        'X-CSRF-TOKEN':csrf,
                        'Accept':'application/json'
                    },
                    body:JSON.stringify({inline_response:current})
                });

                if(response.status===409){
                    location.reload();
                    return;
                }

                if(!response.ok){
                    throw new Error('save failed');
                }

                if(pendingValue===null){
                    localStorage.removeItem(pendingKey);
                    if(state){
                        state.textContent='✓ Saved';
                        state.className='float-right small text-success';
                    }
                }
            }
        } catch(e){
            if(state){
                state.textContent='Not saved — will retry when connected';
                state.className='float-right small text-danger';
            }
        } finally {
            saving=false;

            if(pendingValue !== null && navigator.onLine){
                setTimeout(()=>persistInline(pendingValue),800);
            }
        }
    }

    async function logEvent(type){
        try {
            await fetch(eventUrl,{
                method:'POST',
                headers:{
                    'Content-Type':'application/json',
                    'X-CSRF-TOKEN':csrf,
                    'Accept':'application/json'
                },
                body:JSON.stringify({event_type:type})
            });
        } catch(e) {
            // Audit events must never block the assessment UI.
        }
    }

    document.addEventListener('visibilitychange',()=>{
        logEvent(document.hidden ? 'tab_hidden' : 'tab_visible');
    });
    window.addEventListener('offline',()=>logEvent('connection_lost'));
    window.addEventListener('online',()=>logEvent('connection_restored'));
    window.addEventListener('pageshow',(event)=>{
        if(event.persisted) logEvent('page_refreshed');
    });

    tick();

    if(box){
        function blockImportedText(event){
            event.preventDefault();
            const notice=document.getElementById('pasteNotice');
            if(notice){
                notice.textContent='Pasting or dropping text is not allowed in this skills-test response.';
                notice.className='small text-danger font-weight-bold mt-2';
                setTimeout(()=>{
                    notice.textContent='Paste and drag/drop text are disabled for this skills-test response. Type your response directly in the assessment.';
                    notice.className='small text-muted mt-2';
                },3000);
            }
        }

        box.addEventListener('paste',blockImportedText);
        box.addEventListener('drop',blockImportedText);
        box.addEventListener('beforeinput',(event)=>{
            if(event.inputType==='insertFromPaste' || event.inputType==='insertFromDrop'){
                blockImportedText(event);
            }
        });

        const recovered=localStorage.getItem(pendingKey);
        if(recovered !== null && recovered !== box.value){
            box.value=recovered;
            if(state){
                state.textContent='Recovered unsaved response — saving…';
                state.className='float-right small text-warning';
            }
            persistInline(recovered);
        }

        box.addEventListener('input',()=>{
            clearTimeout(debounceTimer);
            localStorage.setItem(pendingKey,box.value);
            if(state){
                state.textContent='Waiting to save…';
                state.className='float-right small text-muted';
            }
            debounceTimer=setTimeout(()=>persistInline(box.value),900);
        });

        window.addEventListener('online',()=>{
            const pending=localStorage.getItem(pendingKey);
            if(pending !== null){
                persistInline(pending);
            }
        });

        window.addEventListener('offline',()=>{
            if(state){
                state.textContent='Offline — response kept on this device';
                state.className='float-right small text-warning';
            }
        });
    }
})();
</script>
@overwrite
