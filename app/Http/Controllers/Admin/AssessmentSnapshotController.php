<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentSnapshot;
use Illuminate\Support\Str;

class AssessmentSnapshotController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $snapshots = AssessmentSnapshot::orderByDesc('id')->paginate(50);
        return view('admin.assessment_snapshots.index', compact('snapshots'));
    }

    public function download(AssessmentSnapshot $snapshot)
    {
        $name = Str::slug($snapshot->target_type.'-'.$snapshot->event.'-'.$snapshot->id).'.json';

        return response()->streamDownload(function () use ($snapshot) {
            echo json_encode([
                'snapshot_id'=>$snapshot->id,
                'target_type'=>$snapshot->target_type,
                'event'=>$snapshot->event,
                'created_at'=>$snapshot->created_at?->toIso8601String(),
                'snapshot'=>$snapshot->snapshot,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $name, ['Content-Type'=>'application/json']);
    }
}
