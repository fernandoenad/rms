<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Assessment;
use App\Models\Template;
use Yajra\DataTables\Facades\DataTables;

class DiscrepancyController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $assessments = Assessment::with(['application.vacancy'])
                ->select([
                    'assessments.*',
                    'applications.application_code',
                    'applications.first_name',
                    'applications.last_name',
                    'applications.middle_name',
                    'vacancies.position_title'
                ])
                ->leftJoin('applications', 'assessments.application_id', '=', 'applications.id')
                ->leftJoin('vacancies', 'applications.vacancy_id', '=', 'vacancies.id')
                ->where('assessments.status', 3)
                ->where('assessments.score', 0);
            
            return DataTables::of($assessments)
                ->addColumn('code_position', function ($assessment) {
                    return $assessment->application_code . ' | <small>' . $assessment->position_title . '</small>';
                })
                ->addColumn('fullname', function ($assessment) {
                    $middleInitial = $assessment->middle_name ? substr($assessment->middle_name, 0, 1) : '';
                    return $assessment->last_name . ', ' . $assessment->first_name . ' ' . $middleInitial;
                })
                ->addColumn('status_label', function ($assessment) {
                    return $assessment->get_status();
                })
                ->addColumn('raw_score', function ($assessment) {
                    $assessment_scores = json_decode($assessment->assessment, true) ?? [];
                    $total_points = 0;
                    foreach ($assessment_scores as $value) {
                        $total_points += is_numeric($value) ? $value : 0;
                    }
                    return $total_points;
                })
                ->addColumn('action', function ($assessment) {
                    return '<a href="' . route('admin.discrepancies.modify', $assessment->id) . '" class="btn btn-sm btn-primary" title="Modify"><span class="fas primary fa-fw fa-edit"></span></a>';
                })
                ->filterColumn('code_position', function($query, $keyword) {
                    $query->where(function($q) use ($keyword) {
                        $q->where('applications.application_code', 'LIKE', "%{$keyword}%")
                          ->orWhere('vacancies.position_title', 'LIKE', "%{$keyword}%");
                    });
                })
                ->filterColumn('fullname', function($query, $keyword) {
                    $query->where(function($q) use ($keyword) {
                        $q->where('applications.first_name', 'LIKE', "%{$keyword}%")
                          ->orWhere('applications.last_name', 'LIKE', "%{$keyword}%")
                          ->orWhere('applications.middle_name', 'LIKE', "%{$keyword}%");
                    });
                })
                ->rawColumns(['code_position', 'action'])
                ->make(true);
        }

        return view('admin.discrepancies.index');
    }

    public function modify(Assessment $assessment)
    {
        return view('admin.discrepancies.edit_scores',['assessment' => $assessment]);
    }

    public function update(Request $request, Assessment $assessment)
    {
        $template = Template::find($assessment->application->vacancy->template_id);
        $criteria = json_decode($template->template, true);
        $template_details = $criteria;
        $keys = array_keys($template_details);

        $formData = $request->all();

        $totalPoints = 0;

        foreach($request->except('_token') as $key => $value) {
            if (is_numeric($value)) {
                $totalPoints += (float)$value;
            }
        }

        $filteredFormData = array_intersect_key($formData, array_flip($keys));

        $assessment->update(['assessment' => json_encode($filteredFormData), 'score' => $totalPoints]); 

        return view('admin.discrepancies.edit_scores',['assessment' => $assessment]);
    }
}
