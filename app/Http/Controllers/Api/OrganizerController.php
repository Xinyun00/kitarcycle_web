<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class OrganizerController extends Controller
{
    public function register(Request $request)
    {
        $name = $request->name;
        $email = $request->email;
        $password = Hash::make($request->password);
        $contact_number = $request->contact_number;
        $location = $request->location;
        $recyclingTypesAccepted = $request->recyclingTypesAccepted;

        if ($email == null || $name == null || $password == null || $contact_number == null || $location == null || $recyclingTypesAccepted == null) {
            return response()->json([
                'message' => 'Please enter required fields.',
            ], 400);
        }

        $checkExisted = Organizer::where('email', $email)->first();
        if ($checkExisted) {
            return response()->json([
                'message' => 'Email is not available.'
            ], 409);
        }

        try {
            $recyclingTypesAccepted = json_encode($recyclingTypesAccepted);

            $organizer = new Organizer();
            $organizer->name = $name;
            $organizer->email = $email;
            $organizer->password = $password;
            $organizer->contact_number = $contact_number;
            $organizer->location = $location;
            $organizer->recyclingTypesAccepted = $recyclingTypesAccepted;

            $organizer->save();
            return response()->json([
                'message' => 'Organizer registered successfully!'
            ], 201);
        } catch (\Exception $e) {
            Log::error('Organizer Registration Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Please try again later'
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $organizer = Organizer::where('email', $request->email)->first();

        if (!$organizer || !Hash::check($request->password, $organizer->password)) {
            return response()->json([
                'message' => 'Invalid Email Address or Password.'
            ], 401);
        }

        // Generate a new random API token and save it
        $organizer->api_token = Str::random(60);
        $organizer->save();

        return response()->json([
            'token' => $organizer->api_token, // Return the newly generated API token
            'organizer' => $organizer
        ], 200);
    }

    // Get organizers by selected category
    public function getOrganizersByCategory(Request $request)
    {
        $category = $request->category;

        if (!$category) {
            return response()->json([
                'message' => 'Category is required.'
            ], 400);
        }

        // Find organizers whose JSON array includes the category
        $organizers = Organizer::whereJsonContains('recyclingTypesAccepted', $category)->get();

        return response()->json([
            'organizers' => $organizers
        ], 200);
    }

    public function getAllOrganizers()
    {
        try {
            $organizers = Organizer::all();

            return response()->json([
                'organizers' => $organizers
            ], 200);
        } catch (\Exception $e) {
            Log::error('Fetching all organizers failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch organizers. Please try again later.'
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $organizer = Organizer::findOrFail($id);
            return response()->json([
                'success' => true,
                'organizer' => $organizer
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching organizer: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Organizer not found or an error occurred.'
            ], 404);
        }
    }

    public function updateProfile(Request $request, $id)
    {
        // Find the organizer by ID
        $organizer = Organizer::findOrFail($id);

        $rules = [
            'name' => [
                'sometimes',
                'string',
                function ($attribute, $value, $fail) use ($id) {
                    // Check if the name is already taken by another organizer
                    if (Organizer::where('name', $value)->where('id', '!=', $id)->exists()) {
                        $fail('The organizer name is already taken.');
                    }
                },
            ],
            'contact_number' => [
                'sometimes',
                'digits_between:10,11',
                // Ensure contact number is unique among other organizers
                'unique:organizers,contact_number,' . $id,
            ],
            'location' => 'sometimes|string',
            'recyclingTypesAccepted' => 'sometimes|array', // Expecting an array for recycling types
            'recyclingTypesAccepted.*' => 'string', // Each item in the array should be a string
            'image' => 'sometimes|image|mimes:jpg,jpeg,png,gif|max:2048', // Max 2MB image
        ];

        // Check if password is being updated
        $updatingPassword = $request->filled('password');

        if ($updatingPassword) {
            $rules['password'] = [
                'required',
                'confirmed', // Requires a password_confirmation field
                'string',
                'min:8',
                'max:191',
                'regex:/[a-z]/',    // Must contain at least one lowercase letter
                'regex:/[A-Z]/',    // Must contain at least one uppercase letter
                'regex:/[0-9]/',    // Must contain at least one digit
                'regex:/[@$!%*#?&]/', // Must contain at least one special character
            ];
        }

        $customMessages = [
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character [@$!%*#?&].',
            'contact_number.digits_between' => 'Contact number must be between 10 and 11 digits.',
            'recyclingTypesAccepted.array' => 'Recycling types accepted must be an array.',
            'image.max' => 'The image size must not exceed 2MB.',
        ];

        $validator = Validator::make($request->all(), $rules, $customMessages);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        try {
            if (isset($validated['name'])) {
                $organizer->name = $validated['name'];
            }
            if (isset($validated['contact_number'])) {
                $organizer->contact_number = $validated['contact_number'];
            }
            if (isset($validated['location'])) {
                $organizer->location = $validated['location'];
            }
            if (isset($validated['recyclingTypesAccepted'])) {
                // Ensure the array is encoded to JSON before saving
                $organizer->recyclingTypesAccepted = json_encode($validated['recyclingTypesAccepted']);
            }

            if ($updatingPassword) {
                $organizer->password = Hash::make($request->password);
            }

            if ($request->hasFile('image')) {
                // Delete old image if it exists
                if ($organizer->image) {
                    Storage::disk('public')->delete($organizer->image);
                }

                $path = $request->file('image')->store('kitarcycle/organizer_profile', 'public');


                $organizer->image = $path;
            }

            $organizer->save();

            $message = $updatingPassword ? 'Password updated successfully' : 'Profile updated successfully';

            return response()->json([
                'success' => true,
                'message' => $message,
                'organizer' => $organizer, // Return the updated organizer data
            ], 200);
        } catch (\Exception $e) {
            Log::error('Organizer Profile Update Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile.'
            ], 500);
        }
    }

    public function deleteAccount(Request $request)
    {
        $organizer = $request->user();

        if (!$organizer) {
            return response()->json([
                'message' => 'Organizer not authenticated.'
            ], 401);
        }

        try {
            // Optionally, delete associated image from storage
            if ($organizer->image) {
                Storage::disk('public')->delete($organizer->image);
            }

            $organizer->delete();

            return response()->json([
                'message' => 'Organizer account deleted successfully.'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Organizer Delete Account Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete organizer account.'
            ], 500);
        }
    }
}
