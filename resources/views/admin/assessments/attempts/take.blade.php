@extends('adminlte::page')

@php
    $title = "Take Assessment";
    $app_name = config('app.name', '') . ' [Admin]';
@endphp

@section('title', config('app.name', '') . ' | ' . $title)

@section('content_header')
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1 class="m-0">{{ $exam->title }}</h1>
            <p class="mb-0 text-muted">Duration: {{ $exam->duration }} min | Enrollment key: {{ $exam->enrollment_key }}</p>
        </div>
        <div class="col-sm-6 text-right">
            <span class="badge badge-info" id="countdown"></span>
        </div>
    </div>
@stop

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Please answer all items</h3>
                    </div>
                    <div class="card-body">
                        @foreach($items as $index => $item)
                            @php
                                $answer = $attempt->answers->firstWhere('written_exam_id', $item->id);
                            @endphp
                            <div class="mb-4">
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
                    <div class="card-footer">
                        <form method="post" action="{{ route('admin.assessments.attempts.submit', $attempt) }}">
                            @csrf
                            <input type="hidden" name="auto_submit_reason" id="manual_reason" value="">
                            <button type="submit" class="btn btn-primary" onclick="return confirm('Submit your answers now?');">Submit answers</button>
                            <a href="{{ route('admin.assessments.index') }}" class="btn btn-default float-right">Back</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('footer')
    @include('layouts.footer')
@stop

@section('css')
@stop

@section('plugins.Datatables', false)

@section('js')
<script>
    const remainingSeconds = {{ $remainingSeconds }};
    let countdown = remainingSeconds;
    const countdownEl = document.getElementById('countdown');
    let submitted = false;

    function submitAssessment(reason = '') {
        if (!submitted) {
            submitted = true;
            if (reason) {
                document.getElementById('auto_reason').value = reason;
            }
            document.getElementById('autoSubmitForm').submit();
        }
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

        document.querySelectorAll('.answer-radio').forEach((radio) => {
            radio.addEventListener('change', (e) => {
                const itemId = e.target.dataset.item;
                const selected = e.target.value;
                fetch("{{ route('admin.assessments.attempts.answer', $attempt) }}", {
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
            });
        });

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                submitAssessment('visibilitychange');
            }
        });
    });
</script>
<form id="autoSubmitForm" method="post" action="{{ route('admin.assessments.attempts.submit', $attempt) }}">
    @csrf
    <input type="hidden" name="auto_submit_reason" id="auto_reason" value="">
</form>
@stop
