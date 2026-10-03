@extends('adminlte::page')
@section('title','New Skills Test')
@section('content_header')<h1>New Skills Test</h1>@stop

@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<form method="post" action="{{ route('admin.skills.store') }}" id="skillCreateForm">@csrf
<input type="hidden" name="ai_generated_task" id="aiGeneratedTask" value="{{ old('ai_generated_task',0) }}">
<input type="hidden" name="generated_rubric" id="generatedRubric" value="{{ old('generated_rubric') }}">

<div class="card">
    <div class="card-header"><strong>Task & Schedule</strong></div>
    <div class="card-body">
        <div class="form-group">
            <label>Position</label>
            <select name="vacancy_id" id="vacancyId" class="form-control" required>
                <option value="">Select</option>
                @foreach($vacancies as $v)
                    <option value="{{ $v->id }}" {{ old('vacancy_id')==$v->id?'selected':'' }}>
                        {{ $v->position_title }} ({{ $v->cycle }})
                    </option>
                @endforeach
            </select>
        </div>

        <div class="card card-outline card-info">
            <div class="card-header d-flex align-items-center">
                <strong><i class="fas fa-magic mr-1"></i> AI Task Generator</strong>
                <span class="badge badge-light ml-2">Optional</span>
            </div>
            <div class="card-body">
                <p class="small text-muted">
                    Select the position and duration, then let AI draft the title, instructions,
                    expected output, and a 100-point analytic rubric. You can edit the populated
                    fields before creating the test.
                </p>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <input type="hidden" name="use_qualifications" value="0">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="useQualifications"
                                   name="use_qualifications" value="1"
                                   {{ old('use_qualifications','1') ? 'checked' : '' }}>
                            <label class="custom-control-label" for="useQualifications">
                                Use vacancy qualifications / qualification standards
                            </label>
                        </div>
                    </div>
                    <div class="form-group col-md-6">
                        <input type="hidden" name="use_job_description" value="0">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="useJobDescription"
                                   name="use_job_description" value="1"
                                   {{ old('use_job_description','1') ? 'checked' : '' }}>
                            <label class="custom-control-label" for="useJobDescription">
                                Use vacancy job description / details
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Generation focus</label>
                        @php $focus=old('generation_focus','mixed'); @endphp
                        <select name="generation_focus" id="generationFocus" class="form-control">
                            <option value="mixed" {{ $focus==='mixed'?'selected':'' }}>Mixed job-relevant task</option>
                            <option value="duties" {{ $focus==='duties'?'selected':'' }}>Duties and responsibilities</option>
                            <option value="technical" {{ $focus==='technical'?'selected':'' }}>Technical competencies</option>
                            <option value="situational" {{ $focus==='situational'?'selected':'' }}>Situational / work scenario</option>
                        </select>
                    </div>
                    <div class="form-group col-md-8">
                        <label>Additional context <span class="text-muted font-weight-normal">(optional)</span></label>
                        <textarea name="additional_context" id="additionalContext" rows="3" maxlength="30000"
                                  class="form-control"
                                  placeholder="Paste approved competency statements, duties, procedures, or reference material.">{{ old('additional_context') }}</textarea>
                    </div>
                </div>

                <button type="button" class="btn btn-info" id="generateAiTask">
                    <i class="fas fa-magic mr-1"></i> Generate Task & Rubric with AI
                </button>
                <span id="aiGeneratorStatus" class="small ml-2 text-muted"></span>

                <div id="aiRubricPreview" class="mt-3" style="{{ old('generated_rubric') ? '' : 'display:none;' }}">
                    <div class="alert alert-light border mb-0">
                        <strong>Generated rubric preview</strong>
                        <div class="small text-muted mb-2">
                            The generated task and rubric will remain pending human review before publication.
                        </div>
                        <div id="aiRubricRows"></div>
                        <div class="text-right mt-2"><strong>Total: <span id="aiRubricTotal">0</span>/100</strong></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-md-8">
                <label>Title</label>
                <input name="title" id="skillTitle" value="{{ old('title') }}" class="form-control" required>
            </div>
            <div class="form-group col-md-4">
                <label>Code</label>
                <input name="code" value="{{ old('code') }}" class="form-control" placeholder="Auto if blank">
            </div>
        </div>

        <div class="form-group">
            <label>Instructions</label>
            <textarea name="instructions" id="skillInstructions" class="form-control" rows="9" required>{{ old('instructions') }}</textarea>
            <small class="text-muted">AI-generated instructions may be edited before creating the test.</small>
        </div>

        <div class="form-group">
            <label>Expected output</label>
            <textarea name="expected_output" id="skillExpectedOutput" class="form-control" rows="4">{{ old('expected_output') }}</textarea>
        </div>

        <div class="form-row">
            <div class="form-group col-md-4">
                <label>Opens</label>
                <input type="datetime-local" name="start_date" value="{{ old('start_date') }}" class="form-control" required>
            </div>
            <div class="form-group col-md-4">
                <label>Closes</label>
                <input type="datetime-local" name="end_date" value="{{ old('end_date') }}" class="form-control" required>
            </div>
            <div class="form-group col-md-4">
                <label>Duration (minutes)</label>
                <input type="number" min="1" max="480" name="duration" id="skillDuration"
                       value="{{ old('duration',60) }}" class="form-control" required>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>Access, Submission & Scoring</strong></div>
    <div class="card-body">
        <div class="form-group">
            <label>Who may take it?</label>
            <select name="access_mode" class="form-control">
                <option value="all_taken_in" {{ old('access_mode','all_taken_in')==='all_taken_in'?'selected':'' }}>All taken-in applicants for the position</option>
                <option value="selected_applicants" {{ old('access_mode')==='selected_applicants'?'selected':'' }}>Selected applicants only</option>
            </select>
        </div>

        <div class="form-group">
            <label>Submission modes</label>
            <div>
                <label class="mr-3">
                    <input type="checkbox" name="submission_modes[]" value="inline" {{ in_array('inline',old('submission_modes',['inline']),true)?'checked':'' }}>
                    Inline response
                </label>
                <label>
                    <input type="checkbox" name="submission_modes[]" value="file" {{ in_array('file',old('submission_modes',[]),true)?'checked':'' }}>
                    File upload
                </label>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-md-4">
                <label>Allowed extensions</label>
                <input name="allowed_extensions" value="{{ old('allowed_extensions','docx') }}" class="form-control">
                <small class="text-muted">Comma-separated, e.g. docx,pdf,xlsx</small>
            </div>
            <div class="form-group col-md-4">
                <label>Max upload size (KB)</label>
                <input type="number" name="max_file_size_kb" value="{{ old('max_file_size_kb',10240) }}" class="form-control">
            </div>
            <div class="form-group col-md-4">
                <label>Score release</label>
                <select name="score_release_policy" class="form-control" required>
                    <option value="manual" {{ old('score_release_policy','manual')==='manual'?'selected':'' }}>Manual after validation</option>
                    <option value="after_close" {{ old('score_release_policy')==='after_close'?'selected':'' }}>After schedule closes</option>
                    <option value="immediate" {{ old('score_release_policy')==='immediate'?'selected':'' }}>Immediately after human finalization</option>
                    <option value="hidden" {{ old('score_release_policy')==='hidden'?'selected':'' }}>Always hidden</option>
                </select>
            </div>
        </div>

        <input type="hidden" name="ai_scoring" value="0">
        <label>
            <input type="checkbox" name="ai_scoring" value="1" {{ old('ai_scoring','1') ? 'checked' : '' }}>
            Enable AI proposed scoring
        </label>

        <input type="hidden" name="status" value="0">
        <div class="alert alert-light border mt-3 mb-0 small">
            New skills tests are created as <strong>Draft</strong>.
            @if(old('ai_generated_task'))
                AI-generated task/rubric content will require human review before publishing.
            @else
                Add/review the rubric and pass readiness checks before publishing.
            @endif
        </div>
    </div>

    <div class="card-footer">
        <button class="btn btn-primary">Create Skills Test</button>
    </div>
