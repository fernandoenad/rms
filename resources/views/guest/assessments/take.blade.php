@extends('layouts.guest')

@section('title')
    {{ config('app.name', '') }} | Take Assessment
@overwrite

@section('main')
<section class="content assessment-page">
    <div class="container py-2">
        <div class="sticky-top bg-white border-bottom py-2 mb-3 assessment-toolbar">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong>{{ $exam->title }}</strong>
                    <div class="small text-muted"><span id="questionCounter"></span></div>
                </div>
                <span class="badge badge-info p-2" id="countdown">--:--</span>
            </div>
            <div class="small mt-1" id="saveState" aria-live="polite">
                <span class="text-muted">Select an answer to save it automatically.</span>
            </div>
        </div>

        <div class="alert alert-light border small">
            Your answers save automatically. Refreshing, changing tabs, locking your phone, or temporarily losing connection will not submit the exam.
            The assessment submits only when the allotted time ends.
        </div>

        @foreach($items as $index => $item)
            @php
                $answer = $attempt->answers->firstWhere('written_exam_id', $item->id);
                $displayOptions = $item->getRelation('displayOptions');
            @endphp
            <article class="card shadow-sm exam-item" data-index="{{ $index }}" style="display:none">
                <div class="card-body">
                    <p class="mb-3 exam-question"><strong>{{ $index + 1 }}.</strong> {{ $item->question }}</p>

                    @foreach($displayOptions as $optionIndex => $option)
                        @php $letter = chr(65 + $optionIndex); @endphp
                        <label class="option-card d-flex align-items-start border rounded p-3 mb-2 w-100"
                               for="item_{{ $item->id }}_{{ $option->id }}">
                            <input class="answer-radio mt-1 mr-3"
                                   type="radio"
                                   name="item_{{ $item->id }}"
                                   id="item_{{ $item->id }}_{{ $option->id }}"
                                   value="{{ $option->id }}"
                                   data-item="{{ $item->id }}"
                                   {{ ($answer && (int)$answer->selected_option_id === (int)$option->id) ? 'checked' : '' }}>
                            <span><strong>{{ $letter }}.</strong> {{ $option->option_text }}</span>
                        </label>
                    @endforeach
                </div>
            </article>
        @endforeach

        <div class="d-flex justify-content-between align-items-center my-3">
            <button type="button" class="btn btn-outline-secondary btn-lg" id="prevBtn">Previous</button>
            <button type="button" class="btn btn-outline-primary btn-lg" id="questionsBtn" data-toggle="modal" data-target="#questionModal">Questions</button>
            <button type="button" class="btn btn-primary btn-lg" id="nextBtn">Next</button>
        </div>

        <div class="text-center text-muted small mb-5">
            There is no early-submit button. Your saved responses will be finalized automatically when time expires.
        </div>
    </div>
</section>

<div class="modal fade" id="questionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Questions</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap" id="questionNav">
                    @foreach($items as $index => $item)
                        @php $answer = $attempt->answers->firstWhere('written_exam_id', $item->id); @endphp
                        <button type="button"
                                class="btn btn-sm m-1 nav-btn {{ $answer ? 'btn-success' : 'btn-outline-secondary' }}"
                                style="min-width:48px;min-height:44px"
                                data-index="{{ $index }}">
                            {{ $index + 1 }}
                        </button>
                    @endforeach
                </div>
                <hr>
                <div class="small text-muted">Green = saved answer. Outline = unanswered.</div>
            </div>
        </div>
    </div>
</div>
@overwrite

