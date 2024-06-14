<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Office;
use App\Models\Application;

class VacancyReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $offices = Office::all();

        $applications = Application::join('vacancies', 'vacancies.id', '=', 'applications.vacancy_id')
            ->select('applications.*')
            ->get();

        $src_p = Application::join('vacancies', 'vacancies.id', '=', 'applications.vacancy_id')
            ->join('assessments', 'assessments.application_id', '=', 'applications.id')
            ->where('assessments.status', '=', 1)
            ->distinct('applications.id')
            ->get()->count();
        
        $src_c = Application::join('vacancies', 'vacancies.id', '=', 'applications.vacancy_id')
            ->join('assessments', 'assessments.application_id', '=', 'applications.id')
            ->where('assessments.status', '=', 2)
            ->distinct('applications.id')
            ->get()->count();
        
        $drc_c = Application::join('vacancies', 'vacancies.id', '=', 'applications.vacancy_id')
            ->join('assessments', 'assessments.application_id', '=', 'applications.id')
            ->where('assessments.status', '=', 3)
            ->distinct('applications.id')
            ->get()->count();

        return view('admin.vacancies.reports.index', ['offices' => $offices, 'applications' => $applications, 'src_p' => $src_p, 'src_c' => $src_c, 'drc_c' => $drc_c]);
    }
}
