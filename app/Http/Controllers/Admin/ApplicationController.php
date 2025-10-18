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
use App\Models\Template;
use Illuminate\Support\Facades\DB;
use Auth;
use Mail;
use App\Mail\UpdateMail;

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
        $data['name'] =  $application->first_name;
        $data['subject'] =  $application->application_code;
        $data['application'] = $application->application_code;

        $application->inquiries()->delete(); //deletes the inquries first
        $application->delete();

        // email 
        $data['message'] = 'Application was deleted by HR.';
        //Mail::to($application->email)->queue(new UpdateMail($data));
        
        return redirect(route('admin.applications.index'))->with('status', 'Application was successfully deleted.');
    }

    public function edit(Application $application)
    {
        $vacancies = Vacancy::all();
        $stations = Station::all();

        return view('admin.applications.edit',['application' => $application, 'vacancies' => $vacancies, 'stations' => $stations]);
    }

    public function edit_scores(Application $application)
    {
        $template = Template::find($application->vacancy->template_id);
        $assessment = $application->assessment->first();

        return view('admin.applications.edit_scores',['application' => $application, 'assessment' => $assessment, 'template' => $template]);
    }

    public function update(Application $application, Request $request)
    {
        //dd($request);
        $data = $request->validate([
            'email' => 'required|email',
            'vacancy_id' => 'required',
            'station_id' => 'required'
        ]);

        if($data['station_id'] == -1){
            $data['station_id'] == $application->vacancy->office_level;
        }

        $application->update($data);

        $data['message'] = 'The application details were updated by HR.';
        
        $assessment = Assessment::join('applications', 'applications.id', '=', 'assessments.application_id')
            ->join('vacancies', 'applications.vacancy_id', '=', 'vacancies.id')
            ->where('applications.vacancy_id', '=', $application->vacancy_id)
            ->where('applications.station_id', '=', $application->station_id)
            ->where('applications.id', '=', $application->id)
            ->select('assessments.*')
            ->get();

        if($assessment->count() == 0 && $data['station_id'] > 0){
            $template = Template::find($application->vacancy->template_id);
            $criteria = json_decode($template->template, true);
            $asessment_details = $criteria;

            foreach ($asessment_details as $key => $value) {
                $asessment_details[$key] = is_numeric($asessment_details[$key]) ? 0 : '-';
            }

            $assessment = Assessment::create(['application_id' => $application->id,
                'template_id' => $application->vacancy->template_id,
                'assessment' => json_encode($asessment_details),
                'status' => 2,
            ]);

            $data['message'] = 'The application was updated and assessed by HR. This has has been forwarded to the top-level committee.';
        }

        $data['application_id'] = $application->id;
        $data['author'] =  auth()->user()->name;
        //$data['message'] = 'The application details were updated.';
        $data['status'] = 0;

        $inquiry = Inquiry::create($data);

        // email 
        $data['name'] =  $application->first_name;
        $data['subject'] =  $application->application_code;
        $data['application'] = $application->application_code;
        //Mail::to($application->email)->queue(new UpdateMail($data));

        return redirect(route('admin.applications.edit', ['application' => $application]))->with('status', 'Application was successfully updated.');
    }

    public function update_scores(Application $application, Request $request)
    {
        $template = Template::find($application->vacancy->template_id);
        $criteria = json_decode($template->template, true);
        $template_details = $criteria;
        $keys = array_keys($template_details);

        $formData = $request->all();

        $totalPoints = 0;

        foreach($request->except('_token') as $key => $value) {
            if (is_numeric($value)) {
                $totalPoints += (float)$value;
            }
        }

        $filteredFormData = array_intersect_key($formData, array_flip($keys));

        $assessment = Assessment::where('application_id', '=', $application->id)->first();

        $assessment->update(['assessment' => json_encode($filteredFormData), 'score' => $totalPoints, 'status' => 3]); 

        $data['application_id'] = $application->id;
        $data['author'] =  auth()->user()->name;
        $data['message'] = 'The assessment scores were updated by HR. You can view your updated scores via the Scores tab.';
        $data['status'] = 0;

        $inquiry = Inquiry::create($data);

        // email 
        $data['name'] =  $application->first_name;
        $data['subject'] =  $application->application_code;
        $data['application'] = $application->application_code;
        //Mail::to($application->email)->queue(new UpdateMail($data));

        return redirect(route('admin.applications.edit_scores', [$application]))->with('status', 'Assessment was successfully updated.');
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

        // email 
        $data['name'] =  $application->first_name;
        $data['subject'] =  $application->application_code;
        $data['application'] = $application->application_code;
        //Mail::to($application->email)->queue(new UpdateMail($data));
        
        return redirect(route('admin.applications.show', ['application' => $application]))->with('status', 'Message was successfully saved and emailed.');
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
        $data['message'] = 'Status was reverted by HR.';
        $data['status'] = 0;

        $inquiry = Inquiry::create($data);

        return redirect(route('admin.applications.show', $application))->with('status', 'The application status has been reverted.');
    }

    public function remove_station(Application $application)
    {
        $assessment = $application->assessment;
        $assessment->delete(); 

        $application->update(['station_id' => $application->vacancy->office_level]);

        $data['application_id'] = $application->id;
        $data['author'] =  auth()->user()->name;
        $data['message'] = 'The station tagging has been removed by HR.';
        $data['status'] = 0;

        $inquiry = Inquiry::create($data);

        return redirect(route('admin.applications.edit', $application))->with('status', $data['message']);
    }

    public function qualify(Application $application)
    {
        $application->assessment->update(['status' => 2]);

        $data['application_id'] = $application->id;
        $data['author'] =  auth()->user()->name;
        $data['message'] = 'The application has been tagged as qualified by HR.';
        $data['status'] = 0;

        $inquiry = Inquiry::create($data);

        return redirect(route('admin.applications.edit', $application))->with('status', $data['message']);
    }

    public function disqualify(Application $application)
    {
        $application->assessment->update(['status' => 4]);

        $data['application_id'] = $application->id;
        $data['author'] =  auth()->user()->name;
        $data['message'] = 'The application has been tagged as disqualified by HR.';
        $data['status'] = 0;

        $inquiry = Inquiry::create($data);

        return redirect(route('admin.applications.edit', $application))->with('status', $data['message']);
    }


    public function vacancy_show_carview(Vacancy $vacancy)
    {
        $assessments = Assessment::join('applications', 'assessments.application_id', '=', 'applications.id')
            ->join('hrms.stations', 'applications.station_id', '=', 'stations.id')
            ->where('applications.vacancy_id', '=', $vacancy->id)
            ->where('assessments.status', '=', 3)
            ->where('assessments.score', '>=', 50)
            ->orderBy('assessments.score', 'DESC')
            ->select('stations.name', 'stations.code', 'assessments.*', 'applications.*')
            ->get();
        
        if(str_contains($vacancy->template->type,'Non-Teaching')){
            return view('admin.applications.list.carviewnt', ['vacancy' => $vacancy, 'assessments' => $assessments]);
        } else {
            return view('admin.applications.list.carview', ['vacancy' => $vacancy, 'assessments' => $assessments]);
        }
    } 

    public function vacancy_show_careerview(Vacancy $vacancy, $level)
    {
        $offices = Office::all();

        $assessments = Assessment::join('applications', 'assessments.application_id', '=', 'applications.id')
            ->join('hrms.stations', 'applications.station_id', '=', 'stations.id')
            ->where('applications.vacancy_id', '=', $vacancy->id)
            ->where('assessments.status', '=', 3)
            ->where('assessments.score', '>=', 50)
            ->orderBy('assessments.score', 'DESC')
            ->select('stations.name', 'stations.code', 'assessments.*', 'applications.*')
            ->get();

        return view('admin.applications.list.careerview', ['vacancy' => $vacancy, 'assessments' => $assessments, 'offices' => $offices, 'level' => $level]);
    } 

    public function vacancy_show_carview2(Vacancy $vacancy)
    {
        $offices = Office::all();

        if(str_contains($vacancy->template->type,'SG')){
            return view('admin.applications.list.carview2nt', ['vacancy' => $vacancy, 'offices' => $offices]);
        } else {
            return view('admin.applications.list.carview2', ['vacancy' => $vacancy, 'offices' => $offices]);
        }
    }

    public function vacancy_show_carview3(Vacancy $vacancy)
    {
        $assessments = Assessment::join('applications', 'assessments.application_id', '=', 'applications.id')
            ->join('hrms.stations', 'applications.station_id', '=', 'stations.id')
            ->where('applications.vacancy_id', '=', $vacancy->id)
            ->where('assessments.status', '=', 3)
            ->orderBy('assessments.score', 'DESC')
            ->select('stations.name', 'stations.code', 'assessments.*', 'applications.*')
            ->get();

        if(str_contains($vacancy->template->type,'SG')){
            return view('admin.applications.list.carview3nt', ['vacancy' => $vacancy, 'assessments' => $assessments]);
        } else {
            return view('admin.applications.list.carview3', ['vacancy' => $vacancy, 'assessments' => $assessments]);
        }
    }

    public function vacancy_show_carview4(Vacancy $vacancy)
    {
        $assessments = Assessment::join('applications', 'assessments.application_id', '=', 'applications.id')
            ->join('hrms.stations', 'applications.station_id', '=', 'stations.id')
            ->where('applications.vacancy_id', '=', $vacancy->id)
            ->where('assessments.status', '>=', 2)
            ->orderBy('applications.last_name', 'ASC')
            ->orderBy('applications.first_name', 'ASC')
            ->orderBy('applications.middle_name', 'ASC')
            ->select('stations.name', 'stations.code', 'assessments.*', 'applications.*')
            ->get();

        return view('admin.applications.list.carview4nt', ['vacancy' => $vacancy, 'assessments' => $assessments]);
    }

    public function vacancy_show_carview5(Vacancy $vacancy)
    {
        $assessments = Assessment::join('applications', 'assessments.application_id', '=', 'applications.id')
            ->join('hrms.stations', 'applications.station_id', '=', 'stations.id')
            ->where('applications.vacancy_id', '=', $vacancy->id)
            ->where('assessments.status', '=', 3)
            ->where('assessments.score', '>=', 50)
            ->orderBy('assessments.score', 'DESC')
            ->select('stations.name', 'stations.code', 'assessments.*', 'applications.*')
            ->get();

        return view('admin.applications.list.carview5nt', ['vacancy' => $vacancy, 'assessments' => $assessments]);
    }
}
