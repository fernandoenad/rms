<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Town;
use App\Models\Dropdown;
use App\Models\Vacancy;
use App\Models\Template;

class VacancyController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $vacancies = Vacancy::latest()->get();
        return view('admin.vacancies.index',['vacancies' => $vacancies]);
    }

    public function create()
    {
        $templates = Template::where('status', '=', 1)->get();

        return view('admin.vacancies.create', ['templates' => $templates]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cycle' => 'required|integer',
            'position_title' => 'required|string',
            'salary_grade' => 'required|integer',
            'base_pay' => 'required|integer',
            'office_level' => 'required|integer',
            'qualifications' => 'required|string|min:3|max:1000',
            'vacancy' => 'required|integer',
            'status' => 'required|integer',
            'template_id' => 'required|integer',
        ]);

        $data['level1_status'] = 1;
        $data['level2_status'] = 0;

        $newVacancy = Vacancy::create($data);

        return redirect(route('admin.vacancies.index'))->with('status', 'Vacancy was successfully saved.');
    }

    public function edit(Vacancy $vacancy)
    {
        $templates = Template::where('status', '=', 1)->get();

        return view('admin.vacancies.edit', ['vacancy' => $vacancy, 'templates' => $templates]);
    }

    public function update(Request $request, Vacancy $vacancy)
    {
        $data = $request->validate([
            'cycle' => 'required|integer',
            'position_title' => 'required|string',
            'salary_grade' => 'required|integer',
            'base_pay' => 'required|integer',
            'office_level' => 'required|integer',
            'qualifications' => 'required|string|min:3|max:1000',
            'vacancy' => 'required|integer',
            'status' => 'required|integer',
            'template_id' => 'required|integer',
            'level1_status' => 'required|integer',
            'level2_status' => 'required|integer',
        ]);

        $vacancy->update($data);

        return redirect(route('admin.vacancies.index'))->with('status', 'Vacancy was successfully updated.');
    }

    public function delete(Vacancy $vacancy)
    {
        return view('admin.vacancies.delete', ['vacancy' => $vacancy]);
    }

    public function destroy(Vacancy $vacancy)
    {
        $vacancy->delete();

        return redirect(route('admin.vacancies.index'))->with('status', 'Vacancy was successfully deleted.');
    }

    public function active()
    {
        $vacancies = Vacancy::orderBy('created_at', 'DESC')->get();

        return view('admin.vacancies.active',['vacancies' => $vacancies]);
    }

    public function apply(Vacancy $vacancy)
    {
        $towns = Town::all();
        $sexes = Dropdown::where('type', '=', 'sex')->get();
        $civilstatuses = Dropdown::where('type', '=', 'civilstatus')->get();

        return view('guest.vacancies.apply', ['vacancy' => $vacancy, 'towns' => $towns, 'sexes' => $sexes, 'civilstatuses' => $civilstatuses]);
    }

}
