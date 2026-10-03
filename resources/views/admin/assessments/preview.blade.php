@extends('adminlte::page')
@section('title','Written Assessment Preview')
@section('content_header')
<div class="d-flex justify-content-between align-items-center">
    <div><h1 class="mb-0">{{ $exam->title }} — Preview</h1><small class="text-muted">Applicant-facing content preview and deployment dry-run</small></div>
    <a href="{{ route('admin.assessments.edit',$exam) }}" class="btn btn-outline-secondary">Back</a>
</div>
@stop
@section('content')
<div class="row">
    <div class="col-md-6">
        <div class="card card-outline {{ $readiness['ready']?'card-success':'card-warning' }}">
            <div class="card-header"><strong>Assessment Readiness</strong></div>
            <div class="card-body">
                @if($readiness['ready'])<span class="badge badge-success">Assessment content ready</span>
                @else<ul class="mb-0">@foreach($readiness['issues'] as $issue)<li>{{ $issue }}</li>@endforeach</ul>@endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card card-outline {{ $infrastructure['ready']?'card-success':'card-danger' }}">
            <div class="card-header"><strong>Infrastructure Dry Run</strong></div>
            <div class="card-body">
                <div>Queue: <strong>{{ $infrastructure['queue_connection'] }}</strong></div>
                <div>Scheduler: <strong>{{ optional($infrastructure['scheduler_heartbeat'])->diffForHumans() ?: 'No heartbeat' }}</strong></div>
                @foreach($infrastructure['issues'] as $issue)<div class="text-danger small">• {{ $issue }}</div>@endforeach
                @foreach($infrastructure['warnings'] as $warning)<div class="text-warning small">• {{ $warning }}</div>@endforeach
            </div>
        </div>
    </div>
</div>
<div class="card">
    <div class="card-header"><strong>Applicant Preview</strong> <span class="float-right">{{ $exam->duration }} minutes</span></div>
    <div class="card-body">
        @foreach($exam->writtenExams->where('status',1) as $item)
            <div class="border rounded p-3 mb-3">
                <div class="d-flex align-items-start">
    <strong class="mr-1">{{ $loop->iteration }}.</strong>
    <strong class="written-stem">{{ $item->question }}</strong>
</div>
                <div class="mt-2">
                    @foreach($item->options as $option)
                        <div class="custom-control custom-radio">
                            <input type="radio" disabled class="custom-control-input" id="preview_{{ $item->id }}_{{ $option->id }}">
                            <label class="custom-control-label" for="preview_{{ $item->id }}_{{ $option->id }}">{{ $option->option_text }}</label>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
        @if($exam->writtenExams->where('status',1)->isEmpty())<div class="text-muted">No active items.</div>@endif
    </div>
</div>
@stop

@section('css')
<style>
.written-stem { white-space: pre-line; display:block; line-height:1.55; }
</style>
@stop
