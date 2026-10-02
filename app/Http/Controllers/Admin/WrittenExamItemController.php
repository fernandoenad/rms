<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\WrittenExam;
use App\Models\WrittenExamOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WrittenExamItemController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    protected function ensureMutable(Exam $exam): void
    {
        if ($exam->attempts()->exists()) {
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

    public function index(Exam $exam)
    {
        $items = $exam->writtenExams()
            ->with('options')
            ->select('id','question','option_a','option_b','option_c','option_d','answer_key','attempts','status')
            ->orderByDesc('id')
            ->get();

        $hasAttempts = $exam->attempts()->exists();

        return view('admin.assessments.items.index', compact('exam', 'items', 'hasAttempts'));
    }

    public function edit(Exam $exam, WrittenExam $item)
    {
        $this->ensureMutable($exam);
        abort_unless((int) $item->exam_id === (int) $exam->id, 404);
        return view('admin.assessments.items.edit', compact('exam', 'item'));
    }

    public function update(Request $request, Exam $exam, WrittenExam $item)
    {
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

        DB::transaction(function () use ($item, $data) {
            $item->update([
                'question' => $data['question'],
                'option_a' => $data['option_a'],
                'option_b' => $data['option_b'],
                'option_c' => $data['option_c'],
                'option_d' => $data['option_d'],
                'answer_key' => strtoupper($data['answer_key']),
            ]);
            $this->syncOptions($item, $data);
        });

        return redirect()->route('admin.assessments.items.index', $exam)
            ->with('status', 'Item updated.');
    }

    public function destroy(Exam $exam, WrittenExam $item)
    {
        $this->ensureMutable($exam);
        abort_unless((int) $item->exam_id === (int) $exam->id, 404);
        $item->delete();

        return redirect()->route('admin.assessments.items.index', $exam)
            ->with('status', 'Item deleted.');
    }

    public function store(Request $request, Exam $exam)
    {
        $this->ensureMutable($exam);

        $data = $request->validate([
            'question' => 'required|string',
            'option_a' => 'required|string|max:1000',
            'option_b' => 'required|string|max:1000',
            'option_c' => 'required|string|max:1000',
            'option_d' => 'required|string|max:1000',
            'answer_key' => 'required|in:A,B,C,D,a,b,c,d',
        ]);

        DB::transaction(function () use ($exam, $data) {
            $item = WrittenExam::create([
                'exam_id' => $exam->id,
                'enrollment_key' => $exam->enrollment_key,
                'question' => $data['question'],
                'option_a' => $data['option_a'],
                'option_b' => $data['option_b'],
                'option_c' => $data['option_c'],
                'option_d' => $data['option_d'],
                'answer_key' => strtoupper($data['answer_key']),
                'status' => 1,
            ]);
            $this->syncOptions($item, $data);
        });

        return redirect()->route('admin.assessments.items.index', $exam)
            ->with('status', 'Item added to written exam.');
    }

    public function import(Request $request, Exam $exam)
    {
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
                    'status' => 1,
                ]);
                $this->syncOptions($item, $payload);
                $created++;
            }
        });

        fclose($handle);

        return redirect()->route('admin.assessments.items.index', $exam)
            ->with('status', "Imported {$created} items.");
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
