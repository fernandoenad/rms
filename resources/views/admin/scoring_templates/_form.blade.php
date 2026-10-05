@php
    $criteriaRows = old('criteria', collect($criteria)->map(function($row){
        return is_array($row) ? $row : [
            'key'=>$row->key,
            'label'=>$row->label,
            'max_points'=>$row->max_points,
            'source_type'=>$row->source_type,
            'instructions'=>$row->instructions,
        ];
    })->values()->all());
@endphp

@if($errors->any())
<div class="alert alert-danger">
    <strong>Please review the scoring template.</strong>
    <ul class="mb-0 mt-1">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<div class="row">
    <div class="col-lg-4">
        <div class="card card-outline card-primary">
            <div class="card-header"><strong>Template Information</strong></div>
            <div class="card-body">
                <div class="form-group">
                    <label>Template Name</label>
                    <input name="type" value="{{ old('type',$template->type) }}" class="form-control" required maxlength="255"
                           placeholder="e.g. Administrative Officer II">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="4" class="form-control" maxlength="2000"
                              placeholder="Position group, policy basis, or notes">{{ old('description',$template->description) }}</textarea>
                </div>
                <div class="form-group">
                    <label>Version</label>
                    <input class="form-control" value="v{{ $template->version ?: 1 }}" disabled>
                    <small class="text-muted">In-use templates are structurally frozen. Create a new version to change criteria.</small>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control" required>
                        <option value="1" {{ (string)old('status',$template->status)==='1'?'selected':'' }}>Active — available for vacancies</option>
                        <option value="0" {{ (string)old('status',$template->status)==='0'?'selected':'' }}>Draft / Inactive</option>
                    </select>
                </div>
                @if($inUse)
                    <div class="alert alert-warning small mb-0">
                        <strong>Template is in use.</strong> Criteria and point values are locked to protect existing vacancy and assessment records. Use <em>Duplicate as New Version</em> to revise the structure.
                    </div>
                @endif
            </div>
        </div>

        <div class="card card-outline card-secondary">
            <div class="card-header"><strong>Source Types</strong></div>
            <div class="card-body small">
                <p class="mb-1"><strong>Manual</strong> — evaluator-entered comparative score.</p>
                <p class="mb-1"><strong>Written</strong> — reserved for Written Test result integration.</p>
                <p class="mb-1"><strong>Skills</strong> — reserved for approved Skills Test score integration.</p>
                <p class="mb-1"><strong>Interview</strong> — interview / BEI component.</p>
                <p class="mb-0"><strong>Other</strong> — other comparative assessment source.</p>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card card-outline card-success">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Scoring Criteria</strong>
                @unless($inUse)
                <button type="button" class="btn btn-sm btn-outline-primary ml-auto" id="addCriterion">
                    <i class="fas fa-plus mr-1"></i> Add Criterion
                </button>
                @endunless
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-sm mb-0" id="criteriaTable">
                    <thead>
                    <tr>
                        <th style="min-width:150px">Key</th>
                        <th style="min-width:180px">Label</th>
                        <th style="width:110px">Points</th>
                        <th style="min-width:125px">Source</th>
                        <th style="min-width:180px">Instructions</th>
                        @unless($inUse)<th style="width:55px"></th>@endunless
                    </tr>
                    </thead>
                    <tbody id="criteriaRows">
                    @foreach($criteriaRows as $i=>$criterion)
                    <tr>
                        <td><input name="criteria[{{ $i }}][key]" value="{{ $criterion['key'] ?? '' }}" class="form-control form-control-sm criterion-key" {{ $inUse?'readonly':'' }} required></td>
                        <td><input name="criteria[{{ $i }}][label]" value="{{ $criterion['label'] ?? '' }}" class="form-control form-control-sm" {{ $inUse?'readonly':'' }} required></td>
                        <td><input type="number" step=".001" min=".001" max="100" name="criteria[{{ $i }}][max_points]" value="{{ $criterion['max_points'] ?? '' }}" class="form-control form-control-sm criterion-points" {{ $inUse?'readonly':'' }} required></td>
                        <td>
                            <select name="criteria[{{ $i }}][source_type]" class="form-control form-control-sm" {{ $inUse?'disabled':'' }}>
                                @foreach(['manual'=>'Manual','written'=>'Written','skills'=>'Skills','interview'=>'Interview','other'=>'Other'] as $value=>$label)
                                    <option value="{{ $value }}" {{ ($criterion['source_type'] ?? 'manual')===$value?'selected':'' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @if($inUse)<input type="hidden" name="criteria[{{ $i }}][source_type]" value="{{ $criterion['source_type'] ?? 'manual' }}">@endif
                        </td>
                        <td><input name="criteria[{{ $i }}][instructions]" value="{{ $criterion['instructions'] ?? '' }}" class="form-control form-control-sm" {{ $inUse?'readonly':'' }}></td>
                        @unless($inUse)
                        <td><button type="button" class="btn btn-xs btn-outline-danger remove-criterion"><i class="fas fa-times"></i></button></td>
                        @endunless
                    </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr>
                        <th colspan="2" class="text-right">Total</th>
                        <th><span id="criteriaTotal" class="badge badge-secondary">0.000</span></th>
                        <th colspan="{{ $inUse ? 2 : 3 }}"><small class="text-muted">Must total exactly 100 points.</small></th>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

@section('js')
<script>
(function(){
    var rows = document.getElementById('criteriaRows');
    var add = document.getElementById('addCriterion');
    var total = document.getElementById('criteriaTotal');

    function renumber(){
        rows.querySelectorAll('tr').forEach(function(row,index){
            row.querySelectorAll('[name]').forEach(function(el){
                el.name = el.name.replace(/criteria\[\d+\]/,'criteria['+index+']');
            });
        });
    }

    function recalc(){
        var value = 0;
        rows.querySelectorAll('.criterion-points').forEach(function(input){
            value += parseFloat(input.value || 0) || 0;
        });
        total.textContent = value.toFixed(3);
        total.className = 'badge ' + (Math.abs(value-100) < .001 ? 'badge-success' : 'badge-danger');
    }

    if(add){
        add.addEventListener('click', function(){
            var index = rows.querySelectorAll('tr').length;
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td><input name="criteria['+index+'][key]" class="form-control form-control-sm criterion-key" required placeholder="Criterion_Key"></td>'+
                '<td><input name="criteria['+index+'][label]" class="form-control form-control-sm" required placeholder="Criterion label"></td>'+
                '<td><input type="number" step=".001" min=".001" max="100" name="criteria['+index+'][max_points]" class="form-control form-control-sm criterion-points" required></td>'+
                '<td><select name="criteria['+index+'][source_type]" class="form-control form-control-sm"><option value="manual">Manual</option><option value="written">Written</option><option value="skills">Skills</option><option value="interview">Interview</option><option value="other">Other</option></select></td>'+
                '<td><input name="criteria['+index+'][instructions]" class="form-control form-control-sm"></td>'+
                '<td><button type="button" class="btn btn-xs btn-outline-danger remove-criterion"><i class="fas fa-times"></i></button></td>';
            rows.appendChild(tr);
            recalc();
        });

        rows.addEventListener('click', function(e){
            var btn = e.target.closest('.remove-criterion');
            if(!btn) return;
            if(rows.querySelectorAll('tr').length <= 1) return;
            btn.closest('tr').remove();
            renumber();
            recalc();
        });
    }

    rows.addEventListener('input', function(e){
        if(e.target.classList.contains('criterion-points')) recalc();
        if(e.target.classList.contains('criterion-key')){
            e.target.value = e.target.value.replace(/\s+/g,'_');
        }
    });

    recalc();
})();
</script>
@append
