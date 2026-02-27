<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = ProductCategory::withCount('products')->orderBy('name')->get();
        return view('categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('product_categories', 'name')],
        ]);

        ProductCategory::create(['name' => $request->name]);

        return back()->with('success', 'Categoría creada.');
    }

    public function update(Request $request, ProductCategory $category)
    {
        $request->validate([
            'name'   => ['required', 'string', 'max:100', Rule::unique('product_categories', 'name')->ignore($category->id)],
            'active' => ['boolean'],
        ]);

        $category->update([
            'name'   => $request->name,
            'active' => $request->boolean('active', $category->active),
        ]);

        return response()->json(['success' => true, 'name' => $category->name, 'active' => $category->active]);
    }

    public function toggleActive(ProductCategory $category)
    {
        $category->update(['active' => !$category->active]);
        return back()->with('success', 'Categoría ' . ($category->active ? 'activada' : 'desactivada') . '.');
    }

    public function destroy(ProductCategory $category)
    {
        $category->delete();
        return back()->with('success', 'Categoría eliminada. Los productos asignados quedaron sin categoría.');
    }
}
