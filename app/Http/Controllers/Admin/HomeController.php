<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Auth;
use App\Models\User;
use App\Models\Application;
use App\Models\Inquiry;
use App\Models\Vacancy;
use Illuminate\Support\Facades\Hash;


class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $applications = Application::all();
        $inquiries = Inquiry::where('status', '=', 1)->get();
        $vacancies = Vacancy::where('status', '=', 1)->get();
        $users = User::all();
        
        return view('admin.index', ['applications' => $applications, 'inquiries' => $inquiries, 'vacancies' => $vacancies, 'users' => $users]);
    }

    public function change_password()
    {
        return view('admin.change_password');
    }

    public function change_password_ok(Request $request)
    {
        //dd(Auth::user());
        //dd($request);
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|confirmed',
        ]);

        $user = Auth::user();

        if (Hash::check($request->current_password, $user->password)) {
            // Update the password
            $user->update([
                'password' => Hash::make($request->password),
            ]);
    
            return redirect()->route('admin.change_password')->with('status', 'Password changed successfully.');
        } else {
            return back()->withErrors(['current_password' => 'Incorrect current password.']);
        }
    }

    public function get_notifications(Request $request)
    {
        $inquiries = Inquiry::where('status', '=', 1)->orderBy('created_at','desc')->get();
        $notifications = $inquiries->take(5);
        
        $dropdownHtml = '';
        
        foreach ($notifications as $notification) {
            $icon = "<i class='mr-2 fas fa-fw fa-paper-plane text-primary'></i>";
    
            $dropdownHtml .= "<a href='/admin/applications/{$notification->application_id}' class='dropdown-item'>
                                {$icon}{$notification->author}
                              </a>";
        }

        $dropdownHtml .= "<div class='dropdown-divider'></div>";

        return [
            'label'       => count($inquiries),
            'label_color' => 'danger',
            'icon_color'  => 'dark',
            'dropdown'    => $dropdownHtml,
        ];
    }

}
