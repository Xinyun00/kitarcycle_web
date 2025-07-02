<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class RecyclerController extends Controller
{
    public function register(Request $request)
    {
        // Validation rules
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:191', 'unique:users,name'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:191',
                'confirmed',   // expects 'password_confirmation' field in request
                'regex:/[a-z]/',      // must contain a lowercase letter
                'regex:/[A-Z]/',      // must contain an uppercase letter
                'regex:/[0-9]/',      // must contain a digit
                'regex:/[@$!%*#?&]/'  // must contain a special character
            ],
            'contact_number' => [
                'required',
                'digits_between:10,11',
                'unique:users,contact_number'
            ],
            'location' => ['required', 'string'],
            'points' => ['nullable', 'integer']
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $recycler = new User();
            $recycler->name = $request->name;
            $recycler->email = $request->email;
            $recycler->password = Hash::make($request->password);
            $recycler->contact_number = $request->contact_number;
            $recycler->location = $request->location;
            $recycler->total_points_earned = $request->points ?? 0;
            $recycler->current_points = $request->points ?? 0;

            $recycler->save();

            return response()->json([
                'message' => 'Recycler registered successfully!'
            ], 201);
        } catch (\Exception $e) {
            Log::error('Recycler Registration Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Please try again later'
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $input = trim($request->input('email_or_name'));
        $password = trim($request->input('password'));

        if (empty($input) || empty($password)) {
            return response()->json([
                'message' => 'Please enter your email, name, and password',
            ], 400);
        }

        try {
            // Check if input is an email or a name
            $recycler = User::where('email', $input)
                ->orWhere('name', $input)
                ->first();

            if (!$recycler || !Hash::check($password, $recycler->password)) {
                return response()->json([
                    'message' => 'Invalid Email, Name, or Password. Please Try Again',
                ], 401);
            }

            $recycler->api_token = Str::random(60);
            $recycler->save();

            return response()->json([
                'token' => $recycler->api_token,
                'user' => $recycler
            ], 201);
        } catch (\Exception $e) {
            Log::error('Recycler Authentication Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Please try again later',
            ], 500);
        }
    }

    public function show($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json($user);
    }

    public function updateProfile(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $rules = [
            'name' => ['sometimes', 'string', Rule::unique('users')->ignore($id)],
            'contact_number' => ['sometimes', 'digits_between:10,11', Rule::unique('users')->ignore($id)],
            'location' => 'sometimes|string',
            'image' => 'sometimes|image|mimes:jpg,jpeg,png,gif|max:2048', // Added gif and increased max size
        ];

        // Check if password is being updated
        $updatingPassword = $request->filled('password');

        if ($updatingPassword) {
            // Check current password
            if (!$request->filled('currentPassword') || !Hash::check($request->currentPassword, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect.',
                ], 403);
            }
            $rules['password'] = [
                'required',
                'confirmed',
                'string',
                'min:8',
                'max:191',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[@$!%*#?&]/',
            ];
        }

        $customMessages = [
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character [@$!%*#?&].',
            'contact_number.digits_between' => 'Phone number must be between 10 and 11 digits.',
        ];

        // Use Validator facade instead of $request->validate()
        $validator = Validator::make($request->all(), $rules, $customMessages);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();
        $user->fill($request->only(['name', 'contact_number', 'location']));

        if ($updatingPassword) {
            $user->password = Hash::make($request->password);
        }

        if ($request->hasFile('image')) {
            // Check if an old image exists and delete it
            if ($user->image) {
                if (Storage::disk('public')->exists($user->image)) {
                    Storage::disk('public')->delete($user->image);
                }
            }
            // Store the new photo and save the path.
            $imagePath = $request->file('image')->store('kitarcycle/profile_photos', 'public');
            $user->image = $imagePath;
        }

        $user->save();

        $message = $updatingPassword ? 'Password updated successfully' : 'Profile updated successfully';

        return response()->json([
            'success' => true,
            'message' => $message,
            'user' => $user,
        ]);
    }

    public function deleteAccount(Request $request)
    {
        $user = $request->user(); // assuming token-based auth middleware

        if (!$user) {
            return response()->json(['message' => 'User not authenticated.'], 401);
        }

        try {
            // IMPROVEMENT: Delete the user's profile photo from storage before deleting the user.
            if ($user->image) {
                // *** ADDED CHECK HERE ***
                if (Storage::disk('public')->exists($user->image)) {
                    Storage::disk('public')->delete($user->image);
                }
            }

            $user->delete();

            return response()->json(['message' => 'Account deleted successfully.'], 200);
        } catch (\Exception $e) {
            Log::error('Delete Account Error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to delete account.'], 500);
        }
    }

    public function index()
    {
        try {
            $recyclers = User::all();

            return response()->json([
                'success' => true,
                'data' => $recyclers
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching recyclers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recyclers'
            ], 500);
        }
    }
}
