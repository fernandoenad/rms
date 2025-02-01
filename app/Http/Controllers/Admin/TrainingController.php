<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use App\Models\Setting;
use App\Models\Training;

class TrainingController extends Controller
{
    protected $openaiApiKey;

    public function __construct()
    {
        $this->openaiApiKey = env('OPENAI_API_KEY'); // Store API Key in .env
    }

    public function index()
    {
        $trainings = Training::all();

        return view('admin.ai.index', ['trainings' => $trainings]);
    }

    public function create(Training $training)
    {
        return view('admin.ai.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_message' => 'required|unique:trainings',
            'ai_response' => 'required',
        ]);

        $newTraining = Training::create($data);

        return redirect(route('admin.ai.index'))->with('status', 'Record has been saved successfully!');
    }

    public function modify(Training $training)
    {
        return view('admin.ai.modify', ['training' => $training]);
    }

    public function update(Training $training, Request $request)
    {
        $data = $request->validate([
            'user_message' => 'required|unique:trainings,user_message,'.$training->id,
            'ai_response' => 'required', 
        ]);

        $training->update($data);

        return redirect(route('admin.ai.index'))->with('status', 'Record has been saved successfully!');
    }

    public function delete(Training $training)
    {
        $training->delete();

        return redirect(route('admin.ai.index'))->with('status', 'Record has been deleted successfully!');
    }

    public function train()
    {
        return view('admin.ai.train');
    }

    public function start()
    {
        return response()->stream(function () {
            function sendMessage($message)
            {
                echo "data: {$message}\n\n";
                ob_flush();
                flush();
            }

            // Prelminary
            sendMessage("AI Training Progress...");

            // Step 1: Export Training Data
            sendMessage("Step 1: Exporting training data...");
            Artisan::call('export:training-data');

            // Step 2: Upload Training Data to OpenAI
            sendMessage("Step 2: Uploading training data...");
            $fileId = $this->uploadTrainingData();
            if (!$fileId) {
                sendMessage("Error: Failed to upload training data.");
                return;
            }
            
            // Step 3: Fine-Tune Model
            sendMessage("Step 3: Starting fine-tuning...");
            $fineTuneId = $this->fineTuneModel($fileId);
            if (!$fineTuneId) {
                sendMessage("Error: Failed to start fine-tuning.");
                return;
            }

            // Step 4: Monitor Fine-Tuning Progress
            sendMessage("Step 4: Monitoring fine-tuning progress...");
            $fineTunedModel = $this->monitorFineTuning($fineTuneId);

            if (!$fineTunedModel) {
                sendMessage("Error: Fine-tuning failed.");
                return;
            }

            // Step 5: Update AI Model in Settings
            Setting::updateOrCreate(
                ['item' => 'ai_model'],
                ['value' => $fineTunedModel]
            );

            sendMessage("Success: AI Model successfully trained! Model ID: {$fineTunedModel}");
            
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive'
        ]);
    }

    private function uploadTrainingData()
    {
        $filePath = public_path('data/training_data.jsonl');

        $response = Http::withToken($this->openaiApiKey)
            ->attach('file', fopen($filePath, 'r'), 'training_data.jsonl')
            ->post('https://api.openai.com/v1/files', [
                'purpose' => 'fine-tune'
            ]);

        return $response->successful() ? $response->json()['id'] : null;
    }

    private function fineTuneModel($fileId)
    {
        $response = Http::withToken($this->openaiApiKey)
            ->post('https://api.openai.com/v1/fine_tuning/jobs', [
                'training_file' => $fileId,
                'model' => 'gpt-4o-2024-08-06'
            ]);

        return $response->successful() ? $response->json()['id'] : null;
    }

    private function monitorFineTuning($fineTuneId)
    {
        while (true) {
            sleep(60);

            $response = Http::withToken($this->openaiApiKey)
                ->get("https://api.openai.com/v1/fine_tuning/jobs/{$fineTuneId}");

            if ($response->successful()) {
                $data = $response->json();

                if ($data['status'] === 'succeeded') {
                    return $data['fine_tuned_model'];
                } elseif ($data['status'] === 'failed') {
                    return null;
                }

                sendMessage("Status: {$data['status']} - Checking again in 1 min...");
            }
        }
    }
}
