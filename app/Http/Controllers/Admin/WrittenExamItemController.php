<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\WrittenExam;
use Illuminate\Http\Request;

class WrittenExamItemController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function create(Exam $exam)
    {
        if ($exam->attempts()->exists()) {
            abort(403, 'Cannot modify items after attempts exist.');
        }
        return view('admin.assessments.items.create', compact('exam'));
    }

    public function index(Exam $exam)
    {
        $items = $exam->writtenExams()
            ->select('id','question','option_a','option_b','option_c','option_d','answer_key','attempts','status')
            ->orderByDesc('id')
            ->get();

        $hasAttempts = $exam->attempts()->exists();

        return view('admin.assessments.items.index', compact('exam', 'items', 'hasAttempts'));
    }

    public function edit(Exam $exam, WrittenExam $item)
    {
        if ($exam->attempts()->exists()) {
            abort(403, 'Cannot modify items after attempts exist.');
        }
        return view('admin.assessments.items.edit', compact('exam', 'item'));
    }

    public function update(Request $request, Exam $exam, WrittenExam $item)
    {
        if ($exam->attempts()->exists()) {
            abort(403, 'Cannot modify items after attempts exist.');
        }
        $data = $request->validate([
            'question' => 'required|string',
            'option_a' => 'required|string|max:255',
            'option_b' => 'required|string|max:255',
            'option_c' => 'required|string|max:255',
            'option_d' => 'required|string|max:255',
            'answer_key' => 'required|in:A,B,C,D,a,b,c,d',
        ]);

        $item->update([
            'question' => $data['question'],
            'option_a' => $data['option_a'],
            'option_b' => $data['option_b'],
            'option_c' => $data['option_c'],
            'option_d' => $data['option_d'],
            'answer_key' => strtoupper($data['answer_key']),
        ]);

        return redirect()
            ->route('admin.assessments.items.index', $exam)
            ->with('status', 'Item updated.');
    }

    public function destroy(Exam $exam, WrittenExam $item)
    {
        if ($exam->attempts()->exists()) {
            return redirect()
                ->route('admin.assessments.items.index', $exam)
                ->with('status', 'Cannot delete item with existing attempts.');
        }
        if ($item->attempts > 0) {
            return redirect()
                ->route('admin.assessments.items.index', $exam)
                ->with('status', 'Cannot delete item with existing attempts.');
        }

        $item->delete();

        return redirect()
            ->route('admin.assessments.items.index', $exam)
            ->with('status', 'Item deleted.');
    }

    public function store(Request $request, Exam $exam)
    {
        if ($exam->attempts()->exists()) {
            return redirect()
                ->route('admin.assessments.items.index', $exam)
                ->with('status', 'Cannot add items after attempts exist.');
        }
        $data = $request->validate([
            'question' => 'required|string',
            'option_a' => 'required|string|max:255',
            'option_b' => 'required|string|max:255',
            'option_c' => 'required|string|max:255',
            'option_d' => 'required|string|max:255',
            'answer_key' => 'required|in:A,B,C,D,a,b,c,d',
        ]);

        WrittenExam::create([
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

        return redirect()
            ->route('admin.assessments.items.index', $exam)
            ->with('status', 'Item added to written exam.');
    }

    public function import(Request $request, Exam $exam)
    {
        if ($exam->attempts()->exists()) {
            return redirect()
                ->route('admin.assessments.items.index', $exam)
                ->with('status', 'Cannot import items after attempts exist.');
        }

        $data = $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $handle = fopen($data['file']->getRealPath(), 'r');
        $created = 0;
        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            if (count($row) < 6) {
                continue;
            }
            [$question, $a, $b, $c, $d, $ans] = $row;
            $ans = strtoupper(trim($ans));
            if (!in_array($ans, ['A','B','C','D'])) {
                continue;
            }
            WrittenExam::create([
                'exam_id' => $exam->id,
                'enrollment_key' => $exam->enrollment_key,
                'question' => $question,
                'option_a' => $a,
                'option_b' => $b,
                'option_c' => $c,
                'option_d' => $d,
                'answer_key' => $ans,
                'status' => 1,
            ]);
            $created++;
        }
        fclose($handle);

        return redirect()
            ->route('admin.assessments.items.index', $exam)
            ->with('status', "Imported {$created} items.");
    }

    public function toggleStatus(Exam $exam, WrittenExam $item)
    {
        $item->update([
            'status' => $item->status == 1 ? 0 : 1,
        ]);

        return redirect()
            ->route('admin.assessments.items.index', $exam)
            ->with('status', 'Item status updated.');
    }
}
