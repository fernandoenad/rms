@extends('adminlte::page')
@section('title','Written Exam Items')
@section('content_header')
<div class="d-flex justify-content-between align-items-center">
    <div><h1 class="mb-0">{{ $exam->title }}</h1><small class="text-muted">Written exam items</small></div>
    <div><a href="{{ route('admin.assessments.results',$exam) }}" class="btn btn-outline-secondary">Results</a>
    <a href="{{ $hasAttempts ? '#' : route('admin.assessments.items.create',$exam) }}" class="btn btn-primary {{ $hasAttempts?'disabled':'' }}">Add Item</a></div>
</div>
@stop
@section('content')
@if(session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif

@if(!$hasAttempts)
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
    <div class="form-group col-md-2"><label>Items</label><input type="number" min="1" max="100" name="count" value="{{ old('count',20) }}" class="form-control" required></div>
    <div class="form-group col-md-1"><label>Uni %</label><input type="number" min="0" max="100" name="solo_unistructural" value="{{ old('solo_unistructural',10) }}" class="form-control solo-percent" required></div>
    <div class="form-group col-md-1"><label>Multi %</label><input type="number" min="0" max="100" name="solo_multistructural" value="{{ old('solo_multistructural',20) }}" class="form-control solo-percent" required></div>
    <div class="form-group col-md-1"><label>Rel. %</label><input type="number" min="0" max="100" name="solo_relational" value="{{ old('solo_relational',45) }}" class="form-control solo-percent" required></div>
    <div class="form-group col-md-1"><label>EA %</label><input type="number" min="0" max="100" name="solo_extended_abstract" value="{{ old('solo_extended_abstract',25) }}" class="form-control solo-percent" required></div>
    <div class="form-group col-md-2 d-flex align-items-end"><button id="generateAiBtn" class="btn btn-primary btn-block"><i class="fas fa-magic mr-1"></i> Generate</button></div>
</div>
<div class="d-flex justify-content-between flex-wrap">
    <small class="text-muted">Generated items remain reviewable before use. The AI is instructed not to invent unsupported policies, duties, thresholds, or procedures.</small>
    <small id="soloTotal" class="font-weight-bold"></small>
</div>
</form>
</div></div>
@endif

<div class="card"><div class="card-body">
<div class="mb-3">
<form method="post" action="{{ route('admin.assessments.items.import',$exam) }}" enctype="multipart/form-data" class="form-inline">@csrf
<input type="file" name="file" accept=".csv,.txt" class="form-control-file mr-2" {{ $hasAttempts?'disabled':'' }} required>
<button class="btn btn-sm btn-outline-info" {{ $hasAttempts?'disabled':'' }}>Import CSV</button>
</form>
</div>
<div class="table-responsive"><table class="table table-hover">
<thead><tr><th>ID</th><th>Item</th><th>SOLO</th><th>Difficulty</th><th>Correct answer</th><th>Status</th><th></th></tr></thead>
<tbody>
@forelse($items as $item)
@php $correct=$item->options->firstWhere('is_correct',true); @endphp
<tr>
<td>{{ $item->id }}</td>
<td>{{ Str::limit($item->question,140) }} @if($item->ai_generated)<span class="badge badge-info">AI</span>@endif</td>
<td>{{ $item->solo_level ?? '-' }}</td>
<td>{{ $item->difficulty ?? '-' }}</td>
<td>{{ $correct ? $correct->option_text : $item->answer_key }}</td>
<td>{{ $item->status ? 'Active':'Inactive' }}</td>
<td class="text-nowrap">
<a class="btn btn-sm btn-warning {{ $hasAttempts?'disabled':'' }}" href="{{ $hasAttempts?'#':route('admin.assessments.items.edit',[$exam,$item]) }}"><i class="fas fa-edit"></i></a>
<form class="d-inline" method="post" action="{{ route('admin.assessments.items.toggle',[$exam,$item]) }}">@csrf @method('put')<button class="btn btn-sm btn-outline-secondary" {{ $hasAttempts?'disabled':'' }}><i class="fas fa-eye"></i></button></form>
</td>
</tr>
<tr class="bg-light"><td></td><td colspan="6">
<details><summary>Review item details</summary>
<div class="mt-2"><strong>{{ $item->question }}</strong></div>
<ol type="A" class="mt-2">@foreach($item->options->sortBy('source_position') as $option)<li class="{{ $option->is_correct?'text-success font-weight-bold':'' }}">{{ $option->option_text }}</li>@endforeach</ol>
@if($item->competency_basis)<div><strong>Basis:</strong> {{ $item->competency_basis }}</div>@endif
@if($item->rationale)<div><strong>Rationale:</strong> {{ $item->rationale }}</div>@endif
</details></td></tr>
@empty<tr><td colspan="7">No items yet.</td></tr>@endforelse
</tbody></table></div>
</div></div>
@if($hasAttempts)<div class="alert alert-warning">Items are locked because attempts exist. Duplicate the exam to create a revised or parallel set.</div>@endif
@stop


@section('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
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

        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm mr-1"></span> Generating…';
    });
});
</script>
@stop
