@extends('layouts.app')
@section('title', 'Facturas')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-bold text-gray-800">Facturas</h1>
    <a href="{{ route('sales.create') }}" class="pos-btn-success">+ Nueva Venta</a>
</div>

<form method="GET" class="flex gap-2 mb-4">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar por consecutivo..."
        class="border rounded px-3 py-2 text-sm w-48">
    <select name="status" class="border rounded px-3 py-2 text-sm">
        <option value="">Todos los estados</option>
        <option value="PAID" @selected(request('status') === 'PAID')>Pagadas</option>
        <option value="PARTIAL" @selected(request('status') === 'PARTIAL')>Parciales</option>
        <option value="PENDING" @selected(request('status') === 'PENDING')>Pendientes</option>
    </select>
    <button class="pos-btn-secondary">Filtrar</button>
    <a href="{{ route('invoices.index') }}" class="pos-btn-secondary">Limpiar</a>
</form>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="text-left px-4 py-3 text-gray-600 font-semibold">#</th>
                <th class="text-left px-4 py-3 text-gray-600 font-semibold">Fecha</th>
                <th class="text-left px-4 py-3 text-gray-600 font-semibold">Cliente</th>
                <th class="text-right px-4 py-3 text-gray-600 font-semibold">Total</th>
                <th class="text-right px-4 py-3 text-gray-600 font-semibold">Saldo</th>
                <th class="text-center px-4 py-3 text-gray-600 font-semibold">Estado</th>
                <th class="text-center px-4 py-3 text-gray-600 font-semibold">FE</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse($invoices as $inv)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-mono font-semibold text-blue-600">
                    <a href="{{ route('invoices.show', $inv) }}">{{ $inv->consecutive }}</a>
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $inv->invoice_date->format('d/m/Y') }}</td>
                <td class="px-4 py-3">{{ $inv->customer->name }}</td>
                <td class="px-4 py-3 text-right font-semibold">${{ number_format($inv->total, 0, ',', '.') }}</td>
                <td class="px-4 py-3 text-right {{ $inv->balance > 0 ? 'text-yellow-700 font-semibold' : 'text-gray-400' }}">
                    ${{ number_format($inv->balance, 0, ',', '.') }}
                </td>
                <td class="px-4 py-3 text-center">
                    <span @class([
                        'badge-paid'    => $inv->isPaid(),
                        'badge-partial' => $inv->isPartial(),
                        'badge-pending' => $inv->isPending(),
                    ])>{{ $inv->status }}</span>
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="text-xs px-1.5 py-0.5 rounded {{ $inv->fe_status === 'ISSUED' ? 'bg-green-100 text-green-700' : ($inv->fe_status === 'PENDING' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-400') }}">
                        {{ $inv->fe_status }}
                    </span>
                </td>
                <td class="px-4 py-3">
                    <a href="{{ route('invoices.show', $inv) }}" class="text-blue-600 hover:text-blue-800 text-xs">Ver</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">No hay facturas.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $invoices->links() }}</div>
@endsection
