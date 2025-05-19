<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vacancy;
use App\Models\Assessment;
use App\Models\Office;

class RQAController extends Controller
{
    public function index()
    {
        $vacancies = Vacancy::latest()->get();

        return view('guest.rqas.index',['vacancies' => $vacancies]);
    }

    public function show(Vacancy $vacancy)
    {
        if(strpos($vacancy->position_title, 'Elementary for SPIMS') !== false  || strpos($vacancy->position_title, 'Secondary for SPIMS') !== false) {
            $assessments = Assessment::join('applications', 'assessments.application_id', '=', 'applications.id')
                ->join('hrms.stations', 'applications.station_id', '=', 'stations.id')
                ->where('applications.vacancy_id', '=', $vacancy->id)
                ->where('assessments.status', '=', 3)
                ->orderBy('assessments.score', 'DESC')
                ->select('stations.name', 'stations.code', 'assessments.*', 'applications.*')
                ->get();

            return view('guest.rqas.carview3', ['vacancy' => $vacancy, 'assessments' => $assessments]);
        

        } else if(strpos($vacancy->position_title, 'Elementary') !== false || strpos($vacancy->position_title, 'Kindergarten') !== false) {
            $offices = Office::all();

            return view('guest.rqas.carview2', ['vacancy' => $vacancy, 'offices' => $offices]);

        } else {
            $assessments = Assessment::join('applications', 'assessments.application_id', '=', 'applications.id')
                ->join('hrms.stations', 'applications.station_id', '=', 'stations.id')
                ->where('applications.vacancy_id', '=', $vacancy->id)
                ->where('assessments.status', '=', 3)
                ->where('assessments.score', '>=', 50)
                ->orderBy('assessments.score', 'DESC')
                ->select('stations.name', 'stations.code', 'assessments.*', 'applications.*')
                ->get();

            return view('guest.rqas.carview', ['vacancy' => $vacancy, 'assessments' => $assessments]);
        }


    }
}
