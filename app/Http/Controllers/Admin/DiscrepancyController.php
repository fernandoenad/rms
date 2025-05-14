<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Assessment;
use App\Models\Template;

class DiscrepancyController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $assessments = Assessment::where('status', 3)
            ->where('score', 0)
            ->get();

        return view('admin.discrepancies.index',['assessments' => $assessments]);
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
