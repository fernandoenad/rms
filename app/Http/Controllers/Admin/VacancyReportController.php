<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Office;
use App\Models\Application;
use App\Models\Station;

class VacancyReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $offices = Office::orderBy('name', 'ASC')->get();

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


    public function show(Office $office)
    {
        $applications = Application::join('vacancies', 'vacancies.id', '=', 'applications.vacancy_id')
            ->select('applications.*')
            ->get();

        $stations = Station::where('office_id', '=', $office->id)->pluck('id');
        $src_t = Application::whereIn('station_id', $stations)->count();
        $src_p = Application::join('assessments', 'assessments.application_id', '=', 'applications.id')
            ->whereIn('applications.station_id', $stations)
            ->where('assessments.status', '=', 1)
            ->distinct('applications.id') // Ensure distinct applications are counted
            ->count('applications.id');
        $src_c = Application::join('assessments', 'assessments.application_id', '=', 'applications.id')
            ->whereIn('applications.station_id', $stations)
            ->where('assessments.status', '=', 2)
            ->distinct('applications.id') // Ensure distinct applications are counted
            ->count('applications.id');
        $drc_p = Application::join('assessments', 'assessments.application_id', '=', 'applications.id')
            ->whereIn('applications.station_id', $stations)
            ->where('assessments.status', '=', 2)
            ->distinct('applications.id') // Ensure distinct applications are counted
            ->count('applications.id');
        $drc_c = Application::join('assessments', 'assessments.application_id', '=', 'applications.id')
            ->whereIn('applications.station_id', $stations)
            ->where('assessments.status', '=', 3)
            ->distinct('applications.id') // Ensure distinct applications are counted
            ->count('applications.id');
        
        $stations = Station::where('office_id', '=', $office->id)
            ->orderBy('services', 'ASC')
            ->orderBy('name', 'ASC')->get();

        return view('admin.vacancies.reports.show', ['office' => $office, 'applications' => $applications, 'stations' => $stations, 'src_t' => $src_t, 'src_p' => $src_p, 'src_c' => $src_c, 'drc_c' => $drc_c]);
    }
}
