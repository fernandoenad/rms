<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Training;

class TrainingController extends Controller
{
    public function index()
    {
        return view('trainings.index', ['trainings' => Training::all()]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_message' => 'required|string',
            'ai_response' => 'required|string',
        ]);

        Training::create([
            'user_message' => $request->user_message,
            'ai_response' => $request->ai_response,
        ]);

        return redirect()->back()->with('status', 'Training data added successfully.');
    }
}