</div>
</form>
@stop

@section('js')
<script>
(() => {
    const generateButton = document.getElementById('generateAiTask');
    const status = document.getElementById('aiGeneratorStatus');
    const vacancy = document.getElementById('vacancyId');
    const duration = document.getElementById('skillDuration');
    const title = document.getElementById('skillTitle');
    const instructions = document.getElementById('skillInstructions');
    const expectedOutput = document.getElementById('skillExpectedOutput');
    const focus = document.getElementById('generationFocus');
    const additionalContext = document.getElementById('additionalContext');
    const useQualifications = document.getElementById('useQualifications');
    const useJobDescription = document.getElementById('useJobDescription');
    const generatedFlag = document.getElementById('aiGeneratedTask');
    const generatedRubric = document.getElementById('generatedRubric');
    const rubricPreview = document.getElementById('aiRubricPreview');
    const rubricRows = document.getElementById('aiRubricRows');
    const rubricTotal = document.getElementById('aiRubricTotal');
    const csrf = document.querySelector('#skillCreateForm input[name="_token"]').value;
    let hasGeneratedDraft = generatedFlag.value === '1';

    function renderRubric(rubric) {
        rubricRows.innerHTML = '';
        let total = 0;

        rubric.forEach((row, index) => {
            const points = Number(row.max_points || 0);
            total += points;

            const item = document.createElement('div');
            item.className = 'border-top pt-2 mt-2';
            item.innerHTML =
                '<div class="d-flex justify-content-between">' +
                    '<strong>' + (index + 1) + '. ' + escapeHtml(row.criterion || '') + '</strong>' +
                    '<span class="badge badge-info">' + points + ' pts</span>' +
                '</div>' +
                '<div class="small text-muted">' + escapeHtml(row.description || '') + '</div>';
            rubricRows.appendChild(item);
        });

        rubricTotal.textContent = Number.isInteger(total) ? total : total.toFixed(2);
        rubricPreview.style.display = 'block';
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value;
        return div.innerHTML;
    }

    function invalidateGeneratedDraft() {
        if (!hasGeneratedDraft) return;

        hasGeneratedDraft = false;
        generatedFlag.value = '0';
        generatedRubric.value = '';
        rubricPreview.style.display = 'none';
        rubricRows.innerHTML = '';
        status.textContent = 'AI source settings changed. Generate again to attach a matching rubric.';
        status.className = 'small ml-2 text-warning';
    }

    [vacancy, duration, focus, additionalContext, useQualifications, useJobDescription]
        .forEach(element => element.addEventListener('change', invalidateGeneratedDraft));

    additionalContext.addEventListener('input', () => {
        if (hasGeneratedDraft) invalidateGeneratedDraft();
    });

    generateButton.addEventListener('click', async () => {
        if (!vacancy.value) {
            status.textContent = 'Select a position first.';
            status.className = 'small ml-2 text-danger';
            vacancy.focus();
            return;
        }

        const minutes = Number(duration.value);
        if (!minutes || minutes < 1 || minutes > 480) {
            status.textContent = 'Enter a valid duration from 1 to 480 minutes.';
            status.className = 'small ml-2 text-danger';
            duration.focus();
            return;
        }

        if (!useQualifications.checked && !useJobDescription.checked && !additionalContext.value.trim()) {
            status.textContent = 'Select a context source or provide additional context.';
            status.className = 'small ml-2 text-danger';
            return;
        }

        generateButton.disabled = true;
        generateButton.innerHTML = '<span class="spinner-border spinner-border-sm mr-1"></span> Generating…';
        status.textContent = 'AI is drafting the task and rubric…';
        status.className = 'small ml-2 text-muted';

        try {
            const response = await fetch(@json(route('admin.skills.ai_draft')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({
                    vacancy_id: Number(vacancy.value),
                    duration: minutes,
                    generation_focus: focus.value,
                    additional_context: additionalContext.value,
                    use_qualifications: useQualifications.checked ? 1 : 0,
                    use_job_description: useJobDescription.checked ? 1 : 0,
                }),
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(payload.message || 'AI generation failed.');
            }

            title.value = payload.title || '';
            instructions.value = payload.instructions || '';
            expectedOutput.value = payload.expected_output || '';

            generatedFlag.value = '1';
            generatedRubric.value = JSON.stringify(payload.rubric || []);
            hasGeneratedDraft = true;
            renderRubric(payload.rubric || []);

            status.textContent = 'Draft generated. Review or edit the populated fields, then create the test.';
            status.className = 'small ml-2 text-success';
        } catch (error) {
            status.textContent = error.message || 'AI generation failed. Please try again.';
            status.className = 'small ml-2 text-danger';
        } finally {
            generateButton.disabled = false;
            generateButton.innerHTML = '<i class="fas fa-magic mr-1"></i> Generate Task & Rubric with AI';
        }
    });

    if (generatedRubric.value) {
        try {
            const rubric = JSON.parse(generatedRubric.value);
            if (Array.isArray(rubric) && rubric.length) renderRubric(rubric);
        } catch (e) {}
    }
})();
</script>
@stop
