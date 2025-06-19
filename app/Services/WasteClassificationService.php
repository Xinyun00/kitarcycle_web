<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class WasteClassificationService
{
    protected $apiUrl;

    public function __construct()
    {
        // IMPORTANT: Update this URL with your current ngrok URL from Google Colab
        // The URL changes each time you restart the Colab notebook
        $this->apiUrl = 'https://Sherine99-recyclablewaste.hf.space/classify';
    }

    public function classifyWaste(UploadedFile $image)
    {
        try {
            $response = Http::withoutVerifying()->attach(
                'image',
                file_get_contents($image->getRealPath()),
                $image->getClientOriginalName()
            )->post($this->apiUrl);

            if ($response->successful()) {
                $data = $response->json();

                // Debug log the response
                Log::info('Model response:', $data);

                return [
                    'success' => true,
                    'data' => [
                        'category' => $data['recyclable'] ?? 'unknown',
                        'confidence' => $data['recycle_confidence'] ?? 0.0,
                        'class' => $data['recycle_class'] ?? 'unknown'
                    ]
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to classify waste',
                'status' => $response->status()
            ];
        } catch (\Exception $e) {
            Log::error('Classification error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status' => 500
            ];
        }
    }

    protected function determineCategoryFromClass($class)
    {
        $class = strtolower($class);

        // Map common waste classes to categories
        $recyclableClasses = ['plastic', 'glass', 'metal', 'paper', 'cardboard', 'aluminum'];
        $organicClasses = ['food', 'fruit', 'vegetable', 'organic', 'biodegradable'];
        $hazardousClasses = ['battery', 'chemical', 'toxic', 'hazardous'];

        if (in_array($class, $recyclableClasses)) {
            return 'recyclable';
        } elseif (in_array($class, $organicClasses)) {
            return 'organic';
        } elseif (in_array($class, $hazardousClasses)) {
            return 'hazardous';
        }

        return 'non-recyclable';
    }
}
