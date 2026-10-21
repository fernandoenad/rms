<?php

namespace App\Http\Controllers;

use App\Models\Vacancy;

class HomeController extends Controller
{
    public function index()
    {
        // The public landing page only advertises vacancies that are open
        // under the same posting schedule rules as the Vacancies page.
        $featuredVacancies = Vacancy::openForApplications()
            ->orderByDesc('id')
            ->limit(4)
            ->get();

        return view('guest.home', compact('featuredVacancies'));
    }
}
