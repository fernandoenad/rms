@extends('adminlte::page')
@section('title', 'Applicant Integrity Flags')
@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
  <div>
    <h1 class="mb-0">Applicant Integrity Flags</h1>
    <small class="text-muted">Confidential HR screening and verification register</small>
  </div>
  <a href="{{ route('admin.assessment_center.index') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i> Assessment Center</a>
</div>
@stop
@section('content')
@include('admin.assessment_center._nav')
@if(session('status'))<div class="alert alert-success">{{session('status')}}</div>@endif
@if($errors->any())<div class="alert alert-danger"><strong>No change was made.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul></div>@endif
<div class="alert alert-info">
  <strong>Screening is not a finding of cheating.</strong> Automatic cases and manual referrals start as <em>Pending Review</em>.
  Only confirmed cases produce <strong>*</strong> in confidential internal CAR-RQA remarks. No flag alters score, qualifications, or eligibility.
  Browser hidden durations are approximate; unpaired events are incomplete evidence.
</div>
<div class="card card-outline card-primary">
  <div class="card-header"><strong>Select recruitment position</strong></div>
  <form method="GET" action="{{route('admin.integrity_flags.index')}}">
    <div class="card-body form-row align-items-end">
      <div class="col-md-7 mb-2">
        <label for="vacancySelector">Vacancy / Recruitment Cycle</label>
        <select class="form-control" id="vacancySelector" name="vacancy_id" required>
          <option value="">Select...</option>
          @foreach($vacancies as $vacancy)
          <option value="{{$vacancy->id}}" {{$vacancyId===(int)$vacancy->id?'selected':''}}>{{$vacancy->position_title}} · Cycle {{$vacancy->cycle}} · #{{$vacancy->id}}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3 mb-2">
        <label for="statusSelector">Flag Status</label>
        <select class="form-control" id="statusSelector" name="status">
          <option value="">All statuses</option>
          @foreach(['pending'=>'Pending Review','confirmed'=>'Confirmed for Verification','cleared'=>'Cleared'] as $value=>$label)
          <option value="{{$value}}" {{$status===$value?'selected':''}}>{{$label}}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2 mb-2"><button class="btn btn-primary btn-block" type="submit">View Flags</button></div>
    </div>
  </form>
</div>
@if($vacancyId)
<div class="row">
  <div class="col-md-8">
    <div class="card card-outline card-secondary">
      <div class="card-header"><strong>Screening policy — versioned and vacancy-specific</strong></div>
      <form action="{{route('admin.integrity_flags.rules')}}" method="POST">
        @csrf
        <input type="hidden" name="vacancy_id" value="{{$vacancyId}}">
        <div class="card-body">
          @if($rule)
          <div class="alert alert-light border small">Active: Version {{$rule->version}} · Approved reference: {{$rule->authority_reference}} · Prior versions remain in the audit history.</div>
          @else
          <div class="alert alert-warning small">No active policy. Set and authorize the first policy before running a scan.</div>
          @endif
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Switch count (5 or more)</label>
              <input type="number" name="switch_count" class="form-control" required min="1" max="10000" value="{{old('switch_count',$rule?->switch_count ?? 5)}}">
            </div>
            <div class="form-group col-md-6">
              <label>Total paired hidden seconds (over 60)</label>
              <input type="number" name="total_hidden_seconds" class="form-control" required min="0" max="86400" value="{{old('total_hidden_seconds',$rule?->total_hidden_seconds ?? 60)}}">
            </div>
            <div class="form-group col-md-6">
              <label>Average per completed interval (over 30s)</label>
              <input type="number" name="average_hidden_seconds" class="form-control" required min="0" max="86400" value="{{old('average_hidden_seconds',$rule?->average_hidden_seconds ?? 30)}}">
            </div>
            <div class="form-group col-md-6">
              <label>Longest paired hidden interval (over 120s)</label>
              <input type="number" name="longest_hidden_seconds" class="form-control" required min="0" max="86400" value="{{old('longest_hidden_seconds',$rule?->longest_hidden_seconds ?? 120)}}">
            </div>
            <div class="form-group col-12">
              <label>HRMPSB authority / policy reference</label>
              <input name="authority_reference" class="form-control" required minlength="4" maxlength="255" value="{{old('authority_reference')}}" placeholder="Document authorizing these screening criteria">
            </div>
          </div>
          <small class="text-muted">Saving creates a new policy version; older review decisions are not erased. An unpaired hidden event adds a technical-review signal, not a finding of misconduct.</small>
        </div>
        <div class="card-footer">
          <button type="submit" class="btn btn-outline-primary"><i class="fas fa-save mr-1"></i> Save New Policy Version</button>
        </div>
      </form>
      @if($rule)
      <form method="POST" action="{{route('admin.integrity_flags.scan')}}">
        @csrf
        <input type="hidden" name="rule_set_id" value="{{$rule->id}}">
        <div class="card-footer bg-light">
          <button class="btn btn-primary" type="submit"><i class="fas fa-search mr-1"></i> Run Screening with Version {{$rule->version}}</button>
          <small class="d-block mt-2 text-muted">Scans completed, non-voided Written and Skills attempts within this vacancy. It creates new confidential pending cases only. Re-running the same policy does not reopen cleared reviews.</small>
        </div>
      </form>
      @endif
      @if($history->isNotEmpty())
      <details class="card-footer"><summary style="cursor:pointer">Policy version history</summary>
        <ul class="small mt-2 mb-0">@foreach($history as $entry)
          <li>v{{$entry->version}} — {{$entry->authority_reference}} — {{$entry->is_active?'Active':'Superseded'}} ({{$entry->created_at?->format('Y-m-d')}})</li>
        @endforeach</ul>
      </details>
      @endif
    </div>
  </div>
  <div class="col-md-4">
    <div class="card card-outline card-warning">
      <div class="card-header"><strong>Manual applicant referral</strong></div>
      <form method="POST" action="{{route('admin.integrity_flags.manual')}}">
        @csrf
        <input type="hidden" name="vacancy_id" value="{{$vacancyId}}">
        <div class="card-body">
          <div class="form-group"><label>Application Code</label>
            <input class="form-control" name="application_code" required minlength="2" maxlength="255" value="{{old('application_code')}}">
          </div>
          <div class="form-group"><label>Reason / Evidence Reference</label>
            <textarea class="form-control" name="reason" required minlength="20" maxlength="5000" rows="4" placeholder="Document the specific concern and available evidence.">{{old('reason')}}</textarea>
          </div>
          <small class="text-muted">Manual referrals also require a separate review before RQA marking.</small>
        </div>
        <div class="card-footer"><button class="btn btn-outline-warning" type="submit"><i class="fas fa-flag mr-1"></i> Refer for Review</button></div>
      </form>
    </div>
    <div class="card">
      <div class="card-header"><strong>Cases in this vacancy</strong></div>
      <div class="card-body">
        <div>Pending review: <strong>{{number_format((int)($counts['pending'] ?? 0))}}</strong></div>
        <div>Confirmed: <strong>{{number_format((int)($counts['confirmed'] ?? 0))}}</strong></div>
        <div>Cleared: <strong>{{number_format((int)($counts['cleared'] ?? 0))}}</strong></div>
        <a href="{{route('admin.integrity_flags.export',['vacancy_id'=>$vacancyId])}}" class="btn btn-sm btn-outline-secondary mt-2"><i class="fas fa-file-csv mr-1"></i> Export Confidential Audit CSV</a>
      </div>
    </div>
  </div>
