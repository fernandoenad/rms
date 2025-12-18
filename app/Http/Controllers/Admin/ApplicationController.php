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
use App\Models\Exam;
use Yajra\DataTables\Facades\DataTables;

class ApplicationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $stationsTable = config('database.connections.mysql_2.database') . '.stations';
            
            $applications = Application::with(['assessment'])
                ->select([
                    'applications.*',
                    'vacancies.position_title as vacancy_position_title',
                    'stations.name as station_name'
                ])
                ->leftJoin('vacancies', 'applications.vacancy_id', '=', 'vacancies.id')
                ->leftJoin($stationsTable . ' as stations', 'applications.station_id', '=', 'stations.id');
            
            return DataTables::of($applications)
                ->addColumn('application_code_link', function ($application) {
                    return '<a href="' . route('admin.applications.show', $application) . '" title="View">' . $application->application_code . '</a>';
                })
                ->addColumn('fullname', function ($application) {
                    return $application->getFullname();
                })
                ->addColumn('position_title', function ($application) {
                    return '<a href="' . route('admin.applications.vacancy.show', $application->vacancy_id) . '">' . ($application->vacancy_position_title ?? '') . '</a>';
                })
                ->addColumn('station_name_display', function ($application) {
                    return $application->station_name ?? 'Untagged';
                })
                ->addColumn('action', function ($application) {
                    $editBtn = '<a href="' . route('admin.applications.edit', ['application' => $application]) . '" class="btn btn-xs btn-warning" title="Modify application"><span class="fas primary fa-fw fa-edit"></span></a> ';
                    
                    if ($application->assessment !== null) {
                        $scoreBtn = '<a href="' . route('admin.applications.edit_scores', ['application' => $application]) . '" class="btn btn-xs btn-primary" title="Modify assessment"><span class="fas primary fa-fw fa-list"></span></a> ';
                    } else {
                        $scoreBtn = '<a href="#" class="btn btn-xs btn-primary" title="Modify assessment" onClick="return confirm(\'Action not permitted! This application was not taken-in yet. Take in the application first via the School/Office portal.\')"><span class="fas primary fa-fw fa-list"></span></a> ';
                    }
                    
                    $deleteDisabled = isset($application->assessment) ? 'disabled' : '';
                    $deleteBtn = '<a href="' . route('admin.applications.delete', ['application' => $application]) . '" class="btn btn-xs btn-danger ' . $deleteDisabled . '" title="Delete"><span class="fas fa-fw fa-trash"></span></a>';
                    
                    return $editBtn . $scoreBtn . $deleteBtn;
                })
                ->filterColumn('fullname', function($query, $keyword) {
                    $query->where(function($q) use ($keyword) {
                        $q->where('applications.first_name', 'LIKE', "%{$keyword}%")
                          ->orWhere('applications.last_name', 'LIKE', "%{$keyword}%")
                          ->orWhere('applications.middle_name', 'LIKE', "%{$keyword}%");
                    });
                })
                ->filterColumn('position_title', function($query, $keyword) {
                    $query->where('vacancies.position_title', 'LIKE', "%{$keyword}%");
                })
                ->filterColumn('station_name_display', function($query, $keyword) {
                    $query->where('stations.name', 'LIKE', "%{$keyword}%");
                })
                ->orderColumn('fullname', function ($query, $order) {
                    $query->orderBy('applications.last_name', $order);
                })
                ->rawColumns(['application_code_link', 'position_title', 'action'])
                ->make(true);
        }

        return view('admin.applications.index');
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
        $application->load(['vacancy', 'assessment', 'inquiries']);
        
        // Load station from mysql_2 connection
        $station = Station::find($application->station_id);
        
        $applicationInquiries = $application->inquiries;

        return view('admin.applications.show',[
            'application' => $application,
            'applicationInquiries' => $applicationInquiries,
            'station' => $station,
        ]);
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
        // Load application with relationships to prevent N+1
        $application->load(['vacancy', 'station', 'assessment']);
        $vacancies = Vacancy::select('id', 'position_title', 'cycle')->get();
        $stations = Station::select('id', 'name', 'code','office_id')->get();


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

    public function vacancy_show(Request $request, Vacancy $vacancy)
    {
        if ($request->ajax()) {
            $stationsTable = config('database.connections.mysql_2.database') . '.stations';
            
            $applications = Application::with(['assessment'])
                ->select([
                    'applications.*',
                    'stations.name as station_name'
                ])
                ->leftJoin($stationsTable . ' as stations', 'applications.station_id', '=', 'stations.id')
                ->where('applications.vacancy_id', '=', $vacancy->id);
            
            return DataTables::of($applications)
                ->addColumn('application_code_link', function ($application) {
                    return '<a href="' . route('admin.applications.show', $application) . '" title="View">' . $application->application_code . '</a>';
                })
                ->addColumn('fullname', function ($application) {
                    return $application->getFullname();
                })
                ->addColumn('station_name_display', function ($application) {
                    return $application->station_name ?? ($application->station_id == 0 ? 'Division' : 'Untagged');
                })
                ->addColumn('action', function ($application) {
                    $editBtn = '<a href="' . route('admin.applications.edit', ['application' => $application]) . '" class="btn btn-sm btn-warning" title="Modify"><span class="fas primary fa-fw fa-edit"></span></a> ';
                    
                    $deleteDisabled = isset($application->assessment) ? 'disabled' : '';
                    $deleteBtn = '<a href="' . route('admin.applications.delete', ['application' => $application]) . '" class="btn btn-sm btn-danger ' . $deleteDisabled . '" title="Delete"><span class="fas fa-fw fa-trash"></span></a>';
                    
                    return $editBtn . $deleteBtn;
                })
                ->filterColumn('fullname', function($query, $keyword) {
                    $query->where(function($q) use ($keyword) {
                        $q->where('applications.first_name', 'LIKE', "%{$keyword}%")
                          ->orWhere('applications.last_name', 'LIKE', "%{$keyword}%")
                          ->orWhere('applications.middle_name', 'LIKE', "%{$keyword}%");
                    });
                })
                ->filterColumn('station_name_display', function($query, $keyword) {
                    $query->where('stations.name', 'LIKE', "%{$keyword}%");
                })
                ->orderColumn('fullname', function ($query, $order) {
                    $query->orderBy('applications.last_name', $order);
                })
                ->rawColumns(['application_code_link', 'action'])
                ->make(true);
        }

        return view('admin.applications.list.index', ['vacancy' => $vacancy]);
    }


    public function vacancy_show_tagged(Vacancy $vacancy)
    {
        $applications = Application::with(['station', 'assessment'])
            ->where('vacancy_id', '=', $vacancy->id)
            ->where('station_id', '!=', -1)
            ->get();

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
        $assessments = Assessment::with(['application.station.office'])
            ->whereHas('application', function ($query) use ($vacancy) {
                $query->where('vacancy_id', '=', $vacancy->id);
            })
            ->where('assessments.status', '=', 3)
            ->where('assessments.score', '>=', 50)
            ->orderBy('assessments.score', 'DESC')
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

        $assessments = Assessment::with(['application.station.office'])
            ->whereHas('application', function ($query) use ($vacancy) {
                $query->where('vacancy_id', '=', $vacancy->id);
            })
            ->where('assessments.status', '=', 3)
            ->where('assessments.score', '>=', 50)
            ->orderBy('assessments.score', 'DESC')
            ->get();

        $assessmentsByOffice = $assessments
            ->filter(function ($assessment) {
                return optional($assessment->application->station)->office_id !== null;
            })
            ->groupBy(function ($assessment) {
                return $assessment->application->station->office_id;
            });

        return view('admin.applications.list.careerview', [
            'vacancy' => $vacancy,
            'assessments' => $assessments,
            'assessmentsByOffice' => $assessmentsByOffice,
            'offices' => $offices,
            'level' => $level,
        ]);
    } 

    public function vacancy_show_careerviewb(Vacancy $vacancy, $level)
    {
        $offices = Office::all();

        $assessments = Assessment::with(['application.station.office'])
            ->whereHas('application', function ($query) use ($vacancy) {
                $query->where('vacancy_id', '=', $vacancy->id);
            })
            ->where('status', '=', 3)
            ->orderBy('application_id', 'ASC')
            ->get();

        $assessmentsByOffice = $assessments
            ->filter(function ($assessment) {
                return optional($assessment->application->station)->office_id !== null;
            })
            ->groupBy(function ($assessment) {
                return $assessment->application->station->office_id;
            });

        return view('admin.applications.list.careerviewb', [
            'vacancy' => $vacancy,
            'assessments' => $assessments,
            'assessmentsByOffice' => $assessmentsByOffice,
            'offices' => $offices,
            'level' => $level,
        ]);
    } 

    public function vacancy_show_carview2(Vacancy $vacancy)
    {
        $offices = Office::all();
        $assessments = Assessment::with(['application.station.office'])
            ->whereHas('application', function ($query) use ($vacancy) {
                $query->where('vacancy_id', '=', $vacancy->id);
            })
            ->where('assessments.status', '=', 3)
            ->where('assessments.score', '>=', 50)
            ->orderBy('assessments.score', 'DESC')
            ->get();

        $assessmentsByOffice = $assessments
            ->filter(function ($assessment) {
                return optional($assessment->application->station)->office_id !== null;
            })
            ->groupBy(function ($assessment) {
                return $assessment->application->station->office_id;
            });

        $viewData = [
            'vacancy' => $vacancy,
            'offices' => $offices,
            'assessmentsByOffice' => $assessmentsByOffice,
        ];

        if(str_contains($vacancy->template->type,'SG')){
            return view('admin.applications.list.carview2nt', $viewData);
        } else {
            return view('admin.applications.list.carview2', $viewData);
        }
    }

    public function vacancy_show_carview3(Vacancy $vacancy)
    {
        $assessments = Assessment::with(['application.station.office'])
            ->whereHas('application', function ($query) use ($vacancy) {
                $query->where('vacancy_id', '=', $vacancy->id);
            })
            ->where('assessments.status', '=', 3)
            ->orderBy('assessments.score', 'DESC')
            ->get();

        if(str_contains($vacancy->template->type,'SG')){
            return view('admin.applications.list.carview3nt', ['vacancy' => $vacancy, 'assessments' => $assessments]);
        } else {
            return view('admin.applications.list.carview3', ['vacancy' => $vacancy, 'assessments' => $assessments]);
        }
    }

    public function vacancy_show_carview4(Vacancy $vacancy)
    {
        $assessments = Assessment::with(['application' => function ($query) use ($vacancy) {
                $query->where('vacancy_id', '=', $vacancy->id)
                    ->orderBy('applications.last_name', 'ASC')
                    ->orderBy('applications.first_name', 'ASC')
                    ->orderBy('applications.middle_name', 'ASC');
            }, 'application.station.office'])
            ->whereHas('application', function ($query) use ($vacancy) {
                $query->where('vacancy_id', '=', $vacancy->id);
            })
            ->where('assessments.status', '>=', 2)
            ->orderBy(Application::select('last_name')->whereColumn('applications.id', 'assessments.application_id'))
            ->orderBy(Application::select('first_name')->whereColumn('applications.id', 'assessments.application_id'))
            ->orderBy(Application::select('middle_name')->whereColumn('applications.id', 'assessments.application_id'))
            ->get();

        return view('admin.applications.list.carview4nt', ['vacancy' => $vacancy, 'assessments' => $assessments]);
    }

    public function vacancy_show_carview5(Vacancy $vacancy)
    {
        $assessments = Assessment::with(['application.station.office'])
            ->whereHas('application', function ($query) use ($vacancy) {
                $query->where('vacancy_id', '=', $vacancy->id);
            })
            ->where('assessments.status', '=', 3)
            ->where('assessments.score', '>=', 50)
            ->orderBy('assessments.score', 'DESC')
            ->get();

        return view('admin.applications.list.carview5nt', ['vacancy' => $vacancy, 'assessments' => $assessments]);
    }
}
