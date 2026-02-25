@extends('layouts.app')
@section('title', 'FE Pendiente')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-bold text-gray-800">Facturación Electrónica Pendiente</h1>
    <span class="text-sm text-gray-500">{{ $invoices->total() }} facturas</span>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="text-left px-4 py-3 text-gray-600 font-semibold">#</th>
                <th class="text-left px-4 py-3 text-gray-600 font-semibold">Fecha</th>
                <th class="text-left px-4 py-3 text-gray-600 font-semibold">Cliente</th>
                <th class="text-left px-4 py-3 text-gray-600 font-semibold">Documento</th>
                <th class="text-right px-4 py-3 text-gray-600 font-semibold">Total</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse($invoices as $invoice)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-mono font-semibold text-blue-600">{{ $invoice->consecutive }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $invoice->invoice_date->format('d/m/Y') }}</td>
                <td class="px-4 py-3 font-medium">{{ $invoice->customer->name }}</td>
                <td class="px-4 py-3 text-gray-500">{{ $invoice->customer->doc_label ?: '—' }}</td>
                <td class="px-4 py-3 text-right font-semibold">${{ number_format($invoice->total, 0, ',', '.') }}</td>
                <td class="px-4 py-3">
                    <a href="{{ route('invoices.show', $invoice) }}"
                       class="pos-btn-primary text-xs py-1">Ver / Marcar</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">
                No hay facturas con FE pendiente.
            </td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $invoices->links() }}</div>
@endsection
