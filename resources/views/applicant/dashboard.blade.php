@extends('layouts.guest')
@section('title', 'Applicant Dashboard')
@section('navTitle', 'Applicant Portal')
@section('css')
<style>
.app-home{max-width:1170px;margin:0 auto}
.app-hero{background:linear-gradient(118deg,#193e68,#286991);color:#fff;border-radius:16px;padding:30px 32px;box-shadow:0 10px 24px rgba(22,58,92,.14)}
.app-hero h1{font-size:1.7rem;font-weight:700;margin:4px 0}
.app-hero p{color:#e3eff7;margin-bottom:0}
.app-hero .eyebrow{text-transform:uppercase;letter-spacing:.13em;font-size:.72rem;font-weight:700;color:#c9e1f3}
.app-actions{display:flex;flex-wrap:wrap;gap:9px;margin-top:22px}
.app-hero .btn-outline-light{border-color:#c7dbeb}
.app-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:13px;margin:21px 0}
.app-stat{background:#fff;border:1px solid #e5eaf0;border-radius:12px;padding:17px 19px}
.app-stat strong{display:block;font-size:1.45rem;color:#204d77;font-weight:700}
.app-stat span{font-size:.81rem;color:#65778a}
.app-panel{background:#fff;border:1px solid #e3eaf1;border-radius:13px;overflow:hidden;margin-bottom:20px;box-shadow:0 3px 15px rgba(24,53,83,.04)}
.app-panel-heading{display:flex;gap:12px;align-items:center;padding:19px 22px;border-bottom:1px solid #edf1f4}
.app-panel-heading h2{font-size:1.07rem;font-weight:700;margin:0;color:#283f54}
.app-panel-heading p{margin:4px 0 0;font-size:.82rem;color:#61758a}
.app-panel-content{padding:20px 22px}
.app-panel-icon{height:39px;width:39px;background:#edf4fa;color:#27628c;border-radius:10px;display:grid;place-items:center;flex-shrink:0}
.app-status{border-radius:50px;padding:5px 12px;font-size:.75rem;font-weight:700;display:inline-block;white-space:nowrap}
.app-status-pending,.app-status-clarification{background:#fff3d9;color:#825712}
.app-status-approved{background:#e5f6ed;color:#167243}
.app-status-rejected{background:#fce9e9;color:#982e2e}
.app-status-cancelled{background:#fce9e9;color:#982e2e}
.app-status-profile{background:#e7f1fb;color:#225f8b}
.app-entry{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:15px 0;border-bottom:1px solid #eef1f5}
.app-entry:first-child{padding-top:0}.app-entry:last-child{border-bottom:0;padding-bottom:0}
.app-entry-title{font-weight:700;overflow-wrap:anywhere}
.app-entry p{font-size:.84rem;color:#67798b;margin:5px 0 0}
.app-muted{color:#66798d;font-size:.89rem}
.app-field label{font-weight:600;font-size:.86rem;color:#344d62}
.app-field .form-control{border-radius:7px;min-height:43px;border-color:#cddae5}
.app-claim-note{font-size:.83rem;color:#66788b}
.app-empty{background:#f4f7fa;padding:17px;border-radius:9px;color:#62768a;font-size:.88rem}
.app-login-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
@media(max-width:650px){.app-hero{padding:24px 19px}.app-stats{grid-template-columns:1fr}.app-stat{display:flex;align-items:center;justify-content:space-between}.app-panel-heading,.app-panel-content{padding:16px}.app-entry{flex-direction:column}.app-login-actions{margin-top:12px}}
</style>
@endsection

@section('main')
<div class="container py-4 py-lg-5 app-home">
    <div class="app-hero">
        <div class="eyebrow">DepEd Bohol | Recruitment Management System</div>
        <h1>Applicant Dashboard</h1>
        <p>Welcome{{ $profile->first_name ? ', '.$profile->first_name : '' }}. Manage your profile, recruitment applications and historical ownership requests from one place.</p>
        <div class="app-actions">
            <a class="btn btn-light font-weight-bold" href="{{ route('guest.vacancies.index') }}"><i class="fas fa-search mr-2"></i>Browse vacancies</a>
            <a class="btn btn-outline-light" href="{{ route('applicant.profile.edit') }}"><i class="fas fa-user-edit mr-2"></i>Edit master profile</a>
            @if(auth()->user()->role && auth()->user()->role->status)
                <a class="btn btn-outline-light" href="{{ route('admin.index') }}">Switch to Staff Portal</a>
                @if(!\App\Models\OAuthIdentity::where('user_id', auth()->id())->where('provider', 'google')->exists())
                    <a class="btn btn-outline-light" href="{{ route('applicant.staff.google.link') }}">Link Staff Google Account</a>
                @endif
            @endif
            <form action="{{ route('logout') }}" method="POST" class="d-inline">@csrf
                <button class="btn btn-outline-light" type="submit">Sign out</button>
            </form>
        </div>
    </div>

    @if(session('status'))<div class="alert alert-success mt-3" role="status">{{ session('status') }}</div>@endif

    <div class="app-stats" aria-label="Applicant overview">
        <div class="app-stat"><strong>{{ $links->count() }}</strong><span>My Applications</span></div>
        <div class="app-stat"><strong>{{ $claims->whereIn('status', ['pending', 'clarification'])->count() }}</strong><span>Claims awaiting resolution</span></div>
        <div class="app-stat">
            <strong><i class="fas {{ $profile->profile_completed_at && $profile->date_of_birth ? 'fa-check-circle' : 'fa-exclamation-circle' }}" aria-hidden="true"></i></strong>
            <span>{{ $profile->profile_completed_at && $profile->date_of_birth ? 'Profile saved' : 'Profile needs attention' }}</span>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <section class="app-panel" aria-labelledby="applicationsTitle">
                <div class="app-panel-heading">
                    <div class="app-panel-icon"><i class="fas fa-clipboard-list"></i></div>
                    <div><h2 id="applicationsTitle">My Applications</h2><p>Open an application to see its details and available assessments.</p></div>
                </div>
                <div class="app-panel-content">
                    @forelse($links as $link)
                        <div class="app-entry">
                            <div>
                                <a class="app-entry-title" href="{{ route('guest.applications.show', $link->application) }}">{{ $link->application?->application_code }}</a>
                                <p>{{ $link->application?->vacancy?->position_title ?? 'Application' }}</p>
                            </div>
                            <span class="app-status app-status-profile">{{ str_replace('_', ' ', $link->link_method) }}</span>
                        </div>
                    @empty
                        <div class="app-empty">
                            <i class="far fa-folder-open mr-2"></i>No linked applications yet.
                            <div class="mt-2"><a href="{{ route('guest.vacancies.index') }}">View open opportunities <i class="fas fa-arrow-right ml-1"></i></a></div>
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="app-panel" aria-labelledby="claimHistoryTitle">
                <div class="app-panel-heading">
                    <div class="app-panel-icon"><i class="fas fa-history"></i></div>
                    <div><h2 id="claimHistoryTitle">Claim Requests</h2><p>Monitor review and clarification decisions from HR.</p></div>
                </div>
                <div class="app-panel-content" id="claimsList" aria-live="polite">
                    @foreach($claims as $claim)
                        <div class="app-entry" data-claim-code="{{ $claim->claimed_application_code }}">
                            <div>
                                <div class="app-entry-title">{{ $claim->claimed_application_code }}</div>
                                @if($claim->status === 'clarification' && $claim->review_notes)
                                    <p>HR requests additional information: {{ $claim->review_notes }}</p>
                                    <p>Contact HR through the official channel. This application remains inaccessible until approval.</p>
                                @elseif($claim->status === 'cancelled')
                                    <p>HR has cancelled the earlier approval and revoked application access. Contact HR if you believe this is an error.</p>
                                @elseif($claim->status === 'rejected')
                                    <p>HR did not approve this ownership request. Contact HR for guidance if you believe this is an error.</p>
                                    @if($claim->review_notes && str_starts_with($claim->review_notes, 'This application was linked to another verified claimant.'))
                                        <p>Another ownership claim has already been approved. Contact HR to dispute this decision.</p>
                                    @endif
                                @endif
                            </div>
                            <span class="app-status app-status-{{ in_array($claim->status, ['pending','clarification','approved','rejected','cancelled'], true) ? $claim->status : 'pending' }}">{{ ucfirst($claim->status) }}</span>
                        </div>
                    @endforeach
                    @if($claims->isEmpty())
                        <div class="app-empty" id="emptyClaims">No historical claim requests yet.</div>
                    @endif
                </div>
            </section>
        </div>

        <div class="col-lg-5">
            <section class="app-panel" aria-labelledby="profileTitle">
                <div class="app-panel-heading">
                    <div class="app-panel-icon"><i class="far fa-address-card"></i></div>
                    <div><h2 id="profileTitle">Master Profile</h2><p>Your information for future submissions.</p></div>
                </div>
                <div class="app-panel-content">
                    <strong>{{ trim(($profile->first_name ?: '').' '.($profile->last_name ?: '')) ?: 'Profile not completed' }}</strong>
                    <p class="app-muted mb-2">{{ $profile->email ?: auth()->user()->email }}</p>
                    @if(!$profile->date_of_birth)
                        <div class="alert alert-info small">Add your date of birth so RMS can calculate your age automatically.</div>
                    @endif
                    <a href="{{ route('applicant.profile.edit') }}" class="btn btn-outline-primary btn-block">Edit master profile</a>
                    <hr>
                    <h3 class="h6 font-weight-bold">Available Vacancies</h3>
                    <p class="app-muted">Browse currently open recruitment opportunities. Your saved master profile will fill in your next application.</p>
                    <a class="btn btn-primary btn-block" href="{{ route('guest.vacancies.index') }}">Browse vacancies</a>
                </div>
            </section>

            <section class="app-panel" aria-labelledby="claimTitle">
                <div class="app-panel-heading">
                    <div class="app-panel-icon"><i class="fas fa-file-signature"></i></div>
                    <div><h2 id="claimTitle">Claim Historical Application</h2><p>Link older applications only after HR verifies ownership.</p></div>
                </div>
                <div class="app-panel-content">
                    <p class="app-claim-note">If you applied using a different email address, provide the original application code and email. HR approval is required.</p>
                    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
                    <div id="claimNotice" role="status" aria-live="polite" class="alert d-none"></div>
                    <form id="historicalClaimForm" action="{{ route('applicant.claims.store') }}" method="POST">
                        @csrf
                        <div class="form-group app-field">
                            <label for="claimApplicationCode">Application code</label>
                            <input class="form-control" id="claimApplicationCode" name="application_code" required maxlength="255" autocomplete="off" placeholder="Your original application code">
                            <div id="claimCodeError" class="invalid-feedback"></div>
                        </div>
                        <div class="form-group app-field">
                            <label for="claimOldEmail">Previous email</label>
                            <input class="form-control" id="claimOldEmail" type="email" name="email" required maxlength="255" autocomplete="email" placeholder="Email used for the original application">
                            <div id="claimEmailError" class="invalid-feedback"></div>
                        </div>
                        <button type="submit" id="claimSubmit" class="btn btn-primary btn-block">Request HR review</button>
                        <p class="app-claim-note mt-2 mb-0"><i class="fas fa-info-circle mr-1"></i>Claims do not grant access until verified and approved.</p>
                    </form>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
(function() {
    'use strict';
    var form = document.getElementById('historicalClaimForm');
    var button = document.getElementById('claimSubmit');
    var notice = document.getElementById('claimNotice');

    function message(text, type) {
        notice.textContent = text;
        notice.className = 'alert alert-' + type;
    }
    function clearErrors() {
        form.querySelectorAll('.is-invalid').forEach(function(input) {
            input.classList.remove('is-invalid');
        });
        document.getElementById('claimCodeError').textContent = '';
        document.getElementById('claimEmailError').textContent = '';
    }
    function showErrors(fields) {
        ['application_code', 'email'].forEach(function(name) {
            if (!fields[name] || !fields[name].length) return;
            var input = form.elements.namedItem(name);
            input.classList.add('is-invalid');
            document.getElementById(name === 'email' ? 'claimEmailError' : 'claimCodeError').textContent = fields[name][0];
        });
        var alertMessage = fields.claim && fields.claim.length ? fields.claim[0] : null;
        if (alertMessage) message(alertMessage, 'danger');
    }
    function addPendingClaim(code, currentStatus) {
        var list = document.getElementById('claimsList');
        var found = Array.from(list.querySelectorAll('[data-claim-code]')).some(function(entry) {
            return entry.dataset.claimCode === code;
        });
        if (found) return;
        var empty = document.getElementById('emptyClaims');
        if (empty) empty.remove();
        var entry = document.createElement('div');
        entry.className = 'app-entry';
        entry.dataset.claimCode = code;
        var info = document.createElement('div');
        var name = document.createElement('div');
        name.className = 'app-entry-title';
        name.textContent = code;
        info.appendChild(name);
        var badge = document.createElement('span');
        badge.className = 'app-status app-status-pending';
        badge.textContent = currentStatus === 'clarification' ? 'Clarification' : 'Pending';
        entry.appendChild(info);
        entry.appendChild(badge);
        list.prepend(entry);
    }

    form.addEventListener('submit', function(event) {
        if (!window.fetch || !window.FormData) return;
        event.preventDefault();
        clearErrors();
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        button.disabled = true;
        button.textContent = 'Submitting request…';
        fetch(form.action, {
            method: 'POST', body: new FormData(form), credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function(response) {
            window.RMSApplicantSession.check(response);
            return response.json().then(function(payload) {
                if (!response.ok) {
                    var error = new Error(payload.message || 'Unable to submit the claim.');
                    error.fields = payload.errors || {};
                    error.status = response.status;
                    throw error;
                }
                return payload;
            });
        }).then(function(data) {
            message(data.message || 'Claim submitted for HR review.', 'success');
            addPendingClaim(data.application_code, data.status);
            form.reset();
        }).catch(function(error) {
            if (window.RMSApplicantSession.expired || error.status === 401 || error.status === 419) {
                window.RMSApplicantSession.show();
                message('Your session expired. Use Sign in again to continue. Your claim has not been confirmed.', 'warning');
                return;
            }
            showErrors(error.fields || {});
            message(error.status === 429 ? 'Too many requests. Please wait a minute before trying again.' :
                (error.fields && error.fields.claim ? error.fields.claim[0] : error.message || 'Unable to submit.'), 'danger');
        }).finally(function() {
            button.disabled = window.RMSApplicantSession.expired;
            button.textContent = window.RMSApplicantSession.expired ? 'Sign in again to continue' : 'Request HR review';
        });
    });
})();
</script>
@endsection
