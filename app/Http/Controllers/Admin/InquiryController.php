<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Inquiry;

class InquiryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $inquiries = Inquiry::where('status', '=', 1)
        ->get();

        return view('admin.inquiries.index', ['inquiries' => $inquiries]);
    }
}
