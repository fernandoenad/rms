<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use OpenAI;
use App\Models\Inquiry;
use App\Models\Application;

class OpenAIController extends Controller
{
    public function chat(Request $request)
    {
        $client = OpenAI::client(env('OPENAI_API_KEY'));

        $response = $client->chat()->create([
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user', 'content' => $request->input('message')],
            ],
        ]);

        return response()->json($response['choices'][0]['message']);
    }

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
            'model' => 'ft:gpt-3.5-turbo-0125:cbrj-wired-internet-services::AvwKMpvq',
            'messages' => [
                ['role' => 'system', 'content' => 'You are an AI assistant providing information on teacher application processes in the Department of Education - Division of Bohol.'],
                ['role' => 'user', 'content' => $request->input('message')],
            ],
        ]);

        // Get AI response
        $aiResponse = $response['choices'][0]['message']['content'];

        $ApplicantInquiry = Inquiry::create([
            'application_id' => $application->id,
            'author' => $application->getFullname(),
            'message' => $request->input('message'),
            'status' => 1,
        ]);

        $AIResponse = Inquiry::create([
            'application_id' => $application->id,
            'author' => "RMS AI",
            'message' => $aiResponse,
            'status' => 0,
        ]);

        Inquiry::where('application_id', '=', $application->id)->update(['status' => 0]);

        return redirect(route('guest.applications.show', ['application' => $application]))->with('status_inquiry', 'Inquiry message was successfully sent.');
    }
}
