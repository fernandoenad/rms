@extends('layouts.guest')
@section('title', 'Applicant Master Profile')
@section('navTitle', 'Applicant Portal')

@section('css')
<style>
.profile-shell{max-width:1130px;margin:auto}
.profile-hero{background:linear-gradient(120deg,#173b67,#285c88);color:#fff;border-radius:15px;padding:30px 34px;box-shadow:0 10px 22px rgba(22,53,87,.12)}
.profile-hero h1{font-weight:700;font-size:1.65rem;margin:6px 0}
.profile-hero p{color:#e6eff7;max-width:690px;margin-bottom:0}
.profile-hero .eyebrow{letter-spacing:.11em;text-transform:uppercase;font-size:.7rem;font-weight:700;color:#d9e8f6}
.profile-panel{background:white;border:1px solid #e5ebf1;border-radius:12px;box-shadow:0 3px 14px rgba(29,57,87,.045);margin-bottom:20px;overflow:hidden}
.profile-panel .section-heading{display:flex;align-items:center;gap:12px;border-bottom:1px solid #eaf0f5;padding:21px 25px 18px}
.section-icon{width:40px;height:40px;border-radius:10px;background:#edf4fa;color:#255786;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.section-heading h2{font-size:1.03rem;font-weight:700;margin:0}
.section-heading p{margin:2px 0 0;color:#657487;font-size:.86rem}
.profile-panel .section-body{padding:22px 25px 9px}
.profile-form label{font-size:.88rem;font-weight:600;color:#304458;margin-bottom:7px}
.profile-form .form-control{height:43px;border-radius:7px;border-color:#cfd9e4}
.profile-form .form-control:focus{border-color:#487fac;box-shadow:0 0 0 .18rem rgba(53,121,169,.14)}
.profile-form .hint{font-size:.76rem;color:#66788b;margin-top:5px}
.profile-form .field-feedback{font-size:.77rem;display:none}
.profile-form .is-invalid~.field-feedback{display:block}
.profile-note{background:#f1f6fa;border-left:3px solid #427ba7;border-radius:6px;padding:14px 16px;color:#426079;font-size:.88rem}
.profile-actions{background:#fff;border:1px solid #e5ebf1;border-radius:12px;padding:17px 24px;display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;box-shadow:0 4px 15px rgba(29,57,87,.05)}
.profile-save{min-width:168px;font-weight:600}
.profile-tip{font-size:.82rem;color:#657487}
.profile-meta{font-size:.82rem;color:#657487}
.profile-status{border-radius:8px;margin:18px 0;padding:13px 17px}
.profile-progress{height:6px;background:#dbe8f3;border-radius:10px;overflow:hidden;margin-top:12px}
.profile-progress>div{height:100%;width:0;background:#8dd6c4;transition:width .2s}
@media(max-width:576px){.profile-hero{padding:25px 20px}.profile-panel .section-heading{padding:18px}.profile-panel .section-body{padding:18px 18px 4px}.profile-actions{padding:16px}.profile-save{width:100%}}
</style>
@endsection

@section('main')
<div class="container py-4 py-lg-5 profile-shell">
    <div class="profile-hero mb-4">
        <div class="eyebrow"><i class="fas fa-user-circle mr-1"></i> Recruitment Management System</div>
        <h1>Master Applicant Profile</h1>
        <p>Keep your details accurate and ready for your next application. We'll help you fill in the right information before submission.</p>
        <div class="profile-progress" role="progressbar" aria-label="Profile form completion" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="profileProgress"><div id="profileProgressBar"></div></div>
        <div class="mt-2 small" id="profileProgressText" aria-live="polite">Complete the required fields to continue</div>
    </div>

    @if(session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>Please correct the highlighted details.</strong>
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <div id="formNotice" class="alert profile-status d-none" role="status" aria-live="polite"></div>

    <form id="applicantProfileForm" class="profile-form" method="POST" action="{{ route('applicant.profile.update') }}" novalidate>
        @csrf
        @method('PUT')

        <div class="profile-panel">
            <div class="section-heading">
                <div class="section-icon"><i class="fas fa-id-card"></i></div>
                <div><h2>Personal information</h2><p>Use the name that appears on your official records.</p></div>
            </div>
            <div class="section-body">
                <div class="row">
                    @foreach(['first_name' => 'First name and name extension', 'middle_name' => 'Middle name', 'last_name' => 'Last name'] as $field => $label)
                        <div class="col-md-4 form-group">
                            <label for="{{ $field }}">{{ $label }} <span class="text-danger">*</span></label>
                            <input id="{{ $field }}" name="{{ $field }}" type="text" required maxlength="255"
                                class="form-control @error($field) is-invalid @enderror"
                                value="{{ old($field, $profile->$field) }}"
                                placeholder="{{ $field === 'middle_name' ? 'Use - if not applicable' : 'As shown on your official ID' }}">
                            <div class="invalid-feedback field-feedback" data-error-for="{{ $field }}">@error($field){{ $message }}@enderror</div>
                        </div>
                    @endforeach
                    <div class="col-md-4 form-group">
                        <label for="date_of_birth">Date of birth <span class="text-danger">*</span></label>
                        <input id="date_of_birth" name="date_of_birth" type="date" required
                            max="{{ now()->toDateString() }}"
                            class="form-control @error('date_of_birth') is-invalid @enderror"
                            value="{{ old('date_of_birth', $profile->date_of_birth ? $profile->date_of_birth->format('Y-m-d') : '') }}">
                        <div class="invalid-feedback field-feedback" data-error-for="date_of_birth">@error('date_of_birth'){{ $message }}@enderror</div>
                    </div>
                    <div class="col-md-2 form-group">
                        <label for="computedAge">Age (calculated)</label>
                        <input type="text" id="computedAge" class="form-control bg-light" readonly tabindex="-1" placeholder="—" aria-describedby="ageHelp">
                        <div id="ageHelp" class="hint">Updates from your birthdate</div>
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="gender">Gender <span class="text-danger">*</span></label>
                        <select id="gender" name="gender" required class="form-control @error('gender') is-invalid @enderror"
                            data-selected="{{ old('gender', $profile->gender) }}" disabled>
                            <option value="">Loading options…</option>
                        </select>
                        <div class="invalid-feedback field-feedback" data-error-for="gender">@error('gender'){{ $message }}@enderror</div>
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="civil_status">Civil status <span class="text-danger">*</span></label>
                        <select id="civil_status" name="civil_status" required class="form-control @error('civil_status') is-invalid @enderror"
                            data-selected="{{ old('civil_status', $profile->civil_status) }}" disabled>
                            <option value="">Loading options…</option>
                        </select>
                        <div class="invalid-feedback field-feedback" data-error-for="civil_status">@error('civil_status'){{ $message }}@enderror</div>
                    </div>
                    @foreach(['religion' => 'Religion', 'disability' => 'Disability', 'ethnic_group' => 'Ethnic group'] as $field => $label)
                        <div class="col-md-4 form-group">
                            <label for="{{ $field }}">{{ $label }} <span class="text-danger">*</span></label>
                            <input id="{{ $field }}" name="{{ $field }}" type="text" required maxlength="255"
                                class="form-control @error($field) is-invalid @enderror"
                                value="{{ old($field, $profile->$field) }}" placeholder="Enter - if none">
                            <div class="invalid-feedback field-feedback" data-error-for="{{ $field }}">@error($field){{ $message }}@enderror</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="profile-panel">
            <div class="section-heading">
                <div class="section-icon"><i class="fas fa-map-marker-alt"></i></div>
                <div><h2>Residential address</h2><p>Municipalities and districts are loaded from the DepEd Bohol reference database.</p></div>
            </div>
            <div class="section-body">
                <div id="referenceStatus" class="profile-note mb-3" role="status" aria-live="polite"><i class="fas fa-spinner fa-spin mr-2"></i>Loading location and dropdown choices…</div>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label for="municipality">Municipality / City <span class="text-danger">*</span></label>
                        <select id="municipality" name="municipality" required class="form-control @error('municipality') is-invalid @enderror"
                            data-selected="{{ old('municipality', $profile->municipality) }}" disabled>
                            <option value="">Loading municipalities…</option>
                        </select>
                        <div class="invalid-feedback field-feedback" data-error-for="municipality">@error('municipality'){{ $message }}@enderror</div>
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="district_id">Preferred DepEd district <span class="text-muted font-weight-normal">(optional)</span></label>
                        <select id="district_id" name="district_id" class="form-control @error('district_id') is-invalid @enderror"
                            data-selected="{{ old('district_id', $profile->district_id) }}" disabled>
                            <option value="">Loading districts…</option>
                        </select>
                        <div class="hint">For profile reference only; this does not automatically assign an application to a school.</div>
                        <div class="invalid-feedback field-feedback" data-error-for="district_id">@error('district_id'){{ $message }}@enderror</div>
                    </div>
                    <div class="col-md-5 form-group">
                        <label for="barangay">Barangay <span class="text-danger">*</span></label>
                        <input id="barangay" name="barangay" type="text" required maxlength="255"
                            class="form-control @error('barangay') is-invalid @enderror"
                            value="{{ old('barangay', $profile->barangay) }}" placeholder="Enter barangay">
                        <div class="invalid-feedback field-feedback" data-error-for="barangay">@error('barangay'){{ $message }}@enderror</div>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="sitio">Sitio / Purok / Street <span class="text-danger">*</span></label>
                        <input id="sitio" name="sitio" type="text" required maxlength="255"
                            class="form-control @error('sitio') is-invalid @enderror"
                            value="{{ old('sitio', $profile->sitio) }}" placeholder="Street or purok">
                        <div class="invalid-feedback field-feedback" data-error-for="sitio">@error('sitio'){{ $message }}@enderror</div>
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="zip">Postal code <span class="text-danger">*</span></label>
                        <input id="zip" name="zip" type="text" inputmode="numeric" required pattern="[0-9]{4}"
                            class="form-control @error('zip') is-invalid @enderror"
                            value="{{ old('zip', $profile->zip) }}" placeholder="e.g. 6331">
                        <div class="invalid-feedback field-feedback" data-error-for="zip">@error('zip'){{ $message }}@enderror</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="profile-panel">
            <div class="section-heading">
                <div class="section-icon"><i class="fas fa-envelope"></i></div>
                <div><h2>Contact information</h2><p>Use an email address and mobile number you regularly check.</p></div>
            </div>
            <div class="section-body">
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label for="email">Contact email <span class="text-danger">*</span></label>
                        <input id="email" name="email" type="email" required maxlength="255"
                            autocomplete="email" class="form-control @error('email') is-invalid @enderror"
                            value="{{ old('email', $profile->email ?: auth()->user()->email) }}" placeholder="name@example.com">
                        <div class="invalid-feedback field-feedback" data-error-for="email">@error('email'){{ $message }}@enderror</div>
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="phone">Mobile number <span class="text-danger">*</span></label>
                        <input id="phone" name="phone" type="tel" required maxlength="11" inputmode="tel"
                            pattern="09[0-9]{9}" autocomplete="tel"
                            class="form-control @error('phone') is-invalid @enderror"
                            value="{{ old('phone', $profile->phone) }}" placeholder="09XXXXXXXXX">
                        <div class="invalid-feedback field-feedback" data-error-for="phone">@error('phone'){{ $message }}@enderror</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="profile-note mb-3"><i class="fas fa-lock mr-2"></i>
            Updating this profile affects <strong>future applications only</strong>.
            Previously submitted application records and assessment results remain unchanged.
        </div>
        <div class="profile-actions mb-5">
            <div class="profile-tip" id="saveStatus" aria-live="polite"><i class="far fa-check-circle mr-1"></i>Review your details before saving.</div>
            <div class="d-flex align-items-center" style="gap:10px;flex-wrap:wrap">
                <a class="btn btn-outline-secondary" href="{{ route('applicant.dashboard') }}">Cancel</a>
                <button type="submit" id="saveProfile" class="btn btn-primary profile-save" disabled><i class="fas fa-save mr-2"></i>Save profile</button>
            </div>
        </div>
    </form>
</div>
@endsection

@section('js')
<script>
(function () {
    'use strict';
    var form = document.getElementById('applicantProfileForm');
    var notice = document.getElementById('formNotice');
    var save = document.getElementById('saveProfile');
    var status = document.getElementById('saveStatus');
    var ready = false;
    var fields = ['first_name', 'middle_name', 'last_name', 'date_of_birth', 'gender', 'civil_status',
        'religion', 'disability', 'ethnic_group', 'municipality', 'barangay', 'sitio', 'zip', 'email', 'phone'];

    function report(message, type) {
        notice.textContent = message;
        notice.className = 'alert profile-status alert-' + type;
        notice.scrollIntoView({behavior:'smooth',block:'nearest'});
    }
    function option(select, value, label) {
        var node = document.createElement('option');
        node.value = String(value); node.textContent = label;
        select.appendChild(node);
    }
    function fillSelect(id, items, placeholder, district) {
        var el = document.getElementById(id);
        var selected = el.dataset.selected || '';
        el.replaceChildren();
        option(el, '', placeholder);
        items.forEach(function (item) {
            option(el, district ? item.id : item, district ? item.name : item);
        });
        if (selected && Array.from(el.options).some(function (o) { return o.value === selected; })) {
            el.value = selected;
        } else if (selected) {
            // Do not silently substitute a new value when a reference is retired.
            option(el, selected, 'Previously saved: ' + selected + ' (please reselect)');
            el.value = selected;
            el.options[el.selectedIndex].disabled = true;
        }
        el.disabled = false;
    }
    function ageFromBirthdate(value) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return null;
        var p = value.split('-').map(Number);
        var birth = new Date(p[0], p[1] - 1, p[2]);
        if (birth.getFullYear() !== p[0] || birth.getMonth() !== p[1] - 1 || birth.getDate() !== p[2]) return null;
        var today = new Date();
        var age = today.getFullYear() - p[0];
        if (today.getMonth() + 1 < p[1] || (today.getMonth() + 1 === p[1] && today.getDate() < p[2])) age--;
        return age >= 0 ? age : null;
    }
    function updateAge() {
        var age = ageFromBirthdate(document.getElementById('date_of_birth').value);
        document.getElementById('computedAge').value = age === null ? '' : String(age);
    }
    function progress() {
        var done = fields.filter(function (name) {
            var el = form.elements.namedItem(name);
            return el && String(el.value).trim() !== '';
        }).length;
        var pct = Math.round(done * 100 / fields.length);
        document.getElementById('profileProgressBar').style.width = pct + '%';
        document.getElementById('profileProgress').setAttribute('aria-valuenow', String(pct));
        document.getElementById('profileProgressText').textContent = pct + '% of required details provided';
    }
    function resetErrors() {
        form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
        form.querySelectorAll('[data-error-for]').forEach(function (el) { el.textContent = ''; });
    }
    function fieldErrors(errors) {
        var first = null;
        Object.keys(errors || {}).forEach(function (name) {
            var el = form.elements.namedItem(name);
            var msg = form.querySelector('[data-error-for="' + name + '"]');
            if (el) {
                el.classList.add('is-invalid');
                if (!first) first = el;
            }
            if (msg) msg.textContent = errors[name][0] || 'Please check this field.';
        });
        if (first) first.focus();
    }

    fetch(@json(route('applicant.profile.options')), {
        headers: {'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin'
    })
    .then(function (response) { if (!response.ok) throw new Error('Unable to load form choices.'); return response.json(); })
    .then(function (data) {
        fillSelect('municipality', data.municipalities || [], 'Select municipality / city');
        fillSelect('district_id', data.districts || [], 'No district selected', true);
        fillSelect('gender', data.genders || [], 'Select gender');
        fillSelect('civil_status', data.civil_statuses || [], 'Select civil status');
        ready = true;
        save.disabled = false;
        document.getElementById('referenceStatus').innerHTML =
            '<i class="fas fa-check-circle mr-2"></i>Official reference choices loaded. Select your municipality, gender and civil status.';
        progress();
    })
    .catch(function () {
        var box = document.getElementById('referenceStatus');
        box.className = 'alert alert-warning mb-3';
        box.textContent = 'Reference dropdowns are temporarily unavailable. Refresh to retry. You cannot save while choices are unavailable.';
    });

    form.addEventListener('input', function (event) {
        if (event.target.id === 'date_of_birth') updateAge();
        progress();
    });
    form.addEventListener('change', progress);
    updateAge(); progress();

    form.addEventListener('submit', function (event) {
        if (!window.fetch || !window.FormData) return; // normal server submission as a fallback
        event.preventDefault();
        if (!ready) { report('Please wait for the official dropdown lists to load.', 'warning'); return; }
        resetErrors();
        if (!form.checkValidity()) {
            form.reportValidity();
            report('Please complete all required fields before saving.', 'warning');
            return;
        }
        var age = ageFromBirthdate(document.getElementById('date_of_birth').value);
        if (age === null || age < 18 || age > 70) {
            fieldErrors({date_of_birth:['Applicants must be between 18 and 70 years old.']});
            report('Please check your date of birth.', 'warning');
            return;
        }
        save.disabled = true;
        status.textContent = 'Saving your profile…';
        fetch(form.action, {
            method:'POST', body:new FormData(form), credentials:'same-origin',
            headers:{'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest'}
        })
        .then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok) {
                    var error = new Error(payload.message || 'Unable to save profile.');
                    error.status = response.status;
                    error.fields = payload.errors || {};
                    throw error;
                }
                return payload;
            });
        })
        .then(function (payload) {
            report(payload.message || 'Profile saved.', 'success');
            status.textContent = 'Saved successfully.';
            if (payload.redirect) window.location.assign(payload.redirect);
        })
        .catch(function (error) {
            if (error.fields) fieldErrors(error.fields);
            report(error.status === 419 ? 'Your session expired. Reload the page and try again.' :
                (error.message || 'Unable to save. Please try again.'), 'danger');
            status.textContent = 'Not saved. Review the errors and retry.';
        })
        .finally(function () { save.disabled = !ready; });
    });
})();
</script>
@endsection
