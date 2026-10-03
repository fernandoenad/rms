@extends('adminlte::page')
@section('title','Written Exam Items')
@section('content_header')
<div class="d-flex justify-content-between align-items-center">
    <div><h1 class="mb-0">{{ $exam->title }}</h1><small class="text-muted">Written exam items</small></div>
    <div>
        @if($exam->assessment_group_id)
            <a href="{{ route('admin.assessment_groups.edit',$exam->assessment_group_id) }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to Group</a>
        @else
            <a href="{{ route('admin.assessments.edit',$exam) }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back</a>
        @endif
        <a href="{{ route('admin.assessments.results',$exam) }}" class="btn btn-outline-secondary">Results</a>
        <a href="{{ ($hasAttempts || (int)$exam->status===1) ? '#' : route('admin.assessments.items.create',$exam) }}" class="btn btn-primary {{ ($hasAttempts || (int)$exam->status===1)?'disabled':'' }}">Add Item</a>
    </div>
</div>
@stop
@section('content')
@if(session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif
@php
    $locked = $hasAttempts || (int)$exam->status === 1;
    $bp = optional($exam->assessmentGroup)->blueprint ?: [];
    $bpSolo = $bp['solo_distribution'] ?? [];
    $pendingGeneratedCount = $items->where('ai_generated', true)->where('review_status', 'pending_review')->count();
@endphp

<div id="setReadinessCard" class="card card-outline {{ $readiness['ready'] ? 'card-success' : 'card-warning' }}">
    <div class="card-header"><strong>Set Readiness</strong></div>
    <div class="card-body py-2" id="setReadinessBody">
        @if($readiness['ready'])
            <span class="badge badge-success">Ready to publish</span>
            <span class="ml-2 text-muted">{{ $readiness['item_count'] }} active item(s)</span>
        @else
            <span class="badge badge-warning">Not ready</span>
            <ul class="mb-0 mt-2">
                @foreach(array_slice($readiness['issues'],0,12) as $issue)<li>{{ $issue }}</li>@endforeach
            </ul>
        @endif
    </div>
</div>

<div class="card card-outline card-info" id="aiGenerationCard" style="{{ $generationRuns->isEmpty() ? 'display:none;' : '' }}">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>AI Generation Runs</strong>
        <span id="aiLiveStatus" class="small text-muted"></span>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-sm mb-0">
            <thead><tr><th>Requested</th><th>Generated</th><th>Batches</th><th>Status</th><th>Updated</th></tr></thead>
            <tbody id="aiGenerationBody">
            @foreach($generationRuns as $run)
                <tr>
                    <td>{{ $run->requested_count }}</td>
                    <td>{{ $run->generated_count }}</td>
                    <td>{{ $run->completed_batches }} / {{ $run->batch_count }}</td>
                    <td><span class="badge badge-{{ str_contains($run->status,'error') ? 'warning' : ($run->status==='completed' ? 'success' : 'info') }}">{{ $run->status }}</span></td>
                    <td>{{ $run->updated_at->format('M d, h:i:s A') }}</td>
                </tr>
                @if($run->last_error)<tr><td colspan="5" class="small text-danger">{{ Str::limit($run->last_error,250) }}</td></tr>@endif
            @endforeach
            </tbody>
        </table>
    </div>
</div>

@if(!$locked)
<div class="card border-primary">
<div class="card-header"><strong><i class="fas fa-magic"></i> Generate Written Exam with AI</strong></div>
<div class="card-body">
<form method="post" action="{{ route('admin.assessments.ai_generate',$exam) }}">@csrf
<div class="alert alert-light border">
    <strong>Context used by AI</strong>
    <div class="small text-muted mt-1">
        RMS can use the vacancy record automatically. You may also paste a more detailed job description,
        duties, office procedures, competency statements, or other approved reference material below.
    </div>
</div>

<div class="form-row">
    <div class="form-group col-md-6">
        <div class="custom-control custom-checkbox mb-2">
            <input type="checkbox" class="custom-control-input" id="useQualifications" name="use_qualifications" value="1"
                   {{ old('use_qualifications', $exam->ai_use_qualifications ?? true) ? 'checked' : '' }}>
            <label class="custom-control-label" for="useQualifications">Use vacancy qualifications / qualification standards</label>
        </div>
        <div class="border rounded p-2 bg-light small" style="max-height:140px;overflow:auto;">
            {{ optional($exam->vacancy)->qualifications ?: 'No qualifications are currently stored for this vacancy.' }}
        </div>
    </div>

    <div class="form-group col-md-6">
        <div class="custom-control custom-checkbox mb-2">
            <input type="checkbox" class="custom-control-input" id="useJobDescription" name="use_job_description" value="1"
                   {{ old('use_job_description', $exam->ai_use_job_description ?? true) ? 'checked' : '' }}>
            <label class="custom-control-label" for="useJobDescription">Use vacancy job description / details</label>
        </div>
        <div class="border rounded p-2 bg-light small" style="max-height:140px;overflow:auto;">
            {{ optional($exam->vacancy)->vacancy ?: 'No job description/details are currently stored for this vacancy.' }}
        </div>
    </div>
</div>

<div class="form-group">
    <label for="additionalContext">Additional Context for AI <span class="text-muted font-weight-normal">(optional)</span></label>
    <textarea id="additionalContext" name="additional_context" rows="8" maxlength="30000" class="form-control"
              placeholder="Paste the detailed job description, duties and responsibilities, competency statements, approved policy excerpts, office procedures, or other source material here.">{{ old('additional_context', $exam->ai_context) }}</textarea>
    <small class="form-text text-muted">
        This text is saved with the exam so you can reuse the same context when preparing equivalent sets.
        Do not paste confidential personal information or applicant data.
    </small>
</div>

<div class="form-row">
    <div class="form-group col-md-4">
        <label>Generation Focus</label>
        @php $focus = old('generation_focus', $exam->ai_generation_focus ?: 'mixed'); @endphp
        <select name="generation_focus" class="form-control" required>
            <option value="mixed" {{ $focus==='mixed'?'selected':'' }}>Mixed job-relevant assessment</option>
            <option value="duties" {{ $focus==='duties'?'selected':'' }}>Duties and responsibilities</option>
            <option value="technical" {{ $focus==='technical'?'selected':'' }}>Technical competencies</option>
            <option value="situational" {{ $focus==='situational'?'selected':'' }}>Situational judgment</option>
        </select>
    </div>
    <div class="form-group col-md-2"><label>Items</label><input type="number" min="1" max="100" name="count" value="{{ old('count',$bp['item_count'] ?? 20) }}" class="form-control" required></div>
    <div class="form-group col-md-1"><label>Uni %</label><input type="number" min="0" max="100" name="solo_unistructural" value="{{ old('solo_unistructural',$bpSolo['unistructural'] ?? 10) }}" class="form-control solo-percent" required></div>
    <div class="form-group col-md-1"><label>Multi %</label><input type="number" min="0" max="100" name="solo_multistructural" value="{{ old('solo_multistructural',$bpSolo['multistructural'] ?? 20) }}" class="form-control solo-percent" required></div>
    <div class="form-group col-md-1"><label>Rel. %</label><input type="number" min="0" max="100" name="solo_relational" value="{{ old('solo_relational',$bpSolo['relational'] ?? 45) }}" class="form-control solo-percent" required></div>
    <div class="form-group col-md-1"><label>EA %</label><input type="number" min="0" max="100" name="solo_extended_abstract" value="{{ old('solo_extended_abstract',$bpSolo['extended_abstract'] ?? 25) }}" class="form-control solo-percent" required></div>
    <div class="form-group col-md-2 d-flex align-items-end"><button id="generateAiBtn" class="btn btn-primary btn-block"><i class="fas fa-magic mr-1"></i> Generate</button></div>
</div>
<div class="d-flex justify-content-between flex-wrap">
    <div>
        <small class="text-danger font-weight-bold d-block">Generating again replaces all current draft items in this test.</small>
        <small class="text-muted">The previous items are cleared first, and only the newest generation is populated. Generated items remain reviewable before use.</small>
    </div>
    <small id="soloTotal" class="font-weight-bold"></small>
</div>
</form>
</div></div>
@endif

<div class="card"><div class="card-body">
@if(!$locked && $pendingGeneratedCount > 0)
<div class="alert alert-warning d-flex flex-column flex-md-row justify-content-between align-items-md-center">
    <div class="mb-2 mb-md-0">
        <strong>{{ $pendingGeneratedCount }} AI-generated item(s) are pending review.</strong>
        <div class="small">Use bulk approval only after you have reviewed the generated set.</div>
    </div>
    <form method="post"
          action="{{ route('admin.assessments.items.approve_generated',$exam) }}"
          onsubmit="return confirm('Approve all {{ $pendingGeneratedCount }} pending AI-generated items? This will mark them approved and add/update them in the Assessment Content Bank.');">
        @csrf
        <button type="submit" class="btn btn-success text-nowrap">
            <i class="fas fa-check-double mr-1"></i> Approve All Generated Items
        </button>
    </form>
</div>
@endif
<div class="mb-3">
<form method="post" action="{{ route('admin.assessments.items.import',$exam) }}" enctype="multipart/form-data">
    @csrf
    <div class="form-row align-items-end">
        <div class="form-group col-md-8 col-lg-6 mb-2">
            <label class="small font-weight-bold mb-1">Import items from CSV</label>
            <div class="custom-file">
                <input type="file"
                       name="file"
                       id="writtenItemsCsv"
                       accept=".csv,.txt"
                       class="custom-file-input"
                       {{ $locked?'disabled':'' }}
                       required>
                <label class="custom-file-label" for="writtenItemsCsv">Choose CSV or TXT file</label>
            </div>
            <small class="form-text text-muted">Select a prepared item file, then import it into this draft set.</small>
        </div>
        <div class="form-group col-md-4 col-lg-2 mb-2">
            <button class="btn btn-outline-info btn-block" {{ $locked?'disabled':'' }}>
                <i class="fas fa-file-import mr-1"></i> Import CSV
            </button>
        </div>
    </div>
</form>
</div>
<div class="table-responsive"><table class="table table-hover">
<thead><tr><th>ID / Version</th><th>Item</th><th>SOLO</th><th>Difficulty</th><th>Correct answer</th><th>Review</th><th>Status</th><th></th></tr></thead>
<tbody>
@forelse($items as $item)
@php $correct=$item->options->firstWhere('is_correct',true); @endphp
<tr>
<td>{{ $item->id }}<br><small class="text-muted">v{{ $item->item_version ?? 1 }}</small></td>
<td>{{ Str::limit($item->question,140) }} @if($item->ai_generated)<span class="badge badge-info">AI</span>@endif</td>
<td>{{ $item->solo_level ?? '-' }}</td>
<td>{{ $item->difficulty ?? '-' }}</td>
<td>{{ $correct ? $correct->option_text : $item->answer_key }}</td>
<td>
    @if($item->review_status==='approved')<span class="badge badge-success">Approved</span>
    @elseif($item->review_status==='rejected')<span class="badge badge-danger">Rejected</span>
    @else<span class="badge badge-warning">Pending review</span>@endif
</td>
<td>{{ $item->status ? 'Active':'Inactive' }}</td>
<td class="text-nowrap">
<a class="btn btn-sm btn-warning {{ $locked?'disabled':'' }}" href="{{ $locked?'#':route('admin.assessments.items.edit',[$exam,$item]) }}"><i class="fas fa-edit"></i></a>
<form class="d-inline" method="post" action="{{ route('admin.assessments.items.toggle',[$exam,$item]) }}">@csrf @method('put')<button class="btn btn-sm btn-outline-secondary" {{ $locked?'disabled':'' }}><i class="fas fa-eye"></i></button></form>
</td>
</tr>
<tr class="bg-light"><td></td><td colspan="7">
<details><summary>Review item details</summary>
<div class="mt-2"><strong class="written-stem">{{ $item->question }}</strong></div>
<ol type="A" class="mt-2">@foreach($item->options->sortBy('source_position') as $option)<li class="{{ $option->is_correct?'text-success font-weight-bold':'' }}">{{ $option->option_text }}</li>@endforeach</ol>
@if($item->competency_basis)<div><strong>Basis:</strong> {{ $item->competency_basis }}</div>@endif
@if($item->rationale)<div><strong>Rationale:</strong> {{ $item->rationale }}</div>@endif
@if($item->review_notes)<div><strong>Review notes:</strong> {{ $item->review_notes }}</div>@endif
@if(!$locked)
<hr>
<form method="post" action="{{ route('admin.assessments.items.review',[$exam,$item]) }}" class="form-row align-items-end">@csrf @method('put')
    <div class="form-group col-md-3 mb-0">
        <label class="small">Review decision</label>
        <select name="decision" class="form-control form-control-sm">
            <option value="approved">Approve</option>
            <option value="pending_review">Return to pending</option>
            <option value="rejected">Reject</option>
        </select>
    </div>
    <div class="form-group col-md-7 mb-0">
        <label class="small">Reviewer notes</label>
        <input name="review_notes" class="form-control form-control-sm" value="{{ $item->review_notes }}">
    </div>
    <div class="form-group col-md-2 mb-0"><button class="btn btn-sm btn-primary btn-block">Save Review</button></div>
</form>
@endif
</details></td></tr>
@empty<tr><td colspan="8">No items yet.</td></tr>@endforelse
</tbody></table></div>
</div></div>
@if($locked)<div class="alert alert-warning">Items are locked because the set is published or attempts exist. Return an unused published set to draft, or create a new equivalent/versioned set after attempts begin.</div>@endif
@stop


@section('css')
<style>
.written-stem { white-space: pre-line; display:block; line-height:1.55; }
</style>
@stop

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csvInput = document.getElementById('writtenItemsCsv');
    csvInput?.addEventListener('change', function () {
        const label = this.nextElementSibling;
        if (label) {
            label.textContent = this.files?.[0]?.name || 'Choose CSV or TXT file';
        }
    });

    const fields = Array.from(document.querySelectorAll('.solo-percent'));
    const total = document.getElementById('soloTotal');
    const form = document.getElementById('generateAiBtn')?.closest('form');
    const button = document.getElementById('generateAiBtn');

    function updateTotal() {
        const sum = fields.reduce((value, field) => value + (parseInt(field.value || '0', 10) || 0), 0);
        total.textContent = 'SOLO total: ' + sum + '%';
        total.className = 'font-weight-bold ' + (sum === 100 ? 'text-success' : 'text-danger');
        return sum;
    }

    fields.forEach(field => field.addEventListener('input', updateTotal));
    updateTotal();

    const statusUrl = @json(route('admin.assessments.items.generation_status',$exam));
    const generationCard = document.getElementById('aiGenerationCard');
    const generationBody = document.getElementById('aiGenerationBody');
    const liveStatus = document.getElementById('aiLiveStatus');
    const readinessCard = document.getElementById('setReadinessCard');
    const readinessBody = document.getElementById('setReadinessBody');
    let pollTimer = null;
    let dots = 0;

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    function prettyStatus(status) {
        const labels = {
            queued: 'Queued',
            processing: 'Processing',
            completed: 'Completed',
            completed_partial: 'Completed partially',
            completed_with_errors: 'Completed with errors'
        };
        return labels[status] || String(status || '').replaceAll('_',' ');
    }

    function badgeClass(status) {
        if (status === 'completed') return 'success';
        if (status === 'completed_with_errors') return 'warning';
        if (status === 'completed_partial') return 'warning';
        if (status === 'processing') return 'primary';
        return 'info';
    }

    function animateLiveText(status) {
        dots = (dots + 1) % 4;
        const suffix = '.'.repeat(dots);
        if (status === 'queued') {
            liveStatus.innerHTML = '<i class="fas fa-clock mr-1"></i> Queuing' + suffix;
        } else if (status === 'processing') {
            liveStatus.innerHTML = '<span class="spinner-border spinner-border-sm mr-1"></span> Processing' + suffix;
        } else if (status === 'completed') {
            liveStatus.innerHTML = '<i class="fas fa-check-circle text-success mr-1"></i> Completed';
        } else if (status === 'completed_partial') {
            liveStatus.innerHTML = '<i class="fas fa-exclamation-circle text-warning mr-1"></i> Completed partially';
        } else if (status === 'completed_with_errors') {
            liveStatus.innerHTML = '<i class="fas fa-exclamation-triangle text-warning mr-1"></i> Completed with errors';
        } else {
            liveStatus.textContent = '';
        }
    }

    function renderReadiness(readiness) {
        readinessCard.classList.remove('card-success','card-warning');
        readinessCard.classList.add(readiness.ready ? 'card-success' : 'card-warning');

        if (readiness.ready) {
            readinessBody.innerHTML =
                '<span class="badge badge-success">Ready to publish</span>' +
                '<span class="ml-2 text-muted">' + readiness.item_count + ' active item(s)</span>';
        } else {
            const issues = (readiness.issues || []).slice(0,12)
                .map(issue => '<li>' + escapeHtml(issue) + '</li>').join('');
            readinessBody.innerHTML =
                '<span class="badge badge-warning">Not ready</span>' +
                '<ul class="mb-0 mt-2">' + issues + '</ul>';
        }
    }

    async function refreshGenerationStatus() {
        try {
            const response = await fetch(statusUrl, {
                headers: {'Accept':'application/json'},
                cache: 'no-store'
            });
            if (!response.ok) return;

            const payload = await response.json();
            const runs = payload.runs || [];

            if (runs.length) {
                generationCard.style.display = '';
                generationBody.innerHTML = runs.map(run => {
                    const errorRow = run.last_error
                        ? '<tr><td colspan="5" class="small text-danger">' + escapeHtml(run.last_error).slice(0,500) + '</td></tr>'
                        : '';

                    return '<tr>' +
                        '<td>' + run.requested_count + '</td>' +
                        '<td>' + run.generated_count + '</td>' +
                        '<td>' + run.completed_batches + ' / ' + run.batch_count + '</td>' +
                        '<td><span class="badge badge-' + badgeClass(run.status) + '">' + escapeHtml(prettyStatus(run.status)) + '</span></td>' +
                        '<td>' + escapeHtml(run.updated_at || '') + '</td>' +
                        '</tr>' + errorRow;
                }).join('');

                animateLiveText(runs[0].status);

                if (['queued','processing'].includes(runs[0].status)) {
                    if (!pollTimer) {
                        pollTimer = setInterval(refreshGenerationStatus, 2500);
                    }
                } else if (pollTimer) {
                    clearInterval(pollTimer);
                    pollTimer = null;
                }
            }

            if (payload.readiness) renderReadiness(payload.readiness);
        } catch (e) {
            liveStatus.textContent = 'Waiting for status…';
        }
    }

    if (document.querySelector('#aiGenerationBody tr')) {
        refreshGenerationStatus();
        pollTimer = setInterval(refreshGenerationStatus, 2500);
    }

    form?.addEventListener('submit', function (event) {
        if (updateTotal() !== 100) {
            event.preventDefault();
            alert('SOLO distribution must total exactly 100%.');
            return;
        }

        const hasVacancyContext =
            document.getElementById('useQualifications').checked ||
            document.getElementById('useJobDescription').checked;
        const hasAdditionalContext = document.getElementById('additionalContext').value.trim().length > 0;

        if (!hasVacancyContext && !hasAdditionalContext) {
            event.preventDefault();
            alert('Select at least one vacancy context source or paste additional context.');
            return;
        }

        const existingItemCount = {{ (int)$items->count() }};
        if (existingItemCount > 0 && !confirm(
            'Generate a new item set? This will permanently clear the current ' +
            existingItemCount + ' draft item(s) and replace them with the new AI generation.'
        )) {
            event.preventDefault();
            return;
        }

        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm mr-1"></span> Clearing & Queuing…';
        generationCard.style.display = '';
        liveStatus.innerHTML = '<i class="fas fa-clock mr-1"></i> Queuing…';
    });
});
</script>
@stop
