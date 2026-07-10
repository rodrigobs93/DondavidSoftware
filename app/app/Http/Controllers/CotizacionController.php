<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Setting;
use App\Services\EscPosTicketRenderer;
use App\Services\ThermalPrinterService;
use Illuminate\Http\Request;

/**
 * Cotización — informational price quotation built from current product prices.
 *
 * No invoice, sale, payment, inventory or print_jobs record is created; the
 * ticket prints synchronously like marquillas/cartera. The on-screen preview
 * text (with accents, client-side formatCOP) intentionally differs in
 * presentation from the ASCII-sanitized thermal ticket — same data, don't "fix".
 */
class CotizacionController extends Controller
{
    public function __construct(
        private EscPosTicketRenderer $renderer,
        private ThermalPrinterService $printer,
    ) {}

    public function index()
    {
        $products = Product::active()
            ->with('category')
            ->orderBy('name')
            ->get(['id', 'name', 'sale_unit', 'base_price', 'category_id']);

        $categories = ProductCategory::where('active', true)->orderBy('name')->get();
        $shop       = Setting::shopInfo();
        $today      = now()->setTimezone('America/Bogota')->format('d/m/Y');

        return view('products.cotizacion', compact('products', 'categories', 'shop', 'today'));
    }

    public function print(Request $request)
    {
        $validated = $request->validate(
            [
                'product_ids'   => 'required|array|min:1|max:500',
                'product_ids.*' => 'integer',
            ],
            [
                'product_ids.required' => 'Selecciona al menos un producto para la cotización.',
                'product_ids.min'      => 'Selecciona al menos un producto para la cotización.',
            ]
        );

        // Re-read current prices server-side; drop inactive/deleted/priceless products.
        $products = Product::active()
            ->with('category')
            ->whereIn('id', $validated['product_ids'])
            ->where('base_price', '>', 0)
            ->orderBy('name')
            ->get(['id', 'name', 'sale_unit', 'base_price', 'category_id']);

        if ($products->isEmpty()) {
            return response()->json([
                'error' => 'Ninguno de los productos seleccionados tiene un precio válido. Actualiza los precios e intenta de nuevo.',
            ], 422);
        }

        // Group by category (alphabetical), uncategorized products last as "Otros".
        $sections = $products
            ->groupBy(fn ($p) => $p->category?->name ?? '')
            ->map(fn ($group, $label) => [
                'label' => $label !== '' ? $label : 'Otros',
                'items' => $group->map(fn ($p) => [
                    'name'       => $p->name,
                    'sale_unit'  => $p->sale_unit,
                    'base_price' => (string) $p->base_price,
                ])->values()->all(),
            ])
            ->sortBy(fn ($s) => [$s['label'] === 'Otros' ? 1 : 0, mb_strtolower($s['label'])])
            ->values()
            ->all();

        $payload = [
            'shop'      => Setting::shopInfo(),
            'printDate' => now()->setTimezone('America/Bogota')->format('d/m/Y H:i'),
            'sections'  => $sections,
            'note'      => 'Nota: Precios sujetos a cambios segun disponibilidad y actualizacion del dia.',
        ];

        try {
            $this->printer->send($this->renderer->renderCotizacion($payload));
            return response()->json(['ok' => true, 'printed' => $products->count()]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error al imprimir: ' . $e->getMessage()], 500);
        }
    }
}
