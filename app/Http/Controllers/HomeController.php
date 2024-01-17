<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Application;
use App\Models\Inquiry;
use Carbon\Carbon;

class HomeController extends Controller
{
    public function index()
    {
        return view('index');
    }

    public function lookup(Request $request){
        //dd($request);
        $data = $request->validate([
            'applicant_email' => 'required|email'
        ]);

        $applications = Application::where('applicant_email', '=', $request->applicant_email)
        ->get();

        return view('result', ['applications' => $applications, 'applicant_email' => $request->applicant_email]);
    }

    public function show(Application $application){
        //dd($application->inquiries);
        $applicationInquiries = $application->inquiries;

        $oldDate = Carbon::parse($application->updated_at);
        $nowDate = Carbon::parse(date('Y-m-d h:i:s'));
        $diffInDays =  $oldDate->diffInDays($nowDate);
        
        return view('show', ['application' => $application, 'applicationInquiries' => $applicationInquiries, 'diffInDays' => $diffInDays]);
    }

    public function store(Application $application, Request $request){
        // dd($application);
        $data = $request->validate([
            'message' => 'required'
        ]);

        $newInquiry = Inquiry::create([
            'application_id' => $application->id,
            'author' => $application->applicant_fullname,
            'message' => $data['message'],
            'status' => 1,
        ]);
        
        return redirect(route('guest.application.show', ['application' => $application]))->with('status', 'Inquiry message was successfully sent.');
    }
}
