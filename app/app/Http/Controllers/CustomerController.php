<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

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
            'name'        => ['required', 'string', 'max:150'],
            'doc_type'    => ['nullable', 'in:NIT,CC'],
            'doc_number'  => ['nullable', 'string', 'max:30'],
            'phone'       => ['nullable', 'string', 'max:30'],
            'address'     => ['nullable', 'string'],
            'email'       => ['nullable', 'email', 'max:150'],
            'requires_fe' => ['boolean'],
            'notes'       => ['nullable', 'string'],
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
            'name'        => ['required', 'string', 'max:150'],
            'doc_type'    => ['nullable', 'in:NIT,CC'],
            'doc_number'  => ['nullable', 'string', 'max:30'],
            'phone'       => ['nullable', 'string', 'max:30'],
            'address'     => ['nullable', 'string'],
            'email'       => ['nullable', 'email', 'max:150'],
            'requires_fe' => ['boolean'],
            'notes'       => ['nullable', 'string'],
            'active'      => ['boolean'],
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
}
