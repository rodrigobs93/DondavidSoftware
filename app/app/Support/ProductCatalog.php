<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductCategory;

/**
 * Builds the active category → products tree used by the Alpine product picker.
 * Shared by SaleController::create (Nueva Venta) and InvoiceController::show
 * (invoice inline editor) so the picker markup and data stay identical.
 */
class ProductCatalog
{
    /**
     * @return \Illuminate\Support\Collection<int, array>
     */
    public static function tree()
    {
        $cats = ProductCategory::where('active', true)
            ->with(['products' => fn($q) => $q->where('active', true)->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->values()
            ->map(fn($cat, $i) => [
                'id'         => $cat->id,
                'name'       => $cat->name,
                'colorIndex' => $i % 6,
                'products'   => $cat->products->map(fn($p) => self::product($p))->values(),
            ]);

        $uncat = Product::where('active', true)->whereNull('category_id')->orderBy('name')->get();
        if ($uncat->isNotEmpty()) {
            $cats->push([
                'id'         => 0,
                'name'       => 'Sin categoría',
                'colorIndex' => $cats->count() % 6,
                'products'   => $uncat->map(fn($p) => self::product($p))->values(),
            ]);
        }

        return $cats;
    }

    private static function product(Product $p): array
    {
        return [
            'id'         => $p->id,
            'name'       => $p->name,
            'sale_unit'  => $p->sale_unit,
            'base_price' => (string) $p->base_price,
        ];
    }
}
