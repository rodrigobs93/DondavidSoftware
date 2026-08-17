<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerProductPrice;
use App\Models\Product;
use App\Models\Setting;
use App\Services\EscPosTicketRenderer;
use App\Services\ThermalPrinterService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    // Constructor DI (same pattern as CarteraController/CotizacionController)
    // so the printer can be mocked in tests.
    public function __construct(
        private EscPosTicketRenderer $renderer,
        private ThermalPrinterService $printer,
    ) {}

    public function index(Request $request)
    {
        $search = $request->input('search', '');

        $query = Customer::orderByRaw('is_generic DESC, name ASC')
            ->when($search, fn($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('business_name', 'ilike', "%{$search}%");
            }));

        $toRow = fn($c) => [
            'id'            => $c->id,
            'name'          => $c->name,
            'business_name' => $c->business_name,
            'is_generic'    => $c->is_generic,
            'doc_label'     => $c->doc_label ?: null,
            'phone'         => $c->phone,
            'requires_fe'   => $c->requires_fe,
            'active'        => $c->active,
        ];

        if ($request->wantsJson()) {
            return response()->json($query->get()->map($toRow));
        }

        $customers   = $query->paginate(30)->withQueryString();
        $initialData = $customers->map($toRow);

        return view('customers.index', compact('customers', 'search', 'initialData'));
    }

    public function create()
    {
        return view('customers.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:150'],
            'business_name' => ['nullable', 'string', 'max:150', Rule::requiredIf($request->doc_type === 'NIT')],
            'doc_type'      => ['nullable', 'in:NIT,CC'],
            'doc_number'    => ['nullable', 'string', 'max:30',
                Rule::unique('customers', 'doc_number')
                    ->where('doc_type', $request->doc_type)
                    ->whereNull('deleted_at'),
            ],
            'phone'                => ['nullable', 'string', 'max:30'],
            'address'              => ['nullable', 'string'],
            'email'                => ['nullable', 'email', 'max:150'],
            'requires_fe'          => ['boolean'],
            'notes'                => ['nullable', 'string'],
            'default_delivery_fee' => ['nullable', 'numeric', 'min:0'],
        ], [
            'default_delivery_fee.min' => 'El valor de domicilio no puede ser negativo.',
        ]);

        $customer = Customer::create($data);

        if ($request->expectsJson()) {
            return response()->json([
                'customer' => [
                    'id'                   => $customer->id,
                    'name'                 => $customer->name,
                    'business_name'        => $customer->business_name,
                    'is_generic'           => false,
                    'doc_type'             => $customer->doc_type,
                    'doc_number'           => $customer->doc_number,
                    'requires_fe'          => $customer->requires_fe,
                    'default_delivery_fee' => $customer->default_delivery_fee,
                ],
            ], 201);
        }

        return redirect()->route('customers.index')->with('success', 'Cliente creado.');
    }

    public function edit(Customer $customer)
    {
        if ($customer->is_generic) {
            return back()->with('error', 'El cliente GENÉRICO no se puede editar.');
        }
        return view('customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        if ($customer->is_generic) {
            abort(403, 'El cliente GENÉRICO no se puede modificar.');
        }

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:150'],
            'business_name' => ['nullable', 'string', 'max:150', Rule::requiredIf($request->doc_type === 'NIT')],
            'doc_type'      => ['nullable', 'in:NIT,CC'],
            'doc_number'    => ['nullable', 'string', 'max:30',
                Rule::unique('customers', 'doc_number')
                    ->where('doc_type', $request->doc_type)
                    ->whereNull('deleted_at')
                    ->ignore($customer->id),
            ],
            'phone'                => ['nullable', 'string', 'max:30'],
            'address'              => ['nullable', 'string'],
            'email'                => ['nullable', 'email', 'max:150'],
            'requires_fe'          => ['boolean'],
            'notes'                => ['nullable', 'string'],
            'active'               => ['boolean'],
            'default_delivery_fee' => ['nullable', 'numeric', 'min:0'],
        ], [
            'default_delivery_fee.min' => 'El valor de domicilio no puede ser negativo.',
        ]);

        $customer->update($data);
        return redirect()->route('customers.index')->with('success', 'Cliente actualizado.');
    }

    public function search(Request $request)
    {
        $q = $request->input('q', '');
        $customers = Customer::where('active', true)
            ->where(function ($query) use ($q) {
                $query->where('name', 'ilike', "%{$q}%")
                    ->orWhere('business_name', 'ilike', "%{$q}%")
                    ->orWhere('doc_number', 'ilike', "%{$q}%");
            })
            ->orderByRaw('is_generic DESC, name ASC')
            ->limit(20)
            ->get(['id', 'name', 'business_name', 'doc_type', 'doc_number', 'is_generic', 'requires_fe', 'default_delivery_fee']);

        return response()->json($customers);
    }

    public function destroy(Customer $customer)
    {
        if ($customer->is_generic) {
            abort(403, 'El cliente GENÉRICO no se puede eliminar.');
        }

        if ($customer->invoices()->exists()) {
            $customer->delete(); // soft delete — preserves invoice references
            $message = 'Cliente eliminado. El historial de facturas se conserva.';
        } else {
            $customer->forceDelete();
            $message = 'Cliente eliminado definitivamente.';
        }

        return redirect()->route('customers.index')->with('success', $message);
    }

    // ── Special prices ──────────────────────────────────────────────────────

    public function getPrices(Customer $customer)
    {
        $prices = $customer->specialPrices()
            ->with('product:id,name,sale_unit')
            ->orderBy('id')
            ->get();

        return response()->json($prices);
    }

    public function upsertPrice(Request $request, Customer $customer)
    {
        $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'price'      => ['required', 'numeric', 'min:0'],
        ]);

        $cpp = CustomerProductPrice::updateOrCreate(
            ['customer_id' => $customer->id, 'product_id' => $request->product_id],
            ['price' => $request->price]
        );

        $cpp->load('product:id,name,sale_unit');

        return response()->json(['success' => true, 'record' => $cpp]);
    }

    public function updatePrice(Request $request, Customer $customer, Product $product)
    {
        $request->validate([
            'price' => ['required', 'integer', 'min:0'],
        ]);

        $cpp = CustomerProductPrice::where('customer_id', $customer->id)
            ->where('product_id', $product->id)
            ->firstOrFail();

        $cpp->update(['price' => $request->price]);
        $cpp->load('product:id,name,sale_unit');

        return response()->json(['success' => true, 'record' => $cpp]);
    }

    public function deletePrice(Customer $customer, Product $product)
    {
        CustomerProductPrice::where('customer_id', $customer->id)
            ->where('product_id', $product->id)
            ->delete();

        return response()->json(['success' => true]);
    }

    // ── Special prices report (single ticket, whole clientele) ──────────────

    /**
     * Print one thermal ticket with every agreed special price, grouped by
     * customer. Only customers that actually have prices appear, and only
     * products that still exist in the catalog (soft-deleted products keep
     * their `price` row but there is nothing to print). Prices are read fresh
     * from the DB on every print, like the cotización.
     *
     * Informational only: no invoice, payment or print_jobs record.
     */
    public function printSpecialPrices()
    {
        $customers = Customer::whereHas('specialPrices.product')
            ->with(['specialPrices' => fn($q) => $q->whereHas('product')
                                                   ->with('product:id,name,sale_unit,base_price')])
            ->orderBy('name')
            ->get();

        $sections = $customers
            ->map(fn($c) => [
                'label'         => $c->name,
                'business_name' => $c->business_name ?? '',
                'items'         => $c->specialPrices
                    ->sortBy(fn($cpp) => mb_strtolower($cpp->product->name))
                    ->map(fn($cpp) => [
                        'name'          => $cpp->product->name,
                        'sale_unit'     => $cpp->product->sale_unit,
                        'base_price'    => (string) $cpp->product->base_price,
                        'special_price' => (string) $cpp->price,
                    ])->values()->all(),
            ])
            ->values()
            ->all();

        if (empty($sections)) {
            return response()->json([
                'error' => 'Ningún cliente tiene precios especiales configurados.',
            ], 422);
        }

        $totalItems = array_sum(array_map(fn($s) => count($s['items']), $sections));

        $payload = [
            'shop'      => Setting::shopInfo(),
            'printDate' => now()->setTimezone('America/Bogota')->format('d/m/Y H:i'),
            'sections'  => $sections,
            'note'      => 'Nota: Precios acordados con cada cliente, sujetos a cambios.',
        ];

        try {
            $this->printer->send($this->renderer->renderPreciosEspeciales($payload));
            return response()->json([
                'ok'        => true,
                'customers' => count($sections),
                'printed'   => $totalItems,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error al imprimir: ' . $e->getMessage()], 500);
        }
    }
}
