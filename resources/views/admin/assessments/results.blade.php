@extends('adminlte::page')
@section('title','Written Exam Results')
@section('content_header')<h1>{{ $exam->title }} — Results</h1>@stop
@section('content')
@if(session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif
<div class="card"><div class="card-body table-responsive p-0">
<table class="table table-hover">
<thead><tr><th>Applicant</th><th>Start</th><th>End</th><th>Score</th><th>Integrity events</th><th>Review</th></tr></thead>
<tbody>
@forelse($attempts as $attempt)
@php
    $hiddenCount=$attempt->events->where('event_type','tab_hidden')->count();
@endphp
<tr>
<td>{{ optional($attempt->application)->application_code }}<br><small>{{ optional($attempt->application)->getFullname() }}</small></td>
<td>{{ $attempt->started_at }}</td>
<td>{{ $attempt->ended_at }}</td>
<td>{{ $attempt->correct_answers ?? '-' }} / {{ $attempt->total_items ?? '-' }} @if($attempt->percentage!==null) ({{ $attempt->percentage }}%) @endif</td>
<td>{{ $hiddenCount }} tab/app switch{{ $hiddenCount==1?'':'es' }}</td>
<td><button class="btn btn-sm btn-info" data-toggle="modal" data-target="#review{{ $attempt->id }}">Review</button></td>
</tr>
<div class="modal fade" id="review{{ $attempt->id }}" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title">{{ optional($attempt->application)->application_code }} — exact attempt review</h5><button class="close" data-dismiss="modal">&times;</button></div>
<div class="modal-body">
@php
    $questionOrder=json_decode((string)$attempt->question_order,true) ?: $exam->writtenExams->pluck('id')->all();
    $orderedItems=$exam->writtenExams->sortBy(function($item) use($questionOrder){$p=array_search($item->id,$questionOrder,true);return $p===false?PHP_INT_MAX:$p;})->values();
    $orders=$attempt->itemOrders->keyBy('written_exam_id');
@endphp
@foreach($orderedItems as $idx=>$item)
@php
    $answer=$attempt->answers->firstWhere('written_exam_id',$item->id);
    $storedOrder=optional($orders->get($item->id))->option_order ?: $item->options->pluck('id')->all();
    $display=$item->options->sortBy(function($o) use($storedOrder){$p=array_search($o->id,$storedOrder,true);return $p===false?PHP_INT_MAX:$p;})->values();
@endphp
<div class="border-bottom pb-3 mb-3">
<p><strong>{{ $idx+1 }}.</strong> {{ $item->question }}</p>
@foreach($display as $oi=>$option)
@php $selected=$answer && (int)$answer->selected_option_id===(int)$option->id; @endphp
<div class="{{ $option->is_correct?'text-success font-weight-bold':'' }} {{ $selected?'border rounded p-1':'' }}">
<strong>{{ chr(65+$oi) }}.</strong> {{ $option->option_text }}
@if($selected)<span class="badge badge-primary">Selected</span>@endif
@if($option->is_correct)<span class="badge badge-success">Correct</span>@endif
</div>
@endforeach
@if($answer && !$answer->selected_option_id)<small class="text-muted">Legacy saved answer: {{ $answer->selected_option }}</small>@endif
</div>
@endforeach
<details><summary>Audit events</summary><pre class="small">{{ $attempt->events->map(fn($e)=>[$e->event_at?->toIso8601String(),$e->event_type])->toJson(JSON_PRETTY_PRINT) }}</pre></details>
</div>
<div class="modal-footer"><button class="btn btn-secondary" data-dismiss="modal">Close</button></div>
</div></div></div>
@empty<tr><td colspan="6">No submitted attempts yet.</td></tr>@endforelse
</tbody></table></div></div>
{{ $attempts->links('pagination::bootstrap-4') }}
@stop
