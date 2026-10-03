<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentContentBank;
use Illuminate\Http\Request;

class AssessmentContentBankController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = AssessmentContentBank::query()
            ->with('vacancy:id,position_title')
            ->orderByDesc('id');

        if ($request->filled('type')) {
            $query->where('content_type',$request->type);
        }

        if ($request->filled('status')) {
            $request->status === 'retired'
                ? $query->whereNotNull('retired_at')
                : $query->whereNull('retired_at');
        }

        if ($request->filled('q')) {
            $needle = '%'.str_replace(['%','_'],['\%','\_'],$request->q).'%';
            $query->where(function ($q) use ($needle) {
                $q->where('title','like',$needle)->orWhere('content','like',$needle);
            });
        }

        $items = $query->paginate(40)->withQueryString();

        return view('admin.assessment_bank.index', compact('items'));
    }

    public function retire(AssessmentContentBank $content)
    {
        $content->update(['retired_at'=>now()]);
        return back()->with('status','Assessment-bank content retired.');
    }

    public function restore(AssessmentContentBank $content)
    {
        $content->update(['retired_at'=>null]);
        return back()->with('status','Assessment-bank content restored.');
    }
}
