<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerProductPrice;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::orderByRaw('is_generic DESC, name ASC')->paginate(30);
        return view('customers.index', compact('customers'));
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
            'doc_number'    => ['nullable', 'string', 'max:30'],
            'phone'         => ['nullable', 'string', 'max:30'],
            'address'       => ['nullable', 'string'],
            'email'         => ['nullable', 'email', 'max:150'],
            'requires_fe'   => ['boolean'],
            'notes'         => ['nullable', 'string'],
        ]);

        Customer::create($data);
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
            'doc_number'    => ['nullable', 'string', 'max:30'],
            'phone'         => ['nullable', 'string', 'max:30'],
            'address'       => ['nullable', 'string'],
            'email'         => ['nullable', 'email', 'max:150'],
            'requires_fe'   => ['boolean'],
            'notes'         => ['nullable', 'string'],
            'active'        => ['boolean'],
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
                    ->orWhere('doc_number', 'ilike', "%{$q}%");
            })
            ->orderByRaw('is_generic DESC, name ASC')
            ->limit(20)
            ->get(['id', 'name', 'doc_type', 'doc_number', 'is_generic', 'requires_fe']);

        return response()->json($customers);
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

    public function deletePrice(Customer $customer, Product $product)
    {
        CustomerProductPrice::where('customer_id', $customer->id)
            ->where('product_id', $product->id)
            ->delete();

        return response()->json(['success' => true]);
    }
}
