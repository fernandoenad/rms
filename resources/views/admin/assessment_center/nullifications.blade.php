@extends('adminlte::page')
@section('title', 'Board-Authorized Assessment Nullification')

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">Board-Authorized Assessment Nullification</h1>
        <small class="text-muted">Restricted HR action · Preserve original examination results and applicant records</small>
    </div>
    <a href="{{ route('admin.assessment_center.index') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i> Back to Assessment Center</a>
</div>
@stop

@section('content')
@include('admin.assessment_center._nav')

@if(session('status'))
<div class="alert alert-success" role="status">{{ session('status') }}</div>
@endif
@if($errors->any())
<div class="alert alert-danger" role="alert">
    <strong>The operation was not completed.</strong>
    <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
</div>
@endif

<div class="alert alert-warning">
    <strong>Board authority required.</strong> Nullification is an administrative decision, not a finding of cheating.
    This action sets the selected official Written and/or Skills criteria to <strong>0.00</strong> for applicants who started an attempt
    in either selected group. The scope is limited to this vacancy and these groups—not every applicant in the recruitment cycle.
    The original responses, rubric evaluations, event logs and raw scores remain intact.
</div>

<div class="card card-outline card-primary">
    <div class="card-header"><strong>1. Select scope and preview affected applicants</strong></div>
    <form method="GET" action="{{ route('admin.assessment_nullifications.index') }}">
        <div class="card-body">
            <div class="form-row">
                <div class="col-md-4 mb-2">
                    <label for="vacancyId">Position / Recruitment Cycle</label>
                    <select id="vacancyId" name="vacancy_id" class="form-control" required>
                        <option value="">Select a position</option>
                        @foreach($vacancies as $vacancy)
                        <option value="{{ $vacancy->id }}" {{ $vacancyId===$vacancy->id ? 'selected' : '' }}>
                            {{ $vacancy->position_title }} · {{ $vacancy->cycle }} (ID {{ $vacancy->id }})
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-2">
                    <label for="writtenGroupId">Written Assessment Group</label>
                    <select id="writtenGroupId" name="written_group_id" class="form-control">
                        <option value="">Do not nullify Written</option>
                        @foreach($writtenGroups as $group)
                        <option value="{{ $group->id }}" data-vacancy="{{ $group->vacancy_id }}" {{ $writtenId===$group->id ? 'selected' : '' }}>
                            {{ $group->title }} (ID {{ $group->id }}) · {{ $group->assessment_score_key ?: 'Not mapped' }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-2">
                    <label for="skillsGroupId">Skills Test Group</label>
                    <select id="skillsGroupId" name="skills_group_id" class="form-control">
                        <option value="">Do not nullify Skills</option>
                        @foreach($skillsGroups as $group)
                        <option value="{{ $group->id }}" data-vacancy="{{ $group->vacancy_id }}" {{ $skillsId===$group->id ? 'selected' : '' }}>
                            {{ $group->title }} (ID {{ $group->id }}) · {{ $group->assessment_score_key ?: 'Not mapped' }}
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <small class="text-muted">Both groups must belong to the same selected vacancy, use distinct applicant-score criteria, and have no unfinished active attempts.</small>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search mr-1"></i> Preview nullification</button>
        </div>
    </form>
</div>

@if($preview)
<div class="card card-outline card-danger">
    <div class="card-header"><strong>2. Verify Board authority and apply zero scores</strong></div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-sm-4"><div class="small text-uppercase text-muted font-weight-bold">Affected applicants</div><h3>{{ number_format($preview['count']) }}</h3></div>
            <div class="col-sm-4"><div class="small text-uppercase text-muted font-weight-bold">Criteria set to zero</div><h3>{{ count($preview['criteria']) }}</h3></div>
            <div class="col-sm-4"><div class="small text-uppercase text-muted font-weight-bold">Recruitment position</div><strong>{{ $preview['vacancy']->position_title }}</strong></div>
        </div>
        <p><strong>Affected criteria:</strong>
            @foreach($preview['criteria'] as $criterion)
                <span class="badge badge-danger mr-1">{{ $criterion }} → 0.00</span>
            @endforeach
        </p>
        <p class="small text-muted">First 20 affected applicants (complete scope is revalidated when you confirm):</p>
        <div class="table-responsive mb-3">
            <table class="table table-sm table-bordered">
                <thead><tr><th>Application Code</th><th>Applicant</th></tr></thead>
                <tbody>
                @foreach($preview['sample'] as $app)
                    <tr><td>{{ $app->application_code }}</td><td>{{ $app->last_name }}, {{ $app->first_name }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <form method="POST" action="{{ route('admin.assessment_nullifications.store') }}">
            @csrf
            <input type="hidden" name="vacancy_id" value="{{ $vacancyId }}">
            <input type="hidden" name="written_group_id" value="{{ $writtenId }}">
            <input type="hidden" name="skills_group_id" value="{{ $skillsId }}">
            <input type="hidden" name="scope_fingerprint" value="{{ $preview['fingerprint'] }}">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="boardReference">Board Resolution / Memorandum Reference</label>
                    <input id="boardReference" name="board_reference" class="form-control" value="{{ old('board_reference') }}" required minlength="4" maxlength="255" placeholder="Official meeting resolution or memorandum reference">
                </div>
                <div class="form-group col-md-6">
                    <label for="boardDate">Board Decision Date</label>
                    <input id="boardDate" type="date" name="board_date" class="form-control" value="{{ old('board_date') }}" max="{{ now()->toDateString() }}" required>
                </div>
            </div>
            <div class="form-group">
                <label for="boardReason">Documented Board Rationale</label>
                <textarea id="boardReason" name="reason" class="form-control" rows="3" required minlength="20" maxlength="5000" placeholder="Quote the grounds recorded in the Board minutes. Do not label all applicants as cheaters.">{{ old('reason') }}</textarea>
            </div>
            <div class="form-group">
                <label for="nullifyConfirmation">Type NULLIFY to confirm {{ $preview['count'] }} applicant(s) will have selected official criteria set to zero.</label>
                <input id="nullifyConfirmation" name="confirmation" class="form-control" required pattern="NULLIFY" autocomplete="off">
            </div>
            <div class="alert alert-light border small">
                Scores and official totals will be recalculated. The selected groups will be paused.
                Existing raw results and audit evidence will NOT be deleted. The change is protected from automatic score re-synchronization.
                The operation is atomic: if validation fails, no applicant scores are changed.
            </div>
            <button type="submit" class="btn btn-danger"><i class="fas fa-gavel mr-1"></i> Apply Board Nullification</button>
        </form>
    </div>
</div>
@endif

<div class="card">
    <div class="card-header"><strong>Nullification Register and Audit Exports</strong></div>
    <div class="card-body table-responsive p-0">
        <table class="table table-sm table-hover mb-0">
            <thead><tr><th>Batch / Date</th><th>Position</th><th>Board Reference</th><th>Criteria Rows</th><th>Status</th><th>Audit / Restoration</th></tr></thead>
            <tbody>
                @forelse($batches as $batch)
                <tr>
                    <td>#{{ $batch->id }}<div class="small text-muted">{{ $batch->created_at?->format('M j, Y g:i A') }}</div></td>
                    <td>{{ $batch->vacancy?->position_title }}</td>
                    <td>{{ $batch->board_reference }}</td>
                    <td>{{ number_format($batch->items_count) }}</td>
                    <td><span class="badge badge-{{ $batch->status === 'active' ? 'warning' : 'success' }}">{{ $batch->status === 'active' ? 'Nullified by Board' : 'Restored with Authorization' }}</span></td>
                    <td>
                        <a class="btn btn-sm btn-outline-primary mb-1" href="{{ route('admin.assessment_nullifications.export', $batch) }}"><i class="fas fa-file-csv mr-1"></i> Download Audit CSV</a>
                        @if($batch->status === 'active')
                        <details class="mt-1" aria-label="Restore nullification batch {{ $batch->id }}">
                            <summary class="text-danger" style="cursor:pointer">Authorized restoration</summary>
                            <form class="border rounded p-2 mt-2" method="POST" action="{{ route('admin.assessment_nullifications.restore', $batch) }}">
                                @csrf
                                <label>Superseding Board reference</label>
                                <input class="form-control form-control-sm mb-2" name="restoration_reference" required minlength="4" maxlength="255">
                                <label>Restoration reason</label>
                                <textarea class="form-control form-control-sm mb-2" name="restoration_reason" required minlength="20" maxlength="5000" rows="2"></textarea>
                                <label>Type RESTORE to confirm</label>
                                <input class="form-control form-control-sm mb-2" name="confirmation" required pattern="RESTORE" autocomplete="off">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Restore original criterion values</button>
                            </form>
                        </details>
                        @else
                        <small class="d-block text-muted">Restoration: {{ $batch->restoration_reference }}</small>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted p-3">No Board nullifications recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $batches->links() }}</div>
</div>
@stop

@section('js')
<script>
(function () {
    const vacancy = document.getElementById('vacancyId');
    const selectors = [document.getElementById('writtenGroupId'), document.getElementById('skillsGroupId')];
    if (!vacancy) return;
    function filterGroups() {
        selectors.forEach(function (selector) {
            if (!selector) return;
            Array.from(selector.options).forEach(function (option) {
                if (!option.value) return;
                option.hidden = option.dataset.vacancy !== vacancy.value;
                option.disabled = option.hidden;
            });
            if (selector.selectedOptions.length && selector.selectedOptions[0].disabled) selector.value = '';
        });
    }
    vacancy.addEventListener('change', filterGroups);
    filterGroups();
})();
</script>
@stop
