<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $search     = $request->input('search', '');
        $categoryId = $request->input('category_id', '');

        $products = Product::with(['priceUpdatedBy', 'category'])
            ->when($search,     fn($q) => $q->where('name', 'ilike', "%{$search}%"))
            ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
            ->orderByDesc('active')
            ->orderBy('name')
            ->get();

        $categories = ProductCategory::where('active', true)->orderBy('name')->get();

        if ($request->wantsJson()) {
            return response()->json($products->map(fn ($p) => [
                'id'               => $p->id,
                'name'             => $p->name,
                'sale_unit'        => $p->sale_unit,
                'base_price'       => (string) $p->base_price,
                'category_id'      => $p->category_id,
                'category_name'    => $p->category?->name,
                'active'           => $p->active,
                'price_updated_at' => $p->price_updated_at
                                        ?->setTimezone('America/Bogota')->format('d/m/Y H:i'),
                'price_updated_by' => $p->priceUpdatedBy?->name,
            ]));
        }

        return view('products.index', compact('products', 'categories', 'search', 'categoryId'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'sale_unit'   => ['required', 'in:KG,UNIT'],
            'base_price'  => ['required', 'numeric', 'min:0'],
            'category_id' => ['nullable', 'exists:product_categories,id'],
        ]);

        Product::create([
            'name'                     => $request->name,
            'category_id'              => $request->category_id ?: null,
            'sale_unit'                => $request->sale_unit,
            'base_price'               => $request->base_price,
            'price_updated_at'         => now(),
            'price_updated_by_user_id' => auth()->id(),
        ]);

        return back()->with('success', 'Producto creado.');
    }

    public function updatePrice(Request $request, Product $product)
    {
        $request->validate([
            'base_price' => ['required', 'numeric', 'min:0'],
        ]);

        $product->update([
            'base_price'               => $request->base_price,
            'price_updated_at'         => now(),
            'price_updated_by_user_id' => auth()->id(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'base_price' => (string) $product->base_price]);
        }

        return back()->with('success', 'Precio actualizado.');
    }

    public function updateName(Request $request, Product $product)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:150'],
        ]);

        $product->update(['name' => $request->name]);

        return response()->json(['success' => true, 'name' => $product->name]);
    }

    public function updateCategory(Request $request, Product $product)
    {
        $request->validate([
            'category_id' => ['nullable', 'exists:product_categories,id'],
        ]);

        $categoryId = $request->category_id ?: null;
        $product->update(['category_id' => $categoryId]);

        $categoryName = $categoryId ? ProductCategory::find($categoryId)?->name : null;

        return response()->json(['success' => true, 'category_id' => $categoryId, 'category_name' => $categoryName]);
    }

    public function destroy(Product $product)
    {
        if ($product->invoiceItems()->exists()) {
            $product->delete();
            $message = 'Producto eliminado. El historial de ventas se conserva.';
        } else {
            $product->forceDelete();
            $message = 'Producto eliminado definitivamente.';
        }

        return back()->with('success', $message);
    }

    public function toggleActive(Product $product)
    {
        $product->update(['active' => !$product->active]);
        return back()->with('success', 'Producto ' . ($product->active ? 'activado' : 'desactivado') . '.');
    }

    public function search(Request $request)
    {
        $q = $request->input('q', '');
        $products = Product::active()
            ->where('name', 'ilike', "%{$q}%")
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'sale_unit', 'base_price']);

        return response()->json($products);
    }
}
