@extends('layouts.app')
@section('title', 'Clientes')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-bold text-gray-800">Clientes</h1>
    <a href="{{ route('customers.create') }}" class="pos-btn-primary">+ Nuevo Cliente</a>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="text-left px-4 py-3 text-gray-600 font-semibold">Nombre</th>
                <th class="text-left px-4 py-3 text-gray-600 font-semibold">Documento</th>
                <th class="text-left px-4 py-3 text-gray-600 font-semibold">Teléfono</th>
                <th class="text-center px-4 py-3 text-gray-600 font-semibold">FE</th>
                <th class="text-center px-4 py-3 text-gray-600 font-semibold">Estado</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @foreach($customers as $customer)
            <tr class="hover:bg-gray-50 {{ $customer->is_generic ? 'bg-gray-50' : '' }}">
                <td class="px-4 py-3 font-medium">
                    {{ $customer->name }}
                    @if($customer->is_generic)
                        <span class="ml-1 text-xs text-gray-400 italic">(GENÉRICO)</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-gray-500">{{ $customer->doc_label ?: '—' }}</td>
                <td class="px-4 py-3 text-gray-500">{{ $customer->phone ?: '—' }}</td>
                <td class="px-4 py-3 text-center">
                    @if($customer->requires_fe)
                        <span class="px-2 py-0.5 rounded-full text-xs bg-blue-100 text-blue-700 font-semibold">Sí</span>
                    @else
                        <span class="text-gray-300 text-xs">No</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="{{ $customer->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}
                        px-2 py-0.5 rounded-full text-xs font-semibold">
                        {{ $customer->active ? 'Activo' : 'Inactivo' }}
                    </span>
                </td>
                <td class="px-4 py-3 text-right">
                    @unless($customer->is_generic)
                        <a href="{{ route('customers.edit', $customer) }}"
                            class="text-blue-600 hover:text-blue-800 text-xs">Editar</a>
                    @endunless
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $customers->links() }}</div>
@endsection
