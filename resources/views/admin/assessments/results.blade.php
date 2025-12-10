@extends('adminlte::page')

@php
    $title = "Assessment Results";
    $app_name = config('app.name', '') . ' [Admin]';
@endphp

@section('title', config('app.name', '') . ' | ' . $title)

@section('content_header')
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1 class="m-0">{{ $title }}</h1>
            <small class="text-muted">Position: {{ $exam->vacancy ? $exam->vacancy->position_title : 'N/A' }}</small>
        </div>
        <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.assessments.index') }}">Assessments</a></li>
                <li class="breadcrumb-item active">Results</li>
            </ol>
        </div>
    </div>
@stop

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Attempts for {{ $exam->title }}</h3>
                        <div class="float-right">
                            <a href="{{ route('admin.assessments.items.index', $exam) }}" class="btn btn-sm btn-secondary">
                                <i class="fas fa-list"></i> Back to items
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-end mb-2">
                            {{ $attempts->links('pagination::bootstrap-4') }}
                        </div>
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Application</th>
                                    <th>Name</th>
                                    <th>Start</th>
                                    <th>End</th>
                                    <th>Score</th>
                                    <th>Note</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($attempts as $attempt)
                                    @php
                                        $app = $attempt->application;
                                        $total = $exam->writtenExams->count();
                                        $answers = $attempt->answers;
                                        $correct = 0;
                                        foreach($exam->writtenExams as $item) {
                                            $ans = $answers->firstWhere('written_exam_id', $item->id);
                                            if ($ans && strtoupper($ans->selected_option) == strtoupper($item->answer_key)) {
                                                $correct++;
                                            }
                                        }
                                        $pct = $total > 0 ? round(($correct / $total) * 100, 2) : 0;
                                    @endphp
                                    <tr>
                                        <td>{{ $app ? $app->application_code : '-' }}</td>
                                        <td>{{ $app ? $app->getFullname() : '-' }}</td>
                                        <td>{{ $attempt->started_at }}</td>
                                        <td>{{ $attempt->ended_at }}</td>
                                        <td>{{ $correct }} / {{ $total }} ({{ $pct }}%)</td>
                                        <td>
                                            @if($attempt->auto_submitted)
                                                <button type="button" class="btn btn-link p-0" data-toggle="modal" data-target="#attemptNoteModal{{ $attempt->id }}" title="View auto-submit note">
                                                    <i class="fas fa-info-circle text-info"></i>
                                                </button>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#reviewModal{{ $attempt->id }}">
                                                <i class="fas fa-eye"></i> Review
                                            </button>
                                            <form method="post" action="{{ route('admin.assessments.attempts.destroy', [$exam, $attempt]) }}" class="d-inline">
                                                @csrf
                                                @method('delete')
                                                <button type="submit" class="btn btn-sm btn-danger attempt-delete-btn" onclick="return confirm('Delete this attempt?');">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <div class="modal fade" id="reviewModal{{ $attempt->id }}" tabindex="-1" aria-labelledby="reviewModalLabel{{ $attempt->id }}" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="reviewModalLabel{{ $attempt->id }}">Review: {{ $app ? $app->application_code : '' }}</h5>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <p><strong>Score:</strong> {{ $correct }} / {{ $total }} ({{ $pct }}%)</p>
                                                    @foreach($exam->writtenExams as $idx => $item)
                                                        @php
                                                            $ans = $answers->firstWhere('written_exam_id', $item->id);
                                                            $isCorrect = $ans && strtoupper($ans->selected_option) == strtoupper($item->answer_key);
                                                        @endphp
                                                        <div class="mb-3">
                                                            <p><strong>Q{{ $idx + 1 }}.</strong> {{ $item->question }}</p>
                                                            <ul>
                                                                <li @if(strtoupper($item->answer_key)=='A') class="text-success" @endif><strong>A:</strong> {{ $item->option_a }}</li>
                                                                <li @if(strtoupper($item->answer_key)=='B') class="text-success" @endif><strong>B:</strong> {{ $item->option_b }}</li>
                                                                <li @if(strtoupper($item->answer_key)=='C') class="text-success" @endif><strong>C:</strong> {{ $item->option_c }}</li>
                                                                <li @if(strtoupper($item->answer_key)=='D') class="text-success" @endif><strong>D:</strong> {{ $item->option_d }}</li>
                                                            </ul>
                                                            <p><strong>Selected:</strong> {{ $ans ? $ans->selected_option : '-' }} | <strong>Result:</strong> {!! $isCorrect ? '<span class="text-success">Correct</span>' : '<span class="text-danger">Incorrect</span>' !!}</p>
                                                        </div>
                                                    @endforeach
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <tr>
                                        <td colspan="7">No attempts submitted yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                        <div class="mt-3">
                            {{ $attempts->links('pagination::bootstrap-4') }}
                        </div>
                    </div>
                </div>
            @foreach($attempts as $attempt)
                @if($attempt->auto_submitted)
                    @php
                        $app = $attempt->application;
                        $total = $exam->writtenExams->count();
                        $answers = $attempt->answers;
                        $correct = 0;
                        foreach($exam->writtenExams as $item) {
                            $ans = $answers->firstWhere('written_exam_id', $item->id);
                            if ($ans && strtoupper($ans->selected_option) == strtoupper($item->answer_key)) {
                                $correct++;
                            }
                        }
                        $pct = $total > 0 ? round(($correct / $total) * 100, 2) : 0;
                    @endphp
                    <div class="modal fade" id="attemptNoteModal{{ $attempt->id }}" tabindex="-1" aria-labelledby="attemptNoteLabel{{ $attempt->id }}" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="attemptNoteLabel{{ $attempt->id }}">Auto-submit details</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <p><strong>Application:</strong> {{ $app ? $app->application_code : '-' }}</p>
                                    <p><strong>Reason:</strong> {{ $attempt->auto_submit_reason ?? 'Unknown' }}</p>
                                    <p><strong>Submitted at:</strong> {{ $attempt->ended_at }}</p>
                                    <p><strong>Score:</strong> {{ $correct }} / {{ $total }} ({{ $pct }}%)</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</div>
@stop

@section('footer')
    @include('layouts.footer')
@stop

@section('plugins.Datatables', false)

@section('js')
<script>console.log('Results loaded');</script>
@stop
