<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vacancy;

class VacancyController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $vacancies = Vacancy::all();
        return view('admin.vacancies.index',['vacancies' => $vacancies]);
    }

    public function create()
    {
        return view('admin.vacancies.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cycle' => 'integer|required',
            'position_title' => 'string|required',
            'salary_grade' => 'integer|required',
            'base_pay' => 'integer|required',
            'office_level' => 'integer|required',
            'qualifications' => 'string|required|min:3|max:1000',
            'vacancy' => 'integer|required',
            'status' => 'integer|required',
        ]);

        $newVacancy = Vacancy::create($data);

        return redirect(route('admin.vacancies.index'))->with('status', 'Vacancy was successfully saved.');
    }

    public function edit(Vacancy $vacancy)
    {
        return view('admin.vacancies.edit', ['vacancy' => $vacancy]);
    }

    public function update(Request $request, Vacancy $vacancy)
    {
        $data = $request->validate([
            'cycle' => 'integer|required',
            'position_title' => 'string|required',
            'salary_grade' => 'integer|required',
            'base_pay' => 'integer|required',
            'office_level' => 'integer|required',
            'qualifications' => 'string|required|min:3|max:1000',
            'vacancy' => 'integer|required',
            'status' => 'integer|required',
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

}
