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
        $search = request('q');
        $state = request('state');

        $vacancies = Vacancy::query()
            ->select([
                'id','cycle','position_title','salary_grade','office_level','vacancy',
                'status','posting_start_at','posting_end_at','level1_status','level2_status'
            ])
            ->withCount([
                'applications', // total applications
                'applications as applications_with_station_count' => function ($q) {
                    $q->where('station_id', '>', 0);
                },
            ])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('position_title', 'like', '%' . $search . '%')
                        ->orWhere('cycle', 'like', '%' . $search . '%');
                });
            })
            ->when($state, function ($q) use ($state) {
                $now = now();

                if ($state === 'draft') {
                    $q->where('status', 0);
                } elseif ($state === 'scheduled') {
                    $q->where('status', 1)->whereNotNull('posting_start_at')->where('posting_start_at', '>', $now);
                } elseif ($state === 'open') {
                    $q->where('status', 1)
                        ->where(fn ($w) => $w->whereNull('posting_start_at')->orWhere('posting_start_at', '<=', $now))
                        ->where(fn ($w) => $w->whereNull('posting_end_at')->orWhere('posting_end_at', '>', $now));
                } elseif ($state === 'closed') {
                    $q->where('status', 1)->whereNotNull('posting_end_at')->where('posting_end_at', '<=', $now);
                }
            })
            ->orderByDesc('id')
            ->simplePaginate(15)
            ->withQueryString();

        return view('admin.vacancies.index', compact('vacancies', 'search', 'state'));
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
            'status' => 'required|integer|in:0,1',
            'posting_start_at' => 'nullable|date',
            'posting_end_at' => 'nullable|date',
            'template_id' => 'required|integer',
        ]);

        if (!empty($data['posting_start_at']) && !empty($data['posting_end_at']) && strtotime($data['posting_end_at']) <= strtotime($data['posting_start_at'])) {
            return back()->withErrors(['posting_end_at' => 'Applications Close must be later than Applications Open.'])->withInput();
        }

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
            'status' => 'required|integer|in:0,1',
            'posting_start_at' => 'nullable|date',
            'posting_end_at' => 'nullable|date|after:posting_start_at',
            'template_id' => 'required|integer',
            'level1_status' => 'required|integer|in:0,1,2',
            'level2_status' => 'required|integer|in:0,1,2,3',
        ]);

        if (!empty($data['posting_start_at']) && !empty($data['posting_end_at']) && strtotime($data['posting_end_at']) <= strtotime($data['posting_start_at'])) {
            return back()->withErrors(['posting_end_at' => 'Applications Close must be later than Applications Open.'])->withInput();
        }

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
        $vacancies = Vacancy::query()
            ->withCount([
                'applications as untagged_applications_count' => fn ($q) => $q->where('station_id', -1),
                'applications as tagged_applications_count' => fn ($q) => $q->where('station_id', '>', 0),
                'applications as station_pending_count' => fn ($q) => $q->whereHas('assessment', fn ($a) => $a->where('status', 1)),
                'applications as station_completed_count' => fn ($q) => $q->whereHas('assessment', fn ($a) => $a->where('status', '>=', 2)),
                'applications as division_pending_count' => fn ($q) => $q->whereHas('assessment', fn ($a) => $a->where('status', 2)),
                'applications as division_completed_count' => fn ($q) => $q->whereHas('assessment', fn ($a) => $a->where('status', '>=', 3)),
            ])
            ->orderByDesc('created_at')
            ->paginate(50);

        return view('admin.vacancies.active',['vacancies' => $vacancies]);
    }

    public function publish(Vacancy $vacancy)
    {
        $vacancy->update([
            'status' => 1,
            'posting_start_at' => now(),
            'posting_end_at' => $vacancy->posting_end_at && $vacancy->posting_end_at->isFuture()
                ? $vacancy->posting_end_at
                : null,
        ]);

        return back()->with('status', 'Vacancy is now open for applications.');
    }

    public function closePosting(Vacancy $vacancy)
    {
        abort_unless((int) $vacancy->status === 1, 422);

        $vacancy->update(['posting_end_at' => now()]);

        return back()->with('status', 'Applications for this vacancy are now closed.');
    }

    public function reopen(Vacancy $vacancy)
    {
        $vacancy->update([
            'status' => 1,
            'posting_start_at' => now(),
            'posting_end_at' => null,
        ]);

        return back()->with('status', 'Vacancy has been reopened for applications.');
    }

    public function returnToDraft(Vacancy $vacancy)
    {
        $vacancy->update(['status' => 0]);

        return back()->with('status', 'Vacancy was returned to Draft. Its posting dates were preserved.');
    }

    public function apply(Vacancy $vacancy)
    {
        $towns = Town::all();
        $sexes = Dropdown::where('type', '=', 'sex')->get();
        $civilstatuses = Dropdown::where('type', '=', 'civilstatus')->get();

        return view('guest.vacancies.apply', ['vacancy' => $vacancy, 'towns' => $towns, 'sexes' => $sexes, 'civilstatuses' => $civilstatuses]);
    }

}