@section('css')
<style>
    .assessment-page { background:#f8f9fa; min-height:100vh; }
    .assessment-toolbar { z-index:1020; }
    .exam-question { font-size:1.08rem; line-height:1.55; }
    .option-card { cursor:pointer; background:#fff; min-height:56px; font-size:1rem; line-height:1.45; }
    .option-card:has(input:checked) { border-color:#007bff !important; background:#f0f7ff; }
    .answer-radio { transform:scale(1.25); }
    @media (max-width:576px) {
        .container { padding-left:12px; padding-right:12px; }
        .btn-lg { padding:.65rem .8rem; font-size:.95rem; }
        .exam-question { font-size:1rem; }
        .option-card { padding:14px !important; }
    }
</style>
@overwrite

@section('js')
<script>
(() => {
    const attemptId = {{ $attempt->id }};
    const storageKey = 'rms_exam_' + attemptId + '_question';
    const answerUrl = @json(route('guest.assessments.attempts.answer', $attempt));
    const submitUrl = @json(route('guest.assessments.attempts.submit', $attempt));
    const eventUrl = @json(route('guest.assessments.attempts.event', $attempt));
    const csrf = @json(csrf_token());

    let countdown = {{ (int)$remainingSeconds }};
    let finishing = false;
    const items = Array.from(document.querySelectorAll('.exam-item'));
    const navButtons = Array.from(document.querySelectorAll('.nav-btn'));
    let currentIndex = Math.min(parseInt(sessionStorage.getItem(storageKey) || '0', 10), Math.max(items.length - 1, 0));

    const countdownEl = document.getElementById('countdown');
    const counterEl = document.getElementById('questionCounter');
    const saveState = document.getElementById('saveState');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');

    function setSaveState(text, css='text-muted') {
        saveState.innerHTML = '<span class="' + css + '">' + text + '</span>';
    }

    function showItem(index) {
        if (!items.length) return;
        currentIndex = Math.max(0, Math.min(index, items.length - 1));
        sessionStorage.setItem(storageKey, currentIndex);
        items.forEach((el, i) => el.style.display = i === currentIndex ? 'block' : 'none');
        counterEl.textContent = 'Question ' + (currentIndex + 1) + ' of ' + items.length;
        prevBtn.disabled = currentIndex === 0;
        nextBtn.disabled = currentIndex === items.length - 1;
        navButtons.forEach((btn, i) => {
            btn.classList.toggle('border-primary', i === currentIndex);
        });
    }

    async function logEvent(eventType) {
        try {
            await fetch(eventUrl, {
                method:'POST',
                headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
                body:JSON.stringify({event_type:eventType}),
                keepalive:true
            });
        } catch (_) {}
    }

    async function finalizeAtTimeout() {
        if (finishing) return;
        finishing = true;
        setSaveState('Time is up. Finalizing your saved answers…', 'text-info font-weight-bold');

        try {
            const response = await fetch(submitUrl, {
                method:'POST',
                headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'},
            });

            if (response.status === 409) {
                finishing = false;
                setTimeout(finalizeAtTimeout, 1200);
                return;
            }

            if (response.redirected) {
                window.location.href = response.url;
                return;
            }

            window.location.reload();
        } catch (_) {
            finishing = false;
            setSaveState('Time is up. Reconnecting to finalize…', 'text-warning font-weight-bold');
            setTimeout(finalizeAtTimeout, 2500);
        }
    }

    function tick() {
        const mins = Math.floor(Math.max(countdown,0) / 60);
        const secs = Math.max(countdown,0) % 60;
        countdownEl.textContent = mins.toString().padStart(2,'0') + ':' + secs.toString().padStart(2,'0');

        if (countdown <= 0) {
            finalizeAtTimeout();
            return;
        }
        countdown--;
        setTimeout(tick, 1000);
    }

    async function saveAnswer(radio) {
        const itemId = radio.dataset.item;
        const optionId = radio.value;
        const navBtn = navButtons[currentIndex];

        setSaveState('Saving…', 'text-warning');

        try {
            const response = await fetch(answerUrl, {
                method:'POST',
                headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
                body:JSON.stringify({written_exam_id:itemId, selected_option_id:optionId})
            });

            if (response.status === 409) {
                await finalizeAtTimeout();
                return;
            }
            if (!response.ok) throw new Error('save failed');

            navBtn.classList.remove('btn-outline-secondary');
            navBtn.classList.add('btn-success');
            setSaveState('✓ Saved', 'text-success font-weight-bold');
        } catch (_) {
            setSaveState('! Not saved. Check your connection and tap the option again.', 'text-danger font-weight-bold');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        showItem(currentIndex);
        tick();

        prevBtn.addEventListener('click', () => showItem(currentIndex - 1));
        nextBtn.addEventListener('click', () => showItem(currentIndex + 1));

        navButtons.forEach(btn => btn.addEventListener('click', () => {
            showItem(parseInt(btn.dataset.index,10));
            $('#questionModal').modal('hide');
        }));

        document.querySelectorAll('.answer-radio').forEach(radio => {
            radio.addEventListener('change', () => saveAnswer(radio));
        });

        document.addEventListener('visibilitychange', () => {
            logEvent(document.hidden ? 'tab_hidden' : 'tab_visible');
        });

        window.addEventListener('offline', () => {
            setSaveState('Offline. Previously confirmed answers remain safe.', 'text-warning font-weight-bold');
            logEvent('connection_lost');
        });

        window.addEventListener('online', () => {
            setSaveState('Connection restored.', 'text-success');
            logEvent('connection_restored');
        });

        if (performance.getEntriesByType && performance.getEntriesByType('navigation')[0]?.type === 'reload') {
            logEvent('page_refreshed');
        }
    });
})();
</script>
@overwrite