</div>
<div class="card card-outline card-dark">
  <div class="card-header"><strong>Applicant review cases</strong></div>
  <div class="card-body table-responsive p-0">
    <table class="table table-hover table-sm mb-0">
      <thead><tr><th>Applicant</th><th>Source</th><th>Signals / Evidence</th><th>Status</th><th>Decision & History</th></tr></thead>
      <tbody>
      @forelse($flags as $flag)
      <tr>
        <td>
          <strong>{{$flag->application?->application_code}}</strong>
          <div class="small">{{$flag->application?->getFullname()}}</div>
        </td>
        <td>{{$flag->source_type}} @if($flag->source_attempt_id)<div class="small text-muted">Attempt #{{$flag->source_attempt_id}}</div>@endif
          @if($flag->ruleSet)<div class="small text-muted">Policy v{{$flag->ruleSet->version}}</div>@endif
        </td>
        <td style="min-width:180px">
          @foreach($flag->signals ?? [] as $signal)<span class="badge badge-light border mr-1 mb-1">{{str_replace('_',' ',$signal)}}</span>@endforeach
          @if($flag->metrics)
          <div class="small text-muted">Switches: {{data_get($flag->metrics,'switch_count','–')}} · Total: {{data_get($flag->metrics,'total_hidden_seconds','–')}}s
            · Longest: {{data_get($flag->metrics,'longest_hidden_seconds','–')}}s
            · Avg: {{data_get($flag->metrics,'average_hidden_seconds','–')}}s
            · Unpaired: {{data_get($flag->metrics,'unpaired_hidden_intervals',0)}}</div>
          @endif
        </td>
        <td>
          <span class="badge badge-{{$flag->status==='confirmed'?'warning':($flag->status==='cleared'?'success':'secondary')}}">
            {{['pending'=>'Pending Review','confirmed'=>'Confirmed for Verification (*)','cleared'=>'Cleared'][$flag->status] ?? $flag->status}}
          </span>
          @if($flag->reviewed_at)<div class="small text-muted">{{$flag->reviewed_at->format('Y-m-d H:i')}}</div>@endif
        </td>
        <td style="min-width:245px">
          <details><summary class="text-primary" style="cursor:pointer">Review / history</summary>
            <div class="small mt-2">
              @if($flag->actions->isNotEmpty())
              <strong>Audit trail</strong>
              <ul class="pl-3">@foreach($flag->actions as $event)
              <li>{{$event->created_at?->format('Y-m-d H:i')}} — {{$event->action}} → {{$event->to_status}} @if($event->actor_id)(user #{{$event->actor_id}})@endif: {{$event->reason}}</li>
              @endforeach</ul>
              @endif
            </div>
            <form method="POST" action="{{route('admin.integrity_flags.decide',$flag)}}" class="border rounded p-2">
              @csrf
              <label for="reason{{$flag->id}}">HRMPSB review rationale</label>
              <textarea id="reason{{$flag->id}}" name="reason" class="form-control form-control-sm mb-2" rows="2" minlength="20" maxlength="5000" required></textarea>
              <div class="form-row">
                <div class="col-sm-6 mb-1"><button class="btn btn-sm btn-warning btn-block" type="submit" name="decision" value="confirmed" {{$flag->status==='confirmed'?'disabled':''}}>Confirm *</button></div>
                <div class="col-sm-6 mb-1"><button class="btn btn-sm btn-outline-success btn-block" type="submit" name="decision" value="cleared" {{$flag->status==='cleared'?'disabled':''}}>Clear Flag</button></div>
              </div>
            </form>
          </details>
        </td>
      </tr>
      @empty<tr><td colspan="5" class="text-center text-muted p-4">No review cases match the selection.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-footer">{{$flags->links()}}</div>
</div>
@stop
