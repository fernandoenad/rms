@extends('layouts.guest')

@section('title')
    {{ config('app.name', '') }} | Take Assessment
@overwrite

@section('main')
<section class="content assessment-page {{ !empty($largeText) ? 'assessment-large-text' : '' }}">
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
                    <div class="mb-3 exam-question d-flex align-items-start">
    <strong class="mr-1">{{ $index + 1 }}.</strong>
    <div class="exam-question-text">{{ $item->question }}</div>
</div>

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

        <button type="button" class="btn btn-success btn-lg btn-block mb-3" id="submitReviewBtn" data-toggle="modal" data-target="#submitReviewModal">
            <i class="fas fa-check-circle mr-1"></i> Submit Assessment
        </button>

        <div class="text-center text-muted small mb-5">
            You may submit early when you are finished. Your saved responses are also finalized automatically when time expires.
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

<div class="modal fade" id="submitReviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Review Before Submission</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="row text-center mb-3">
                    <div class="col-6">
                        <div class="border rounded p-3">
                            <div class="h4 mb-0 text-success" id="answeredCount">0</div>
                            <small class="text-muted">Answered</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-3">
                            <div class="h4 mb-0 text-danger" id="unansweredCount">0</div>
                            <small class="text-muted">Unanswered</small>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <strong>Answered items</strong>
                    <div class="d-flex flex-wrap mt-2" id="answeredItems"></div>
                </div>

                <div class="mb-3">
                    <strong>Unanswered items</strong>
                    <div class="d-flex flex-wrap mt-2" id="unansweredItems"></div>
                </div>

                <div class="alert alert-warning mb-0" id="unansweredWarning" style="display:none;">
                    You still have unanswered items. You may return to the exam, or continue if you intentionally want to submit them unanswered.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Return to Exam</button>
                <button type="button" class="btn btn-danger" id="continueFinalConfirmBtn">
                    Continue to Final Confirmation
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="finalConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Final Confirmation</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="mb-2"><strong>This action is final.</strong></p>
                <p class="mb-0">
                    Once submitted, you cannot reopen or change your answers. Are you sure you want to submit this assessment now?
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">No, Go Back</button>
                <button type="button" class="btn btn-danger" id="finalSubmitBtn">Yes, Submit Now</button>
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
    .exam-question-text { white-space:pre-line; }
    .option-card { cursor:pointer; background:#fff; min-height:56px; font-size:1rem; line-height:1.45; }
    .option-card.option-selected { border-color:#007bff !important; background:#f0f7ff; }
    .answer-radio { transform:scale(1.25); }
    .assessment-large-text .exam-question { font-size:1.35rem; }
    .assessment-large-text .option-card { font-size:1.2rem; }
    .assessment-large-text .btn { font-size:1.05rem; }
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
    const pendingStorageKey = 'rms_exam_' + attemptId + '_pending_answers';
    const answerUrl = @json(route('guest.assessments.attempts.answer', $attempt));
    const submitUrl = @json(route('guest.assessments.attempts.submit', $attempt));
    const reviewUrl = @json(route('guest.assessments.attempts.review', $attempt));
    const timeStatusUrl = @json(route('guest.assessments.attempts.time_status', $attempt));
    const eventUrl = @json(route('guest.assessments.attempts.event', $attempt));
    const csrf = @json(csrf_token());

    let authoritativeRemainingSeconds = {{ (int)$remainingSeconds }};
    let authoritativeSyncMark = performance.now();
    let timeSyncInFlight = false;
    let finishing = false;
    const items = Array.from(document.querySelectorAll('.exam-item'));
    const navButtons = Array.from(document.querySelectorAll('.nav-btn'));

    function storageGet(storage, key, fallback) {
        try {
            const value = storage.getItem(key);
            return value === null ? fallback : value;
        } catch (e) {
            return fallback;
        }
    }

    function storageSet(storage, key, value) {
        try { storage.setItem(key, value); } catch (e) {}
    }

    function storageRemove(storage, key) {
        try { storage.removeItem(key); } catch (e) {}
    }

    let currentIndex = Math.min(parseInt(storageGet(sessionStorage, storageKey, '0') || '0', 10), Math.max(items.length - 1, 0));

    const countdownEl = document.getElementById('countdown');
    const counterEl = document.getElementById('questionCounter');
    const saveState = document.getElementById('saveState');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitReviewBtn = document.getElementById('submitReviewBtn');
    const continueFinalConfirmBtn = document.getElementById('continueFinalConfirmBtn');
    const finalSubmitBtn = document.getElementById('finalSubmitBtn');
    let activeSaves = 0;
    const saveQueues = new Map();

    function setSaveState(text, css='text-muted') {
        saveState.innerHTML = '<span class="' + css + '">' + text + '</span>';
    }

    function updateSubmitAvailability() {
        submitReviewBtn.disabled = activeSaves > 0;
    }

    function loadPendingAnswers() {
        try { return JSON.parse(storageGet(localStorage, pendingStorageKey, '{}') || '{}') || {}; }
        catch (_) { return {}; }
    }

    function savePendingAnswer(itemId, optionId) {
        const pending = loadPendingAnswers();
        pending[String(itemId)] = String(optionId);
        storageSet(localStorage, pendingStorageKey, JSON.stringify(pending));
    }

    function clearPendingAnswer(itemId, optionId) {
        const pending = loadPendingAnswers();
        if (String(pending[String(itemId)] || '') === String(optionId)) {
            delete pending[String(itemId)];
            storageSet(localStorage, pendingStorageKey, JSON.stringify(pending));
        }
    }

    function retryPendingAnswers() {
        if (finishing) return;

        const pending = loadPendingAnswers();
        let delay = 0;

        Object.entries(pending).forEach(([itemId, optionId]) => {
            const radio = document.querySelector(
                '.answer-radio[data-item="' + itemId + '"][value="' + optionId + '"]'
            );

            if (!radio) {
                clearPendingAnswer(itemId, optionId);
                return;
            }

            radio.checked = true;
            if (radio.parentElement) {
                const groupName = radio.name;
                document.querySelectorAll('input[name="' + groupName + '"]').forEach(input => {
                    if (input.parentElement) input.parentElement.classList.remove('option-selected');
                });
                radio.parentElement.classList.add('option-selected');
            }

            // Stagger retries slightly so a reconnect with many unsaved answers
            // does not create a sudden request burst.
            setTimeout(() => {
                if (!finishing) {
                    queueAnswerSave(radio);
                }
            }, delay);

            delay += 120;
        });
    }

    function showItem(index) {
        if (!items.length) return;
        currentIndex = Math.max(0, Math.min(index, items.length - 1));
        storageSet(sessionStorage, storageKey, currentIndex);
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
                body:JSON.stringify({event_type:eventType})
            });
        } catch (_) {}
    }

    function currentRemainingSeconds() {
        const elapsed = Math.max(0, (performance.now() - authoritativeSyncMark) / 1000);
        return Math.max(0, Math.ceil(authoritativeRemainingSeconds - elapsed));
    }

    async function syncAuthoritativeTime(options={}) {
        if (timeSyncInFlight) return null;
        timeSyncInFlight = true;

        try {
            const response = await fetch(timeStatusUrl, {
                headers:{'Accept':'application/json','Cache-Control':'no-cache'},
                cache:'no-store'
            });

            const data = await response.json().catch(() => ({}));

            if (response.status === 409 || data.expired || data.submitted) {
                authoritativeRemainingSeconds = 0;
                authoritativeSyncMark = performance.now();

                if (options.reloadOnExpired !== false) {
                    window.location.reload();
                }
                return data;
            }

            if (response.ok && Number.isFinite(Number(data.remaining_seconds))) {
                authoritativeRemainingSeconds = Math.max(0, Number(data.remaining_seconds));
                authoritativeSyncMark = performance.now();
                return data;
            }

            throw new Error('time sync failed');
        } catch (_) {
            if (options.showError) {
                setSaveState(
                    'Unable to verify the timer with the server. Reconnecting…',
                    'text-warning font-weight-bold'
                );
            }
            return null;
        } finally {
            timeSyncInFlight = false;
        }
    }

    async function finalizeAtTimeout() {
        if (finishing) return;
        finishing = true;
        setSaveState('Checking the official assessment time…', 'text-info font-weight-bold');

        const status = await syncAuthoritativeTime({
            reloadOnExpired:true,
            showError:true
        });

        if (status && !status.expired && !status.submitted && Number(status.remaining_seconds) > 0) {
            finishing = false;
            setSaveState('Timer synchronized with the server.', 'text-success');
            setTimeout(tick, 250);
            return;
        }

        if (!status) {
            finishing = false;
            setTimeout(finalizeAtTimeout, 2500);
        }
    }

    function twoDigits(value) {
        return value < 10 ? '0' + value : String(value);
    }

    function tick() {
        const remaining = currentRemainingSeconds();
        const mins = Math.floor(remaining / 60);
        const secs = remaining % 60;
        countdownEl.textContent = twoDigits(mins) + ':' + twoDigits(secs);

        if (remaining <= 0) {
            finalizeAtTimeout();
            return;
        }
        setTimeout(tick, 1000);
    }

    function queueAnswerSave(radio) {
        const itemId = String(radio.dataset.item);
        const optionId = String(radio.value);
        const itemIndex = items.findIndex(function (item) {
            const input = item.querySelector('.answer-radio');
            return input && input.dataset.item === itemId;
        });
        const navBtn = itemIndex >= 0 ? navButtons[itemIndex] : null;

        let state = saveQueues.get(itemId);
        if (!state) {
            state = {
                running: false,
                pending: null,
                sequence: 0,
            };
            saveQueues.set(itemId, state);
        }

        savePendingAnswer(itemId, optionId);

        state.sequence++;
        state.pending = {
            itemId,
            optionId,
            navBtn,
            sequence: state.sequence,
        };

        setSaveState('Saving…', 'text-warning');

        if (!state.running) {
            processAnswerQueue(itemId);
        }
    }

    async function processAnswerQueue(itemId) {
        const state = saveQueues.get(itemId);
        if (!state || state.running) return;

        state.running = true;
        activeSaves++;
        updateSubmitAvailability();

        try {
            while (state.pending && !finishing) {
                const current = state.pending;
                state.pending = null;

                try {
                    const response = await fetch(answerUrl, {
                        method:'POST',
                        headers:{
                            'Content-Type':'application/json',
                            'X-CSRF-TOKEN':csrf,
                            'Accept':'application/json'
                        },
                        body:JSON.stringify({
                            written_exam_id:current.itemId,
                            selected_option_id:current.optionId
                        })
                    });

                    if (response.status === 409) {
                        state.pending = null;
                        await finalizeAtTimeout();
                        return;
                    }

                    if (!response.ok) {
                        throw new Error('save failed');
                    }

                    // Only mark the UI saved when this is still the latest
                    // selection for the question. If the learner changed the
                    // answer while this request was running, the newer value
                    // remains queued and is sent next.
                    if (!state.pending && current.sequence === state.sequence) {
                        if (current.navBtn) {
                            current.navBtn.classList.remove('btn-outline-secondary');
                            current.navBtn.classList.add('btn-success');
                        }
                        clearPendingAnswer(current.itemId, current.optionId);
                        setSaveState('✓ Saved', 'text-success font-weight-bold');
                    }
                } catch (_) {
                    // If a newer selection is waiting, continue and try to save
                    // that latest choice. Otherwise tell the learner this item
                    // still needs another tap after connectivity is restored.
                    if (!state.pending) {
                        setSaveState(
                            '! Not saved. Check your connection and tap the option again.',
                            'text-danger font-weight-bold'
                        );
                    }
                }
            }
        } finally {
            state.running = false;
            activeSaves = Math.max(0, activeSaves - 1);
            updateSubmitAvailability();

            // A selection may have arrived between the final loop check and
            // clearing the running flag.
            if (state.pending && !finishing) {
                processAnswerQueue(itemId);
            }
        }
    }

    async function buildSubmissionReview() {
        continueFinalConfirmBtn.disabled = true;
        document.getElementById('answeredCount').textContent = '…';
        document.getElementById('unansweredCount').textContent = '…';

        try {
            const response = await fetch(reviewUrl, {
                headers:{'Accept':'application/json'}
            });

            if (response.status === 409) {
                await finalizeAtTimeout();
                return;
            }
            if (!response.ok) throw new Error('review failed');

            const data = await response.json();
            const savedIds = new Set((data.answered_item_ids || []).map(String));
            const answered = [];
            const unanswered = [];

            items.forEach((item, index) => {
                const input = item.querySelector('.answer-radio');
                const itemId = String(input ? input.dataset.item : '');
                (savedIds.has(itemId) ? answered : unanswered).push(index + 1);
            });

            document.getElementById('answeredCount').textContent = data.answered;
            document.getElementById('unansweredCount').textContent = data.unanswered;

            document.getElementById('answeredItems').innerHTML = answered.length
                ? answered.map(n => '<span class="badge badge-success p-2 m-1">Q' + n + '</span>').join('')
                : '<span class="text-muted small">None</span>';

            document.getElementById('unansweredItems').innerHTML = unanswered.length
                ? unanswered.map(n => '<span class="badge badge-danger p-2 m-1">Q' + n + '</span>').join('')
                : '<span class="text-success small">All items are saved.</span>';

            document.getElementById('unansweredWarning').style.display = unanswered.length ? 'block' : 'none';
            continueFinalConfirmBtn.disabled = activeSaves > 0;
        } catch (_) {
            document.getElementById('answeredItems').innerHTML =
                '<span class="text-danger small">Could not verify saved responses with the server. Return to the exam and try again.</span>';
            continueFinalConfirmBtn.disabled = true;
        }
    }

    async function submitManually() {
        if (finishing) return;
        finishing = true;
        finalSubmitBtn.disabled = true;
        finalSubmitBtn.textContent = 'Submitting…';

        try {
            const response = await fetch(submitUrl, {
                method:'POST',
                headers:{
                    'Content-Type':'application/json',
                    'X-CSRF-TOKEN':csrf,
                    'Accept':'application/json'
                },
                body:JSON.stringify({confirmed:true})
            });

            if (!response.ok && !response.redirected) {
                const data = await response.json().catch(() => ({}));
                throw new Error(data.message || 'Unable to submit assessment.');
            }

            if (response.redirected) {
                window.location.href = response.url;
                return;
            }

            window.location.reload();
        } catch (error) {
            finishing = false;
            finalSubmitBtn.disabled = false;
            finalSubmitBtn.textContent = 'Yes, Submit Now';
            setSaveState(error.message || 'Submission failed. Please try again.', 'text-danger font-weight-bold');
            $('#finalConfirmModal').modal('hide');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        showItem(currentIndex);
        tick();

        // Synchronize immediately, then periodically. This keeps the visible
        // countdown aligned with the server-side expires_at used by the
        // scheduled finalizer, including after iOS Chrome background/resume.
        syncAuthoritativeTime({reloadOnExpired:true});
        setInterval(() => {
            if (!document.hidden && !finishing) {
                syncAuthoritativeTime({reloadOnExpired:true});
            }
        }, 15000);

        prevBtn.addEventListener('click', () => showItem(currentIndex - 1));
        nextBtn.addEventListener('click', () => showItem(currentIndex + 1));

        navButtons.forEach(btn => btn.addEventListener('click', () => {
            showItem(parseInt(btn.dataset.index,10));
            $('#questionModal').modal('hide');
        }));

        $('#submitReviewModal').on('show.bs.modal', () => {
            buildSubmissionReview();
        });

        continueFinalConfirmBtn.addEventListener('click', () => {
            if (activeSaves > 0) {
                setSaveState('Please wait for your latest answer to finish saving.', 'text-warning font-weight-bold');
                $('#submitReviewModal').modal('hide');
                return;
            }

            $('#submitReviewModal').modal('hide');
            setTimeout(() => $('#finalConfirmModal').modal('show'), 200);
        });

        finalSubmitBtn.addEventListener('click', submitManually);

        document.querySelectorAll('.answer-radio').forEach(radio => {
            if (radio.checked && radio.parentElement) {
                radio.parentElement.classList.add('option-selected');
            }

            radio.addEventListener('change', () => {
                const groupName = radio.name;
                document.querySelectorAll('input[name="' + groupName + '"]').forEach(input => {
                    if (input.parentElement) input.parentElement.classList.remove('option-selected');
                });
                if (radio.parentElement) radio.parentElement.classList.add('option-selected');
                queueAnswerSave(radio);
            });
        });

        retryPendingAnswers();

        document.addEventListener('visibilitychange', () => {
            logEvent(document.hidden ? 'tab_hidden' : 'tab_visible');
            if (!document.hidden) {
                syncAuthoritativeTime({reloadOnExpired:true});
            }
        });

        window.addEventListener('offline', () => {
            setSaveState('Offline. Previously confirmed answers remain safe.', 'text-warning font-weight-bold');
            logEvent('connection_lost');
        });

        window.addEventListener('online', () => {
            setSaveState('Connection restored. Retrying unsaved answers…', 'text-success');
            logEvent('connection_restored');
            syncAuthoritativeTime({reloadOnExpired:true});
            retryPendingAnswers();
        });

        if (performance.getEntriesByType) {
            const navigationEntries = performance.getEntriesByType('navigation');
            if (navigationEntries && navigationEntries[0] && navigationEntries[0].type === 'reload') {
                logEvent('page_refreshed');
            }
        }
    });
})();
</script>
@overwrite
