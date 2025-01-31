<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Office;
use App\Models\Application;
use App\Models\Station;
use App\Models\Vacancy;

class ApplicationReportController extends Controller
{
    public function index()
    {
        $offices = Office::orderBy('name', 'ASC')->get();
        $cycle = Vacancy::latest()->first()->cycle;

        $applications = Application::join('vacancies', 'vacancies.id', '=', 'applications.vacancy_id')
            ->where('vacancies.cycle', $cycle)
            ->select('applications.*')
            ->get();

        $src_p = Application::join('vacancies', 'vacancies.id', '=', 'applications.vacancy_id')
            ->join('assessments', 'assessments.application_id', '=', 'applications.id')
            ->where('assessments.status', '=', 1)
            ->where('vacancies.cycle', $cycle)
            ->distinct('applications.id')
            ->get()->count();
        
        $src_c = Application::join('vacancies', 'vacancies.id', '=', 'applications.vacancy_id')
            ->join('assessments', 'assessments.application_id', '=', 'applications.id')
            ->where('assessments.status', '>=', 2)
            ->where('vacancies.cycle', $cycle)
            ->distinct('applications.id')
            ->get()->count();
        
        $drc_p = Application::join('vacancies', 'vacancies.id', '=', 'applications.vacancy_id')
            ->join('assessments', 'assessments.application_id', '=', 'applications.id')
            ->where('assessments.status', '=', 2)
            ->where('vacancies.cycle', $cycle)
            ->distinct('applications.id')
            ->get()->count();
        
        $drc_c = Application::join('vacancies', 'vacancies.id', '=', 'applications.vacancy_id')
            ->join('assessments', 'assessments.application_id', '=', 'applications.id')
            ->where('assessments.status', '>=', 3)
            ->where('vacancies.cycle', $cycle)
            ->distinct('applications.id')
            ->get()->count();

        return view('guest.reports.index', ['offices' => $offices, 'applications' => $applications, 'src_p' => $src_p, 'src_c' => $src_c, 'drc_p' => $drc_p, 'drc_c' => $drc_c, 'cycle' => $cycle]);
    }

    public function show(Office $office)
    {
        $applications = Application::join('vacancies', 'vacancies.id', '=', 'applications.vacancy_id')
            ->select('applications.*')
            ->get();
        $cycle = Vacancy::latest()->first()->cycle;

        $stations = Station::where('office_id', '=', $office->id)->pluck('id');

        $src_t = Application::join('vacancies', 'vacancies.id', '=', 'applications.vacancy_id')
            ->where('vacancies.cycle', $cycle)
            ->whereIn('station_id', $stations)->count();
        $src_p = Application::join('vacancies', 'vacancies.id', '=', 'applications.vacancy_id')
            ->join('assessments', 'assessments.application_id', '=', 'applications.id')
            ->whereIn('applications.station_id', $stations)
            ->where('assessments.status', '=', 1)
            ->where('vacancies.cycle', $cycle)
            ->distinct('applications.id') // Ensure distinct applications are counted
            ->count('applications.id');
        $src_c = Application::join('vacancies', 'vacancies.id', '=', 'applications.vacancy_id')
            ->join('assessments', 'assessments.application_id', '=', 'applications.id')
            ->whereIn('applications.station_id', $stations)
            ->where('assessments.status', '>=', 2)
            ->where('vacancies.cycle', $cycle)
            ->distinct('applications.id') // Ensure distinct applications are counted
            ->count('applications.id');
        $drc_p = Application::join('vacancies', 'vacancies.id', '=', 'applications.vacancy_id')
            ->join('assessments', 'assessments.application_id', '=', 'applications.id')
            ->whereIn('applications.station_id', $stations)
            ->where('assessments.status', '=', 2)
            ->where('vacancies.cycle', $cycle)
            ->distinct('applications.id') // Ensure distinct applications are counted
            ->count('applications.id');
        $drc_c = Application::join('vacancies', 'vacancies.id', '=', 'applications.vacancy_id')
            ->join('assessments', 'assessments.application_id', '=', 'applications.id')
            ->whereIn('applications.station_id', $stations)
            ->where('assessments.status', '>=', 3)
            ->where('vacancies.cycle', $cycle)
            ->distinct('applications.id') // Ensure distinct applications are counted
            ->count('applications.id');
        
        $stations = Station::where('office_id', '=', $office->id)
            ->orderBy('services', 'ASC')
            ->orderBy('name', 'ASC')->get();

        return view('guest.reports.show', ['office' => $office, 'applications' => $applications, 'stations' => $stations, 'src_t' => $src_t, 'src_p' => $src_p, 'src_c' => $src_c, 'drc_c' => $drc_c, 'cycle' => $cycle]);
    }
}
