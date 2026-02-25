<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('priceUpdatedBy')->orderBy('name')->get();
        return view('products.index', compact('products'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'       => ['required', 'string', 'max:150'],
            'sale_unit'  => ['required', 'in:KG,UNIT'],
            'base_price' => ['required', 'numeric', 'min:0'],
        ]);

        Product::create([
            'name'                     => $request->name,
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
