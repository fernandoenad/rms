@extends('layouts.guest')

@section('title')
    {{ config('app.name', '') }} | Take Assessment
@overwrite

@section('main')
    <section class="content-header">
        <div class="container">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">{{ $exam->title }}</h1>
                    <p class="mb-0 text-muted">Duration: {{ $exam->duration }} min | Single attempt</p>
                </div>
                <div class="col-sm-6 text-right">
                    <span class="badge badge-info" id="countdown"></span>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Please answer all items</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-9">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span id="questionCounter"></span>
                                        <div>
                                            <button type="button" class="btn btn-sm btn-secondary" id="prevBtn">Previous</button>
                                            <button type="button" class="btn btn-sm btn-secondary" id="nextBtn">Next</button>
                                        </div>
                                    </div>
                                    @foreach($items as $index => $item)
                                        @php
                                            $answer = $attempt->answers->firstWhere('written_exam_id', $item->id);
                                        @endphp
                                        <div class="mb-4 exam-item" data-index="{{ $index }}" style="display: none;">
                                            <p><strong>Q{{ $index + 1 }}.</strong> {{ $item->question }}</p>
                                            @foreach(['A' => $item->option_a, 'B' => $item->option_b, 'C' => $item->option_c, 'D' => $item->option_d] as $letter => $text)
                                                <div class="form-check">
                                                    <input class="form-check-input answer-radio" type="radio" name="item_{{ $item->id }}" id="item_{{ $item->id }}_{{ $letter }}"
                                                        value="{{ $letter }}" data-item="{{ $item->id }}" {{ ($answer && $answer->selected_option == $letter) ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="item_{{ $item->id }}_{{ $letter }}">{{ $text }}</label>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                                <div class="col-md-3">
                                    <div class="card card-outline card-info position-sticky" style="top: 1rem;">
                                        <div class="card-header p-2">
                                            <h3 class="card-title">Navigate</h3>
                                        </div>
                                        <div class="card-body p-2">
                                            <div class="d-flex flex-wrap" id="questionNav">
                                                @foreach($items as $index => $item)
                                                    <button type="button" class="btn btn-outline-secondary btn-sm nav-btn m-1" style="min-width: 48px;" data-index="{{ $index }}">{{ $index + 1 }}</button>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <form method="post" action="{{ route('guest.assessments.attempts.submit', $attempt) }}">
                                @csrf
                                <input type="hidden" name="auto_submit_reason" id="manual_reason" value="">
                                <button type="submit" class="btn btn-primary" onclick="return confirm('Submit your answers now?');">Submit answers</button>
                                <a href="{{ route('guest.applications.show', $attempt->application_id) }}" class="btn btn-default float-right">Back</a>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <br><br><br><br>
    </section>

    <form id="autoSubmitForm" method="post" action="{{ route('guest.assessments.attempts.submit', $attempt) }}">
        @csrf
        <input type="hidden" name="auto_submit_reason" id="auto_reason" value="">
    </form>
@overwrite

@section('js')
<script>
    const remainingSeconds = {{ $remainingSeconds }};
    let countdown = remainingSeconds;
    const countdownEl = document.getElementById('countdown');
    let submitted = false;
    const items = Array.from(document.querySelectorAll('.exam-item'));
    const totalItems = items.length;
    let currentIndex = 0;
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const questionCounter = document.getElementById('questionCounter');
    const navButtons = Array.from(document.querySelectorAll('.nav-btn'));

    function submitAssessment(reason = '') {
        if (!submitted) {
            submitted = true;
            if (reason) {
                document.getElementById('auto_reason').value = reason;
            }
            document.getElementById('autoSubmitForm').submit();
        }
    }

    function showItem(index) {
        items.forEach((el, idx) => {
            el.style.display = idx === index ? 'block' : 'none';
        });
        prevBtn.disabled = index === 0;
        nextBtn.disabled = index === totalItems - 1;
        questionCounter.textContent = `Question ${index + 1} of ${totalItems}`;
        navButtons.forEach((btn, idx) => {
            const isActive = idx === index;
            btn.classList.toggle('btn-info', isActive);
            btn.classList.toggle('btn-outline-secondary', !isActive && !btn.classList.contains('btn-success'));
            btn.classList.toggle('text-white', isActive);
        });
    }

    function updateNavAnsweredStates() {
        items.forEach((itemEl, idx) => {
            const checked = itemEl.querySelector('.answer-radio:checked');
            const navBtn = navButtons[idx];
            if (!navBtn) return;
            if (checked) {
                navBtn.classList.add('btn-success', 'text-white');
                navBtn.classList.remove('btn-outline-secondary');
            }
        });
    }

    function updateCountdown() {
        const minutes = Math.floor(countdown / 60);
        const seconds = countdown % 60;
        countdownEl.textContent = `Time left: ${minutes.toString().padStart(2,'0')}:${seconds.toString().padStart(2,'0')}`;
        if (countdown <= 0) {
            submitAssessment('timeout');
        } else {
            countdown--;
            setTimeout(updateCountdown, 1000);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        updateCountdown();

        if (items.length > 0) {
            showItem(currentIndex);
            updateNavAnsweredStates();
            prevBtn.addEventListener('click', () => {
                if (currentIndex > 0) {
                    currentIndex--;
                    showItem(currentIndex);
                }
            });
            nextBtn.addEventListener('click', () => {
                if (currentIndex < totalItems - 1) {
                    currentIndex++;
                    showItem(currentIndex);
                }
            });
            navButtons.forEach((btn) => {
                btn.addEventListener('click', () => {
                    const target = parseInt(btn.dataset.index, 10);
                    currentIndex = target;
                    showItem(currentIndex);
                });
            });
        }

        document.querySelectorAll('.answer-radio').forEach((radio) => {
            radio.addEventListener('change', (e) => {
                const itemId = e.target.dataset.item;
                const selected = e.target.value;
                fetch("{{ route('guest.assessments.attempts.answer', $attempt) }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        written_exam_id: itemId,
                        selected_option: selected
                    })
                }).catch((err) => console.error(err));

                // mark nav button as answered
                const navBtn = navButtons.find(btn => parseInt(btn.dataset.index, 10) === currentIndex);
                if (navBtn) {
                    navBtn.classList.add('btn-success', 'text-white');
                    navBtn.classList.remove('btn-outline-secondary');
                }
            });
        });

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                submitAssessment('visibilitychange');
            }
        });
    });
</script>
@overwrite
