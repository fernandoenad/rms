<?php

namespace App\Http\Controllers\Guest;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Vacancy;
use App\Models\Town;
use App\Models\Dropdown;
use Carbon\Carbon;

class VacancyController extends Controller
{
    public function index()
    {
        $vacancies = Vacancy::openForApplications()
            ->orderByDesc('id')
            ->get();

        return view('guest.vacancies.index', ['vacancies' => $vacancies]);
    }

    public function show(Vacancy $vacancy)
    {
        if (!$vacancy->isOpenForApplications()) {
            abort(404);
        }

        return view('guest.vacancies.show', ['vacancy' => $vacancy]);
    }

    public function apply(Vacancy $vacancy)
    {
        if(!$vacancy->isOpenForApplications()){
            abort(404);
        }

        $towns = Town::all();
        $sexes = Dropdown::where('type', '=', 'sex')->get();
        $civilstatuses = Dropdown::where('type', '=', 'civilstatus')->get();

        return view('guest.vacancies.apply', ['vacancy' => $vacancy, 'towns' => $towns, 'sexes' => $sexes, 'civilstatuses' => $civilstatuses]);
    }
}
