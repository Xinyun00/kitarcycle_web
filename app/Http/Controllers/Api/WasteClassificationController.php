<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WasteClassificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class WasteClassificationController extends Controller
{
    protected $wasteClassificationService;

    public function __construct(WasteClassificationService $wasteClassificationService)
    {
        $this->wasteClassificationService = $wasteClassificationService;
    }

    public function classify(Request $request): JsonResponse
    {
        try {
            // Log the incoming request for debugging
            Log::info('Classify request received', [
                'has_image' => $request->hasFile('image'),
                'content_type' => $request->header('Content-Type'),
                'method' => $request->method()
            ]);

            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240' // max 10MB
            ]);

            $result = $this->wasteClassificationService->classifyWaste($request->file('image'));

            // Log the result for debugging
            Log::info('Classification result', $result);

            return response()->json($result);
        } catch (ValidationException $e) {
            Log::error('Validation error in classify', ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'error' => 'Invalid image file',
                'details' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Exception in classify', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
