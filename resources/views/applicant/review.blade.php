@extends('layouts.guest')
@section('title', 'Review Application')
@section('navTitle', 'Applicant Portal')
@section('css')
<style>
.app-review{max-width:1040px;margin:auto}
.review-hero{background:linear-gradient(115deg,#193c64,#29638b);border-radius:14px;color:#fff;padding:29px 31px;box-shadow:0 10px 22px rgba(28,63,95,.11)}
.review-hero .eyebrow{color:#d4e5f3;font-size:.73rem;letter-spacing:.13em;text-transform:uppercase;font-weight:700}
.review-hero h1{font-size:1.55rem;font-weight:700;margin:7px 0}
.review-hero p{color:#e4eef5;margin:0}
.review-panel{background:#fff;border:1px solid #e1e9f1;border-radius:12px;overflow:hidden;margin-bottom:20px;box-shadow:0 4px 17px rgba(34,60,87,.045)}
.review-header{border-bottom:1px solid #e8eef3;padding:18px 23px;display:flex;align-items:center;gap:11px}
.review-header .icon{background:#e9f3fa;color:#285a85;border-radius:9px;width:38px;height:38px;display:grid;place-items:center}
.review-header h2{font-size:1.04rem;font-weight:700;margin:0}
.review-body{padding:18px 23px}
.review-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 22px}
.review-value{overflow-wrap:anywhere}
.review-field{min-width:0}
.review-label{display:block;font-size:.77rem;color:#6c7c8a;margin-bottom:4px}
.review-value{font-size:.95rem;font-weight:600;color:#2d465b}
.review-callout{border-left:3px solid #2c729c;border-radius:6px;background:#f0f6fa;color:#446179;padding:15px 17px;font-size:.87rem}
.review-actions{background:#fff;border-radius:12px;border:1px solid #e0e9f0;padding:20px 23px;margin-bottom:40px}
.review-confirm{display:flex;align-items:flex-start;gap:11px;cursor:pointer;color:#314b63}
.review-confirm input{margin-top:5px;flex-shrink:0;min-width:17px;min-height:17px}
.review-buttons{display:flex;gap:11px;flex-wrap:wrap;margin-top:17px;align-items:center}
.review-buttons button{min-width:185px}
@media(max-width:600px){.review-hero{padding:23px 19px}.review-header,.review-body,.review-actions{padding:17px}.review-grid{grid-template-columns:1fr}.review-buttons button{width:100%}}
</style>
@endsection

@section('main')
<div class="container py-4 py-lg-5 app-review">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3" style="gap:10px">
        <a class="btn btn-outline-secondary font-weight-bold" href="{{ route('guest.vacancies.show', $vacancy) }}"><i class="fas fa-arrow-left mr-2" aria-hidden="true"></i>Back to Vacancy</a>
        <a class="btn btn-outline-primary" href="{{ route('applicant.dashboard') }}">Dashboard</a>
    </div>
    <div class="review-hero mb-4">
        <div class="eyebrow">Final review • Recruitment application</div>
        <h1>Review Application</h1>
        <p>{{ $vacancy->position_title }} — Verify your saved profile before submitting your application.</p>
    </div>

    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <div id="submitNotice" class="alert d-none" role="status" aria-live="polite"></div>

    <section class="review-panel" aria-labelledby="personalReviewTitle">
        <div class="review-header"><div class="icon"><i class="far fa-address-card"></i></div><h2 id="personalReviewTitle">Personal information</h2></div>
        <div class="review-body">
            <div class="review-grid">
                @foreach([
                    'first_name' => 'First name', 'middle_name' => 'Middle name', 'last_name' => 'Last name',
                    'gender' => 'Gender', 'civil_status' => 'Civil status', 'religion' => 'Religion',
                    'disability' => 'Disability', 'ethnic_group' => 'Ethnic group',
                ] as $field => $label)
                    <div class="review-field"><span class="review-label">{{ $label }}</span><div class="review-value">{{ $profile->$field ?: 'Not provided' }}</div></div>
                @endforeach
                <div class="review-field"><span class="review-label">Age at application</span>
                    <div class="review-value">{{ $profile->date_of_birth ? $profile->date_of_birth->age : $profile->age }}</div>
                </div>
            </div>
        </div>
    </section>

    <section class="review-panel" aria-labelledby="addressReviewTitle">
        <div class="review-header"><div class="icon"><i class="fas fa-map-marker-alt"></i></div><h2 id="addressReviewTitle">Residential address</h2></div>
        <div class="review-body">
            <div class="review-grid">
                @foreach(['sitio' => 'Sitio / Purok / Street', 'barangay' => 'Barangay', 'municipality' => 'Municipality', 'zip' => 'Postal code'] as $field => $label)
                    <div class="review-field"><span class="review-label">{{ $label }}</span><div class="review-value">{{ $profile->$field ?: 'Not provided' }}</div></div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="review-panel" aria-labelledby="contactReviewTitle">
        <div class="review-header"><div class="icon"><i class="far fa-envelope"></i></div><h2 id="contactReviewTitle">Contact information</h2></div>
        <div class="review-body">
            <div class="review-grid">
                <div class="review-field"><span class="review-label">Contact email</span><div class="review-value">{{ $profile->email }}</div></div>
                <div class="review-field"><span class="review-label">Mobile number</span><div class="review-value">{{ $profile->phone }}</div></div>
            </div>
            <div class="mt-3"><a class="btn btn-outline-secondary btn-sm" href="{{ route('applicant.profile.edit') }}"><i class="fas fa-user-edit mr-1"></i>Edit Master Profile</a></div>
        </div>
    </section>

    <div class="review-callout mb-3"><i class="fas fa-info-circle mr-2"></i>
        Once submitted, the application stores a snapshot of your details. Later profile edits will not change that submitted record.
    </div>
    <div class="review-actions">
        <form id="submitApplicationForm" method="POST" action="{{ route('applicant.apply.store', $vacancy) }}">
            @csrf
            <label class="review-confirm" for="confirmApplication">
                <input id="confirmApplication" type="checkbox" name="confirm" value="1" required>
                <span>I confirm the current information and wish to submit this application.</span>
            </label>
            <div class="review-buttons">
                <button type="submit" id="submitApplication" class="btn btn-primary" disabled>
                    <i class="fas fa-paper-plane mr-1"></i>Submit Application
                </button>
                <a class="btn btn-outline-secondary" href="{{ route('applicant.dashboard') }}">Back to Dashboard</a>
            </div>
        </form>
        <div class="d-none mt-2" id="submittedResult" role="status" aria-live="polite">
            <p class="mb-2 font-weight-bold">Application submitted successfully.</p>
            <p class="mb-2">Application code: <strong id="submittedCode"></strong></p>
            <a id="submittedLink" class="btn btn-primary" href="{{ route('applicant.dashboard') }}">View application details</a>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
(function() {
    'use strict';
    var form = document.getElementById('submitApplicationForm');
    var confirm = document.getElementById('confirmApplication');
    var button = document.getElementById('submitApplication');
    var notice = document.getElementById('submitNotice');
    var submitted = false;

    confirm.addEventListener('change', function() {
        button.disabled = submitted || !confirm.checked;
    });
    form.addEventListener('submit', function(event) {
        if (!window.fetch || !window.FormData) return;
        event.preventDefault();
        if (submitted || !confirm.checked) return;
        button.disabled = true;
        button.textContent = 'Submitting your application…';
        fetch(form.action, {
            method: 'POST', body: new FormData(form), credentials: 'same-origin',
            headers: {'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}
        }).then(function(response) {
            return response.json().then(function(data) {
                if (!response.ok) {
                    var error = new Error(data.message || 'Application could not be submitted.');
                    error.status = response.status;
                    error.fields = data.errors || {};
                    throw error;
                }
                return data;
            });
        }).then(function(data) {
            submitted = true;
            form.classList.add('d-none');
            document.getElementById('submittedCode').textContent = data.application_code || '';
            if (data.redirect) document.getElementById('submittedLink').href = data.redirect;
            document.getElementById('submittedResult').classList.remove('d-none');
            notice.textContent = data.message || 'Application submitted successfully.';
            notice.className = 'alert alert-success';
        }).catch(function(error) {
            var message = (error.fields && (error.fields.application || error.fields.profile || error.fields.confirm));
            notice.textContent = message && message[0] ? message[0] :
                error.status === 419 ? 'Session expired. Refresh and try again.' :
                error.status === 403 ? 'This vacancy is no longer accepting applications.' :
                (error.message || 'Unable to submit. Please try again.');
            notice.className = 'alert alert-danger';
            button.disabled = !confirm.checked;
            button.innerHTML = '<i class="fas fa-paper-plane mr-1"></i>Submit Application';
        });
    });
})();
</script>
@endsection
