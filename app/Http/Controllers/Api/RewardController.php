<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reward;
use Illuminate\Http\Request;

class RewardController extends Controller
{
    public function index()
    {
        $rewards = Reward::all();
        return response()->json($rewards);
    }

    public function show($id)
    {
        $reward = Reward::findOrFail($id);
        return response()->json($reward);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'points_required' => 'required|integer|min:0',
            'stock' => 'required|integer|min:0',
            'image' => 'required|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        $imagePath = null;

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $destinationPath = public_path('kitarcycle/rewards'); //

            // Create the directory if it doesn't already exist.
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0777, true);
            }

            // Move the uploaded file to the public destination path.
            $file->move($destinationPath, $fileName); //

            // Prepare the relative image path to be stored in the database.
            $imagePath = 'kitarcycle/rewards/' . $fileName;
        }

        $reward = Reward::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'points_required' => $validated['points_required'],
            'stock' => $validated['stock'],
            'image' => $imagePath, // Save the generated image path
        ]);

        // Return a successful response with the created reward data.
        return response()->json([
            'message' => 'Reward created successfully.',
            'data' => $reward
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $reward = Reward::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'points_required' => 'sometimes|required|integer|min:0',
            'stock' => 'sometimes|required|integer|min:0',
        ]);

        $reward->update($validated);

        return response()->json($reward);
    }

    public function updateImage(Request $request, $id)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,gif|max:2048'
        ]);

        $reward = Reward::findOrFail($id);

        // Get the uploaded file
        $file = $request->file('image');
        // Generate a unique filename
        $fileName = time() . '_' . $file->getClientOriginalName();
        // Define the public path within 'rewards' folder
        $destinationPath = public_path('kitarcycle/rewards');

        // Create the directory if it doesn't exist
        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0777, true);
        }

        // Move the image to the public directory
        $file->move($destinationPath, $fileName);

        // Save the path to the database (relative to public folder)
        $reward->image = 'kitarcycle/rewards/' . $fileName;
        $reward->save();

        return response()->json([
            'message' => 'Image updated successfully.',
            'data' => $reward
        ]);
    }

    public function destroy($id)
    {
        $reward = Reward::findOrFail($id);
        $reward->delete();

        return response()->json(null, 204);
    }
}
