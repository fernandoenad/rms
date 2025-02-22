<?php

namespace App\Http\Controllers\Guest;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Inquiry;
use App\Models\Vacancy;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Mail;
use App\Mail\UpdateMail;

class ApplicationController extends Controller
{
    public function index(Request $request)
    {
        $request->session()->forget('guest_email');

        return view('guest.applications.index');
    }

    public function lookup(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email'
        ]);

        $request->session()->put('guest_email', $request->email);

        $applications = Application::where('email', '=', $request->email)
        ->get();

        return view('guest.applications.result', ['applications' => $applications, 'email' => $request->email]);
    }

    public function my(Request $request)
    {
        $applications = Application::where('email', '=', $request->session()->get('guest_email'))
        ->get();

        return view('guest.applications.result', ['applications' => $applications, 'email' => $request->session()->get('guest_email')]);
    }

    public function show(Request $request, Application $application)
    {
        $applicationInquiries = $application->inquiries;

        if($request->session()->get('guest_email') == $application->email){
            $oldDate = Carbon::parse($application->updated_at);
            $nowDate = Carbon::parse(date('Y-m-d h:i:s'));
            $diffInDays =  $oldDate->diffInDays($nowDate);
        
            return view('guest.applications.show', ['application' => $application, 'applicationInquiries' => $applicationInquiries, 'diffInDays' => $diffInDays]);
        } else {
            abort(401);
        }   
    }

    public function store(Request $request, Vacancy $vacancy)
    {
        $data = $request->validate([
            'first_name' => 'required|min:2|max:255',
            'middle_name' => 'required|min:1|max:255',
            'last_name' => 'required|min:2|max:255',
            'sitio' => 'required:min:1|max:255',
            'barangay' => 'required|min:2|max:255',
            'municipality' => 'required',
            'zip' => 'required|integer|between:6300,6400',
            'age' => 'required|integer|between:18,60',
            'gender' => 'required',
            'civil_status' => 'required',
            'religion' => 'required|min:1|max:255',
            'disability' => 'required|min:1|max:255',
            'ethnic_group' => 'required|min:1|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('applications')
                ->where(function ($query) use ($request, $vacancy) {
                    return $query->where('email', $request->email)
                    ->where('vacancy_id', $vacancy->id);
            })],
            'phone' => 'required|min:11|max:12|regex:(^(09)\\d{9})',
        ], [
            'email.unique' => 'See error above!'
        ]);

        $data['vacancy_id'] = $vacancy->id;
        $data['application_code'] = $vacancy->cycle . '-' . $vacancy->id;

        $newApplication = Application::create($data);   
        $newApplication->update(['application_code' => $data['application_code'] . '-' . $newApplication->id]);

        $data['station_id'] = $vacancy->office_level;
        $newApplication->update(['station_id' => $data['station_id']]);

        // email 
        $data['name'] =  $newApplication->first_name;
        $data['message'] =  'You have successfully applied for the ' . $vacancy->position_title . ' position!';
        $data['subject'] =  $newApplication->application_code;

        Mail::to($newApplication->email)->queue(new UpdateMail($data));

        $request->session()->put('guest_email', $request->email);
        
        return redirect(route('guest.applications.show', ['application' => $newApplication]))->with('status', 'Application was successful!');
    }

    public function inquire(Application $application, Request $request)
    {
        $data = $request->validate([
            'message' => 'required'
        ]);

        $newInquiry = Inquiry::create([
            'application_id' => $application->id,
            'author' => $application->getFullname(),
            'message' => $data['message'],
            'status' => 1,
        ]);
        
        return redirect(route('guest.applications.show', ['application' => $application]))->with('status_inquiry', 'Inquiry message was successfully sent.');
    }
}
