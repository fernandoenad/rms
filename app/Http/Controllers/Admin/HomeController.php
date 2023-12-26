<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Auth;
use App\Models\User;
use App\Models\Application;
use App\Models\Inquiry;
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
        $users = User::all();
        
        return view('admin.index', ['applications' => $applications, 'inquiries' => $inquiries, 'users' => $users]);
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

}
