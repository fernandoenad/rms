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
<div class="form-row">
<div class="form-group col-md-2"><label>Items</label><input type="number" min="1" max="100" name="count" value="20" class="form-control" required></div>
<div class="form-group col-md-2"><label>Unistructural %</label><input type="number" min="0" max="100" name="solo_unistructural" value="10" class="form-control" required></div>
<div class="form-group col-md-2"><label>Multistructural %</label><input type="number" min="0" max="100" name="solo_multistructural" value="20" class="form-control" required></div>
<div class="form-group col-md-2"><label>Relational %</label><input type="number" min="0" max="100" name="solo_relational" value="45" class="form-control" required></div>
<div class="form-group col-md-2"><label>Extended Abstract %</label><input type="number" min="0" max="100" name="solo_extended_abstract" value="25" class="form-control" required></div>
<div class="form-group col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-block" onclick="return confirm('Generate AI items from this position\\'s qualifications and job details?');">Generate</button></div>
</div>
<small class="text-muted">Generation follows SOLO abstraction levels, contextual stem+question construction, single-best-answer rules, and similar option length. Generated items remain reviewable before use.</small>
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
