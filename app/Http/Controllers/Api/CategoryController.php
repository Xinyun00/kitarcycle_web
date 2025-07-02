<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;

class CategoryController extends Controller
{
    // List all categories (with pagination)
    public function index()
    {
        $categories = Category::all();
        return response()->json($categories);
    }

    // Show a single category by ID
    public function show($id)
    {
        $category = Category::find($id);
        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }
        return response()->json($category);
    }

    // Create a new category
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'points_per_kg' => 'required|numeric|min:0',
        ]);

        $category = Category::create([
            'name' => $request->name,
            'points_per_kg' => $request->points_per_kg,
        ]);

        return response()->json([
            'message' => 'Category created successfully',
            'category' => $category
        ], 201);
    }

    // Update existing category
    public function update(Request $request, $id)
    {
        $category = Category::find($id);
        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255|unique:categories,name,' . $category->id,
            'points_per_kg' => 'sometimes|numeric|min:0',
        ]);

        if ($request->has('name')) {
            $category->name = $request->name;
        }
        if ($request->has('points_per_kg')) {
            $category->points_per_kg = $request->points_per_kg;
        }
        $category->save();
        $category->refresh();

        return response()->json([
            'message' => 'Category updated successfully',
            'category' => $category
        ]);
    }

    // Delete category
    public function destroy($id)
    {
        $category = Category::find($id);
        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted successfully']);
    }
}
