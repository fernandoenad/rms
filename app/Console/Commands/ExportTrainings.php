<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Training;
use Illuminate\Support\Facades\Storage;

class ExportTrainings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'export:training-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export training data to JSONL format for OpenAI fine-tuning';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $trainingData = Training::all();
        $data = [];

        foreach ($trainingData as $item) {
            $data[] = [
                "messages" => [
                    ["role" => "system", "content" => "You are an AI assistant for application inquiries of DepEd Bohol."],
                    ["role" => "user", "content" => $item->user_message],
                    ["role" => "assistant", "content" => $item->ai_response],
                ]
            ];
        }

        // Define the public path
        $filePath = public_path('data/training_data.jsonl');

        // Write the file to public/data
        file_put_contents($filePath, implode("\n", array_map('json_encode', $data)));

        $this->info("Exported training data to public/data/training_data.jsonl");
    }
}
