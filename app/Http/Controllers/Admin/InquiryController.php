<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Inquiry;
use Yajra\DataTables\Facades\DataTables;

class InquiryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $inquiries = Inquiry::select([
                    'inquiries.*',
                    'applications.application_code'
                ])
                ->leftJoin('applications', 'inquiries.application_id', '=', 'applications.id')
                ->where('inquiries.status', '=', 1);
            
            return DataTables::of($inquiries)
                ->addColumn('application_code_link', function ($inquiry) {
                    return '<a href="' . route('admin.applications.show', $inquiry->application_id) . '" title="View">' . $inquiry->application_code . '</a>';
                })
                ->addColumn('message_snippet', function ($inquiry) {
                    return substr($inquiry->message, 0, 50) . '...';
                })
                ->filterColumn('application_code_link', function($query, $keyword) {
                    $query->where('applications.application_code', 'LIKE', "%{$keyword}%");
                })
                ->rawColumns(['application_code_link'])
                ->make(true);
        }

        return view('admin.inquiries.index');
    }
}
