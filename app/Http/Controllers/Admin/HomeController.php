<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Auth;
use App\Models\User;
use App\Models\Application;
use App\Models\Inquiry;

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

}
