@extends('layouts.app')
@section('title', 'Proveedor: ' . $supplier->name)

@php $methods = \App\Models\SupplierPayment::$methods; @endphp

@section('content')
<div x-data="supplierDetail()">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 gap-2 flex-wrap">
        <div>
            <a href="{{ route('suppliers.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Proveedores</a>
            <h1 class="text-xl font-bold text-gray-800">{{ $supplier->name }}</h1>
            @if($supplier->tax_id)<span class="text-sm text-gray-500">NIT {{ $supplier->tax_id }}</span>@endif
            @if($supplier->phone)<span class="text-sm text-gray-500"> · Tel {{ $supplier->phone }}</span>@endif
        </div>
        <div class="flex gap-2">
            <form method="POST" action="{{ route('suppliers.print', $supplier) }}">
                @csrf
                <button class="pos-btn pos-btn-secondary text-sm">🖨 Imprimir estado</button>
            </form>
            <button type="button"
                    @click="$dispatch('open-supplier-invoice', { supplierId: {{ $supplier->id }}, supplierName: @js($supplier->name), locked: true })"
                    class="pos-btn-primary text-sm">+ Nueva factura</button>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm mb-4">
            {{ session('success') }}
        </div>
    @endif
    @foreach(['payment', 'print', 'total_amount'] as $errKey)
        @error($errKey)
            <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm mb-4">{{ $message }}</div>
        @enderror
    @endforeach

    {{-- Summary cards --}}
    <div class="grid grid-cols-3 gap-3 mb-4">
        <div class="bg-red-50 rounded-lg shadow p-4">
            <div class="text-xs text-red-700/70">Deuda total</div>
            <div class="text-lg font-bold text-red-700">{{ format_cop($totalDebt) }}</div>
        </div>
        <div class="bg-green-50 rounded-lg shadow p-4">
            <div class="text-xs text-green-700/70">Saldo a favor</div>
            <div class="text-lg font-bold text-green-700">{{ format_cop($credit) }}</div>
        </div>
        <div class="bg-yellow-50 rounded-lg shadow p-4">
            <div class="text-xs text-yellow-700/70">Neto a pagar</div>
            <div class="text-lg font-bold text-yellow-700">{{ format_cop($net) }}</div>
        </div>
    </div>

    {{-- Consolidated payment (FIFO) --}}
    @if($pending->isNotEmpty())
    <div class="bg-white rounded-lg shadow p-6 mb-4">
        <h2 class="font-semibold text-gray-700 mb-3">Registrar pago al proveedor (se aplica a las facturas más antiguas)</h2>
        <form method="POST" action="{{ route('suppliers.payments', $supplier) }}"
              @submit="$refs.subkey.value = crypto.randomUUID()">
            @csrf
            <input type="hidden" name="submission_key" x-ref="subkey">
            <div class="flex flex-wrap gap-2 mb-3">
                @foreach($methods as $key => $label)
                    <label class="cursor-pointer">
                        <input type="radio" name="method" value="{{ $key }}" class="hidden peer" @if($loop->first) checked @endif>
                        <span class="px-3 py-1.5 rounded-full text-sm border peer-checked:bg-blue-600 peer-checked:text-white peer-checked:border-blue-600">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            <div class="flex flex-wrap gap-3 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Monto</label>
                    <input type="number" step="1" min="1" name="amount" data-keyboard="numeric"
                           class="border rounded px-3 py-2 text-sm w-40" required>
                </div>
                <div class="flex-1 min-w-[12rem]">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                    <input type="text" name="notes" data-keyboard="text" class="w-full border rounded px-3 py-2 text-sm">
                </div>
                <button class="pos-btn pos-btn-success text-sm">Pagar</button>
            </div>
        </form>
    </div>
    @endif

    {{-- Tabs --}}
    <div class="flex gap-2 mb-3">
        <button @click="tab = 'pending'" :class="tab === 'pending' ? 'pos-btn-primary' : 'pos-btn pos-btn-secondary'" class="text-sm">Pendientes ({{ $pending->count() }})</button>
        <button @click="tab = 'paid'" :class="tab === 'paid' ? 'pos-btn-primary' : 'pos-btn pos-btn-secondary'" class="text-sm">Pagadas ({{ $paid->count() }})</button>
        <button @click="tab = 'payments'" :class="tab === 'payments' ? 'pos-btn-primary' : 'pos-btn pos-btn-secondary'" class="text-sm">Pagos ({{ $payments->count() }})</button>
    </div>

    {{-- Pending invoices --}}
    <div x-show="tab === 'pending'" class="bg-white rounded-lg shadow overflow-hidden">
        <table class="pos-table">
            <thead>
                <tr><th>N.º</th><th>Fecha</th><th>Vence</th><th class="text-right">Total</th><th class="text-right">Saldo</th><th class="text-center">Estado</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($pending as $inv)
                    <tr>
                        <td class="font-medium">{{ $inv->invoice_number ?: '—' }}</td>
                        <td class="text-gray-500">{{ $inv->invoice_date->format('d/m/Y') }}</td>
                        <td class="text-gray-500">{{ $inv->due_date?->format('d/m/Y') ?: '—' }}</td>
                        <td class="text-right">{{ format_cop($inv->total_amount) }}</td>
                        <td class="text-right font-semibold text-red-600">{{ format_cop($inv->balance) }}</td>
                        <td class="text-center">
                            <span class="badge-{{ strtolower($inv->status) }}">{{ $inv->status }}</span>
                        </td>
                        <td class="text-right">
                            <button @click="payInvoice = (payInvoice === {{ $inv->id }} ? null : {{ $inv->id }})" class="pos-btn-link">Abonar</button>
                        </td>
                    </tr>
                    @if($inv->items->isNotEmpty())
                        <tr class="text-xs text-gray-500">
                            <td colspan="7" class="pt-0 pb-2 pl-4">
                                @foreach($inv->items as $it)
                                    <div class="flex justify-between max-w-md">
                                        <span>{{ $it->formatted_quantity }} · {{ $it->description }}</span>
                                        <span class="tabular-nums">{{ format_cop($it->line_total) }}</span>
                                    </div>
                                @endforeach
                            </td>
                        </tr>
                    @endif
                    <tr x-show="payInvoice === {{ $inv->id }}" x-cloak class="bg-yellow-50">
                        <td colspan="7" class="p-3">
                            <form method="POST" action="{{ route('supplier-invoices.payments', $inv) }}"
                                  @submit="$refs.subkey{{ $inv->id }}.value = crypto.randomUUID()"
                                  class="flex flex-wrap gap-2 items-end">
                                @csrf
                                <input type="hidden" name="submission_key" x-ref="subkey{{ $inv->id }}">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($methods as $key => $label)
                                        <label class="cursor-pointer">
                                            <input type="radio" name="method" value="{{ $key }}" class="hidden peer" @if($loop->first) checked @endif>
                                            <span class="px-2 py-1 rounded-full text-xs border peer-checked:bg-blue-600 peer-checked:text-white peer-checked:border-blue-600">{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <input type="number" step="1" min="1" max="{{ (int) round((float) $inv->balance) }}" name="amount"
                                       placeholder="Monto" data-keyboard="numeric" class="border rounded px-3 py-2 text-sm w-32" required>
                                <input type="text" name="notes" placeholder="Notas" data-keyboard="text" class="border rounded px-3 py-2 text-sm flex-1 min-w-[8rem]">
                                <button class="pos-btn pos-btn-success text-sm">Registrar abono</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center py-8 text-gray-400">Sin facturas pendientes.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paid invoices --}}
    <div x-show="tab === 'paid'" x-cloak class="bg-white rounded-lg shadow overflow-hidden">
        <table class="pos-table">
            <thead>
                <tr><th>N.º</th><th>Fecha</th><th class="text-right">Total</th><th class="text-center">Estado</th></tr>
            </thead>
            <tbody>
                @forelse($paid as $inv)
                    <tr>
                        <td class="font-medium">{{ $inv->invoice_number ?: '—' }}</td>
                        <td class="text-gray-500">{{ $inv->invoice_date->format('d/m/Y') }}</td>
                        <td class="text-right">{{ format_cop($inv->total_amount) }}</td>
                        <td class="text-center"><span class="badge-paid">PAID</span></td>
                    </tr>
                    @if($inv->items->isNotEmpty())
                        <tr class="text-xs text-gray-500">
                            <td colspan="4" class="pt-0 pb-2 pl-4">
                                @foreach($inv->items as $it)
                                    <div class="flex justify-between max-w-md">
                                        <span>{{ $it->formatted_quantity }} · {{ $it->description }}</span>
                                        <span class="tabular-nums">{{ format_cop($it->line_total) }}</span>
                                    </div>
                                @endforeach
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="4" class="text-center py-8 text-gray-400">Sin facturas pagadas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Payments history --}}
    <div x-show="tab === 'payments'" x-cloak class="bg-white rounded-lg shadow overflow-hidden">
        <table class="pos-table">
            <thead>
                <tr><th>Fecha</th><th>Método</th><th>Factura</th><th class="text-right">Monto</th><th>Notas</th></tr>
            </thead>
            <tbody>
                @forelse($payments as $p)
                    <tr>
                        <td class="text-gray-500">{{ $p->paid_at->setTimezone('America/Bogota')->format('d/m/Y H:i') }}</td>
                        <td>{{ $p->method_label }}</td>
                        <td class="text-gray-500">{{ $p->supplier_invoice_id ? ($p->invoice->invoice_number ?: '#'.$p->supplier_invoice_id) : 'FIFO' }}</td>
                        <td class="text-right font-semibold">{{ format_cop($p->amount) }}</td>
                        <td class="text-gray-500 text-sm">{{ $p->notes ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center py-8 text-gray-400">Sin pagos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>{{-- end x-data --}}

<script>
function supplierDetail() {
    return {
        tab: 'pending',
        payInvoice: null,
    };
}
</script>

@include('partials._supplier-invoice-modal')
@endsection
