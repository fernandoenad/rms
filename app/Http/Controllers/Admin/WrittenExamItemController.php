<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAiGenerationRun;
use App\Models\Exam;
use App\Models\WrittenExam;
use App\Models\WrittenExamOption;
use Illuminate\Http\Request;
use App\Services\AssessmentGovernanceService;
use Illuminate\Support\Facades\DB;

class WrittenExamItemController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    protected function ensureMutable(Exam $exam): void
    {
        if ($exam->archived_at) {
            abort(403, 'Archived assessments are frozen and cannot be modified.');
        }

        if ((int) $exam->status === 1) {
            abort(403, 'Published sets are immutable. Return the set to draft before changing items.');
        }

        if ($exam->attempts()->whereNotNull('started_at')->exists()) {
            abort(403, 'Cannot modify exam items after attempts exist. Create a new exam set/version instead.');
        }
    }

    protected function syncOptions(WrittenExam $item, array $data): void
    {
        $answerKey = strtoupper($data['answer_key']);

        foreach (['A','B','C','D'] as $position) {
            $sourcePosition = ord($position) - 64;
            WrittenExamOption::updateOrCreate(
                ['written_exam_id' => $item->id, 'source_position' => $sourcePosition],
                [
                    'option_text' => $data['option_' . strtolower($position)],
                    'is_correct' => $answerKey === $position,
                ]
            );
        }

        $item->options()->whereNotIn('source_position', [1,2,3,4])->delete();
    }

    public function create(Exam $exam)
    {
        $this->ensureMutable($exam);
        return view('admin.assessments.items.create', compact('exam'));
    }

    public function index(Exam $exam, AssessmentGovernanceService $governance)
    {
        $items = $exam->writtenExams()
            ->with('options')
            ->select(
                'id',
                'exam_id',
                'question',
                'option_a',
                'option_b',
                'option_c',
                'option_d',
                'answer_key',
                'attempts',
                'status',
                'solo_level',
                'difficulty',
                'competency_basis',
                'rationale',
                'ai_generated',
                'item_version',
                'review_status',
                'review_notes',
                'reviewed_at'
            )
            ->orderByDesc('id')
            ->get();

        $hasAttempts = $exam->attempts()->whereNotNull('started_at')->exists();
        $readiness = $governance->readiness($exam);
        $generationRuns = AssessmentAiGenerationRun::where('exam_id', $exam->id)
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return view('admin.assessments.items.index', compact(
            'exam',
            'items',
            'hasAttempts',
            'readiness',
            'generationRuns'
        ));
    }

    public function generationStatus(Exam $exam, AssessmentGovernanceService $governance)
    {
        $runs = AssessmentAiGenerationRun::where('exam_id', $exam->id)
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn ($run) => [
                'id'=>$run->id,
                'requested_count'=>(int)$run->requested_count,
                'generated_count'=>(int)$run->generated_count,
                'completed_batches'=>(int)$run->completed_batches,
                'batch_count'=>(int)$run->batch_count,
                'failed_batches'=>(int)$run->failed_batches,
                'status'=>$run->status,
                'last_error'=>$run->last_error,
                'updated_at'=>$run->updated_at?->format('M d, h:i:s A'),
            ])
            ->values();

        $readiness = $governance->readiness($exam->fresh());

        return response()->json([
            'runs'=>$runs,
            'readiness'=>[
                'ready'=>(bool)$readiness['ready'],
                'item_count'=>(int)($readiness['item_count'] ?? 0),
                'issues'=>array_values($readiness['issues'] ?? []),
            ],
        ]);
    }

    public function edit(Exam $exam, WrittenExam $item)
    {
        $this->ensureMutable($exam);
        abort_unless((int) $item->exam_id === (int) $exam->id, 404);
        return view('admin.assessments.items.edit', compact('exam', 'item'));
    }

    public function update(
        Request $request,
        Exam $exam,
        WrittenExam $item,
        AssessmentGovernanceService $governance
    ) {
        $this->ensureMutable($exam);
        abort_unless((int) $item->exam_id === (int) $exam->id, 404);

        $data = $request->validate([
            'question' => 'required|string',
            'option_a' => 'required|string|max:1000',
            'option_b' => 'required|string|max:1000',
            'option_c' => 'required|string|max:1000',
            'option_d' => 'required|string|max:1000',
            'answer_key' => 'required|in:A,B,C,D,a,b,c,d',
        ]);

        $newItem = DB::transaction(function () use ($item, $data, $exam) {
            $item->update(['status' => 0]);

            $newItem = $item->replicate();
            $newItem->exam_id = $exam->id;
            $newItem->question = $data['question'];
            $newItem->option_a = $data['option_a'];
            $newItem->option_b = $data['option_b'];
            $newItem->option_c = $data['option_c'];
            $newItem->option_d = $data['option_d'];
            $newItem->answer_key = strtoupper($data['answer_key']);
            $newItem->item_version = ((int) $item->item_version) + 1;
            $newItem->supersedes_item_id = $item->id;
            $newItem->review_status = 'pending_review';
            $newItem->reviewed_by = null;
            $newItem->reviewed_at = null;
            $newItem->review_notes = null;
            $newItem->status = 1;
            $newItem->save();

            $this->syncOptions($newItem, $data);

            return $newItem;
        });

        $governance->log('item_version_created', [
            'assessment_group_id' => $exam->assessment_group_id,
            'exam_id' => $exam->id,
            'written_exam_id' => $newItem->id,
        ], [
            'supersedes_item_id' => $item->id,
            'version' => $newItem->item_version,
        ]);

        return redirect()->route('admin.assessments.items.index', $exam)
            ->with('status', "Item revised as version {$newItem->item_version}. Review and approve the new version before publishing.");
    }

    public function destroy(Exam $exam, WrittenExam $item)
    {
        $this->ensureMutable($exam);
        abort_unless((int) $item->exam_id === (int) $exam->id, 404);
        $item->delete();

        return redirect()->route('admin.assessments.items.index', $exam)
            ->with('status', 'Item deleted.');
    }

    public function store(
        Request $request,
        Exam $exam,
        AssessmentGovernanceService $governance
    ) {
        $this->ensureMutable($exam);

        $data = $request->validate([
            'question' => 'required|string',
            'option_a' => 'required|string|max:1000',
            'option_b' => 'required|string|max:1000',
            'option_c' => 'required|string|max:1000',
            'option_d' => 'required|string|max:1000',
            'answer_key' => 'required|in:A,B,C,D,a,b,c,d',
        ]);

        $item = DB::transaction(function () use ($exam, $data) {
            $item = WrittenExam::create([
                'exam_id' => $exam->id,
                'enrollment_key' => $exam->enrollment_key,
                'question' => $data['question'],
                'option_a' => $data['option_a'],
                'option_b' => $data['option_b'],
                'option_c' => $data['option_c'],
                'option_d' => $data['option_d'],
                'answer_key' => strtoupper($data['answer_key']),
                'review_status' => 'approved',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'status' => 1,
            ]);
            $this->syncOptions($item, $data);
            return $item;
        });

        $governance->syncWrittenItemToBank($item);

        $governance->log('item_created', [
            'assessment_group_id' => $exam->assessment_group_id,
            'exam_id' => $exam->id,
            'written_exam_id' => $item->id,
        ]);

        return redirect()->route('admin.assessments.items.index', $exam)
            ->with('status', 'Item added to written exam.');
    }

    public function import(
        Request $request,
        Exam $exam,
        AssessmentGovernanceService $governance
    ) {
        $this->ensureMutable($exam);

        $data = $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $handle = fopen($data['file']->getRealPath(), 'r');
        $created = 0;

        DB::transaction(function () use ($handle, $exam, &$created) {
            while (($row = fgetcsv($handle, 5000, ',')) !== false) {
                if (count($row) < 6) continue;

                [$question, $a, $b, $c, $d, $answer] = $row;
                $answer = strtoupper(trim($answer));
                if (!in_array($answer, ['A','B','C','D'], true)) continue;

                $payload = [
                    'question' => trim($question),
                    'option_a' => trim($a),
                    'option_b' => trim($b),
                    'option_c' => trim($c),
                    'option_d' => trim($d),
                    'answer_key' => $answer,
                ];

                if ($payload['question'] === '') continue;

                $item = WrittenExam::create([
                    'exam_id' => $exam->id,
                    'enrollment_key' => $exam->enrollment_key,
                    ...$payload,
                    'review_status' => 'approved',
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                    'status' => 1,
                ]);
                $this->syncOptions($item, $payload);
                $created++;
            }
        });

        fclose($handle);

        $governance->log('items_imported', [
            'assessment_group_id' => $exam->assessment_group_id,
            'exam_id' => $exam->id,
        ], ['created' => $created]);

        return redirect()->route('admin.assessments.items.index', $exam)
            ->with('status', "Imported {$created} items.");
    }

    public function review(
        Request $request,
        Exam $exam,
        WrittenExam $item,
        AssessmentGovernanceService $governance
    ) {
        $this->ensureMutable($exam);
        abort_unless((int) $item->exam_id === (int) $exam->id, 404);

        $data = $request->validate([
            'decision' => 'required|in:approved,rejected,pending_review',
            'review_notes' => 'nullable|string|max:5000',
        ]);

        $item->update([
            'review_status' => $data['decision'],
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_notes' => $data['review_notes'] ?? null,
            'status' => $data['decision'] === 'rejected' ? 0 : $item->status,
        ]);

        if ($data['decision'] === 'approved') {
            $governance->syncWrittenItemToBank($item->fresh());
        }

        $governance->log('item_reviewed', [
            'assessment_group_id' => $exam->assessment_group_id,
            'exam_id' => $exam->id,
            'written_exam_id' => $item->id,
        ], [
            'decision' => $data['decision'],
            'version' => $item->item_version,
        ]);

        return back()->with('status', 'Item review decision saved.');
    }

    public function toggleStatus(Exam $exam, WrittenExam $item)
    {
        $this->ensureMutable($exam);
        abort_unless((int) $item->exam_id === (int) $exam->id, 404);

        $item->update(['status' => $item->status == 1 ? 0 : 1]);

        return redirect()->route('admin.assessments.items.index', $exam)
            ->with('status', 'Item status updated.');
    }
}
