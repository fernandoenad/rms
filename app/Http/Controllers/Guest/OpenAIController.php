<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use OpenAI;
use App\Models\Inquiry;
use App\Models\Application;
use App\Models\Setting;

class OpenAIController extends Controller
{
    public function inquire2(Application $application, Request $request)
    {
        // Validate input
        $request->validate([
            'message' => 'required|string|max:500',
        ]);

        // Create OpenAI client
        $client = OpenAI::client(env('OPENAI_API_KEY'));

        // Send request to OpenAI
        $response = $client->chat()->create([
            'model' => Setting::where('item', 'ai_model_id')->first()->value,
            'messages' => [
                ['role' => 'system', 'content' => 'You are an AI assistant providing the application processes in the Department of Education - Division of Bohol.'],
                ['role' => 'user', 'content' => $request->input('message')],
            ],
        ]);

        // Get AI response
        $aiResponse = $response['choices'][0]['message']['content'];

        $applicantInquiry = Inquiry::create([
            'application_id' => $application->id,
            'author' => $application->getFullname(),
            'message' => $request->input('message'),
            'status' => 1,
        ]);

        $aiResponse = Inquiry::create([
            'application_id' => $application->id,
            'author' => "RMS AI",
            'message' => $aiResponse,
            'status' => 0,
        ]);

        $applicantInquiry->update(['status' => 1]);

        return redirect(route('guest.applications.show', ['application' => $application]))->with('status_inquiry', 'Inquiry message was successfully sent.');
    }
}
