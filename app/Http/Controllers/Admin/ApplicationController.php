<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Application;
use App\Models\Inquiry;
use App\Models\Vacancy;
use App\Models\Station;
use App\Models\Office;
use App\Models\Assessment;
use Illuminate\Support\Facades\DB;
use Auth;

class ApplicationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $applications = Application::latest()->take(100)->get();

        return view('admin.applications.index',['applications' => $applications]);
    }

    public function search(Request $request)
    {
        $search_str = $request->input('search_str');

        $applications = Application::where('application_code', $search_str)
            ->orWhere('first_name', 'LIKE', '%' . $search_str . '%')
            ->orWhere('last_name', 'LIKE', '%' . $search_str . '%')
            ->get();

        return view('admin.applications.index',['applications' => $applications]);
    }

    public function create()
    {
        return view('admin.applications.create');
    }

    public function store(Request $request)
    {
        //dd($request);
        $data = $request->validate([
            'application_code' => 'required|unique:applications',
            'applicant_email' => 'required|email',
            'applicant_fullname' => 'required',
            'position_applied' => 'required',
            'pertinent_doc' => 'required|url',
        ]);

        $newApplication = Application::create($data);

        return redirect(route('admin.applications.index'))->with('status', 'Application was successfully saved.');
    }

    public function import(Request $request)
    {
        $file = $request->file('file');
        $fileContents = file($file->getPathname());

        foreach ($fileContents as $line) {
            $data = str_getcsv($line);

            Application::create([
                'application_code' => $data[0],
                'applicant_email' => $data[1],
                'position_applied' => $data[2],
                'applicant_fullname' => $data[3],
                'pertinent_doc' => $data[4],
                // Add more fields as needed
            ]);
        }

        return redirect(route('admin.applications.index'))->with('status', 'Applications were successfully saved.');
    }

    public function show(Application $application)
    {
        $applicationInquiries = $application->inquiries;

        return view('admin.applications.show',['application' => $application, 'applicationInquiries' => $applicationInquiries]);
    }

    public function delete(Application $application)
    {
        return view('admin.applications.delete',['application' => $application]);
    }

    public function destroy(Application $application)
    {
        $application->inquiries()->delete(); //deletes the inquries first
        $application->delete();
        
        return redirect(route('admin.applications.index'))->with('status', 'Application was successfully deleted.');
    }

    public function edit(Application $application)
    {
        $vacancies = Vacancy::all();
        $stations = Station::all();

        return view('admin.applications.edit',['application' => $application, 'vacancies' => $vacancies, 'stations' => $stations]);
    }

    public function update(Application $application, Request $request)
    {
        //dd($request);
        $data = $request->validate([
            'email' => 'required|email',
            'vacancy_id' => 'required',
            'station_id' => 'required'
        ]);

        $application->update($data);

        return redirect(route('admin.applications.index'))->with('status', 'Application was successfully updated.');
    }

    public function saveInquiry(Application $application, Request $request)
    {
        // dd($application);
        $data = $request->validate([
            'message' => 'required'
        ]);

        $newInquiry = Inquiry::create([
            'application_id' => $application->id,
            'author' => Auth::user()->name,
            'message' => $data['message'],
            'status' => 0,
        ]);

        Inquiry::where('application_id', '=', $application->id)->update(['status' => 0]);
        
        return redirect(route('admin.applications.show', ['application' => $application]))->with('status', 'Inquiry message was successfully sent.');
    }

    public function vacancy_show(Vacancy $vacancy)
    {
        $applications = Application::where('vacancy_id', '=', $vacancy->id)->get();

        return view('admin.applications.list.index', ['vacancy' => $vacancy, 'applications' => $applications]);
    }


    public function vacancy_show_tagged(Vacancy $vacancy)
    {
        $applications = Application::where('vacancy_id', '=', $vacancy->id)
            ->where('station_id', '!=', -1)->get();

        return view('admin.applications.list.tagged', ['vacancy' => $vacancy, 'applications' => $applications]);
    }


    public function revert(Application $application)
    {
        $assessment = $application->assessment;
        $assessment->delete(); 

        $data['application_id'] = $application->id;
        $data['author'] =  auth()->user()->name;
        $data['message'] = 'Status has been sucessfuly reverted.';
        $data['status'] = 0;

        $inquiry = Inquiry::create($data);

        return redirect(route('admin.applications.show', $application))->with('status', 'The application status has been reverted.');
    }

    public function vacancy_show_carview(Vacancy $vacancy)
    {
            $assessments = Assessment::join('applications', 'assessments.application_id', '=', 'applications.id')
            ->join('hrms.stations', 'applications.station_id', '=', 'stations.id')
            ->where('applications.vacancy_id', '=', $vacancy->id)
            ->where('assessments.status', '>=', 2)
            ->orderBy('assessments.score', 'DESC')
            ->select('stations.name', 'stations.code', 'assessments.*', 'applications.*')
            ->get();

        return view('admin.applications.list.carview', ['vacancy' => $vacancy, 'assessments' => $assessments]);
    }

    public function vacancy_show_carview2(Vacancy $vacancy)
    {
        $offices = Office::all();

        return view('admin.applications.list.carview2', ['vacancy' => $vacancy, 'offices' => $offices]);
    }
}
