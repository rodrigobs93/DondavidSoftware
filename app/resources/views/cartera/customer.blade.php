@extends('layouts.app')
@section('title', 'Cartera — ' . $customer->name)

@section('content')
@php
    $netAmount = bcsub($totalDebt, (string) $customer->credit_balance, 2);
    $hasCredit = bccomp((string) $customer->credit_balance, '0', 2) > 0;
    $fmt = fn($v) => '$' . number_format((float) $v, 0, ',', '.');
@endphp

{{-- Back link --}}
<a href="{{ route('cartera.index') }}"
   class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-4">
    ← Volver a Cartera
</a>

{{-- Customer summary card --}}
<div class="bg-white rounded-lg shadow p-5 mb-5">
    <h1 class="text-xl font-bold text-gray-800">{{ $customer->name }}</h1>
    @if($customer->business_name)
        <div class="text-gray-500 text-sm mb-3">{{ $customer->business_name }}</div>
    @endif

    <div class="grid grid-cols-3 gap-4 mt-3">
        <div class="text-center p-3 bg-red-50 rounded-lg border border-red-100">
            <div class="text-xs text-red-500 font-medium uppercase tracking-wide mb-1">Deuda total</div>
            <div class="text-2xl font-bold text-red-700 font-mono">{{ $fmt($totalDebt) }}</div>
        </div>
        <div class="text-center p-3 {{ $hasCredit ? 'bg-green-50 border-green-100' : 'bg-gray-50 border-gray-100' }} rounded-lg border">
            <div class="text-xs {{ $hasCredit ? 'text-green-500' : 'text-gray-400' }} font-medium uppercase tracking-wide mb-1">Saldo a favor</div>
            <div class="text-2xl font-bold {{ $hasCredit ? 'text-green-700' : 'text-gray-400' }} font-mono">
                {{ $fmt($customer->credit_balance) }}
            </div>
        </div>
        <div class="text-center p-3 {{ bccomp($netAmount,'0',2) > 0 ? 'bg-yellow-50 border-yellow-100' : 'bg-green-50 border-green-100' }} rounded-lg border">
            <div class="text-xs {{ bccomp($netAmount,'0',2) > 0 ? 'text-yellow-600' : 'text-green-600' }} font-medium uppercase tracking-wide mb-1">Neto a cobrar</div>
            <div class="text-2xl font-bold {{ bccomp($netAmount,'0',2) > 0 ? 'text-yellow-700' : 'text-green-700' }} font-mono">
                {{ $fmt($netAmount) }}
            </div>
        </div>
    </div>
</div>

{{-- Pending invoices --}}
@if($invoices->isNotEmpty())
<div class="bg-white rounded-lg shadow mb-5">
    <div class="px-5 py-3 border-b">
        <h2 class="font-semibold text-gray-700">
            Facturas pendientes
            <span class="text-gray-400 font-normal text-sm">({{ $invoices->count() }})</span>
        </h2>
    </div>

    {{-- Mobile cards --}}
    <div class="sm:hidden divide-y divide-gray-100 p-4 space-y-3">
        @foreach($invoices as $invoice)
        <div x-data="{ showAbono: false }" class="pt-3 first:pt-0">
            <div class="flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('invoices.show', $invoice) }}"
                           class="font-mono font-bold text-blue-600 hover:text-blue-800">
                            #{{ $invoice->consecutive }}
                        </a>
                        <span class="text-sm text-gray-500">
                            {{ $invoice->invoice_date->format('d/m/Y') }}
                        </span>
                        <span class="{{ $invoice->status === 'PARTIAL' ? 'badge-partial' : 'badge-pending' }}">
                            {{ $invoice->status === 'PARTIAL' ? 'PARCIAL' : 'PENDIENTE' }}
                        </span>
                    </div>
                    <div class="flex gap-3 text-sm mt-1">
                        <span class="text-gray-500">Total: <strong>{{ $fmt($invoice->total) }}</strong></span>
                        <span class="text-green-600">Pagado: <strong>{{ $fmt($invoice->paid_amount) }}</strong></span>
                        <span class="text-yellow-700 font-semibold">Saldo: {{ $fmt($invoice->balance) }}</span>
                    </div>
                </div>
                <button type="button" @click="showAbono = !showAbono"
                        class="pos-btn pos-btn-success text-sm shrink-0">
                    <span x-show="!showAbono">+ Abonar</span>
                    <span x-show="showAbono">Cancelar</span>
                </button>
            </div>

            <div x-show="showAbono" x-cloak class="mt-3 pt-3 border-t border-gray-100">
                <form method="POST" action="{{ route('cartera.payments', $invoice) }}">
                    @csrf
                    <div class="space-y-2">
                        <select name="method" class="border rounded px-3 py-2.5 text-base w-full">
                            @foreach($paymentMethods as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <input type="number" name="amount" placeholder="Monto a abonar"
                               min="0.01" step="0.01" max="{{ $invoice->balance }}"
                               class="border rounded px-3 py-2.5 text-base w-full" required>
                        <input type="text" name="notes" placeholder="Notas (opcional)"
                               class="border rounded px-3 py-2.5 text-base w-full">
                        <button class="w-full pos-btn pos-btn-success justify-center py-3">
                            Registrar abono — {{ $fmt($invoice->balance) }} máx.
                        </button>
                    </div>
                    @error('amount')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </form>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Desktop table --}}
    <div class="hidden sm:block overflow-x-auto">
        <table class="pos-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Fecha</th>
                    <th class="text-right">Total</th>
                    <th class="text-right">Pagado</th>
                    <th class="text-right">Saldo</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoices as $invoice)
                <tr x-data="{ showAbono: false }">
                    <td>
                        <a href="{{ route('invoices.show', $invoice) }}"
                           class="font-mono font-bold text-blue-600 hover:text-blue-800">
                            #{{ $invoice->consecutive }}
                        </a>
                    </td>
                    <td class="text-sm text-gray-600">{{ $invoice->invoice_date->format('d/m/Y') }}</td>
                    <td class="text-right font-mono text-sm">{{ $fmt($invoice->total) }}</td>
                    <td class="text-right font-mono text-sm text-green-700">{{ $fmt($invoice->paid_amount) }}</td>
                    <td class="text-right font-mono text-sm font-semibold text-yellow-700">{{ $fmt($invoice->balance) }}</td>
                    <td>
                        <span class="{{ $invoice->status === 'PARTIAL' ? 'badge-partial' : 'badge-pending' }}">
                            {{ $invoice->status === 'PARTIAL' ? 'PARCIAL' : 'PENDIENTE' }}
                        </span>
                    </td>
                    <td class="text-right">
                        <button type="button" @click="showAbono = !showAbono"
                                class="pos-btn pos-btn-success text-sm">
                            <span x-show="!showAbono">+ Abonar</span>
                            <span x-show="showAbono">Cancelar</span>
                        </button>
                    </td>
                </tr>
                <tr x-show="showAbono" x-cloak>
                    <td colspan="7" class="bg-yellow-50 px-4 py-3">
                        <form method="POST" action="{{ route('cartera.payments', $invoice) }}"
                              class="flex gap-2 flex-wrap items-end">
                            @csrf
                            <select name="method" class="border rounded px-3 py-2.5 text-base">
                                @foreach($paymentMethods as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <input type="number" name="amount" placeholder="Monto"
                                   min="0.01" step="0.01" max="{{ $invoice->balance }}"
                                   class="border rounded px-3 py-2.5 text-base w-36" required>
                            <input type="text" name="notes" placeholder="Notas (opcional)"
                                   class="border rounded px-3 py-2.5 text-base flex-1">
                            <button class="pos-btn pos-btn-success">Registrar abono</button>
                        </form>
                        @error('amount')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@else
<div class="bg-white rounded-lg shadow p-6 text-center text-gray-400 mb-5">
    No hay facturas pendientes para este cliente.
</div>
@endif

{{-- Consolidated payment form --}}
<div class="bg-white rounded-lg shadow p-5">
    <h2 class="font-semibold text-gray-700 mb-1">Registrar pago consolidado</h2>
    <p class="text-sm text-gray-500 mb-4">
        El pago se distribuirá automáticamente desde la factura más antigua (FIFO).
        @if($hasCredit)
            <span class="text-green-700 font-medium">
                Este cliente tiene {{ $fmt($customer->credit_balance) }} en saldo a favor.
            </span>
        @endif
    </p>

    <form method="POST" action="{{ route('cartera.customer.payments', $customer) }}"
          x-data="{ method: 'CASH' }">
        @csrf

        {{-- Payment method chips --}}
        <div class="flex gap-2 flex-wrap mb-4">
            @php
            $chipClasses = [
                'CASH'      => 'bg-green-100 text-green-700 border-green-300 data-[active]:bg-green-500 data-[active]:text-white data-[active]:border-green-500',
                'CARD'      => 'bg-blue-100 text-blue-700 border-blue-300 data-[active]:bg-blue-500 data-[active]:text-white data-[active]:border-blue-500',
                'NEQUI'     => 'bg-pink-100 text-pink-700 border-pink-300 data-[active]:bg-pink-500 data-[active]:text-white data-[active]:border-pink-500',
                'DAVIPLATA' => 'bg-red-100 text-red-700 border-red-300 data-[active]:bg-red-500 data-[active]:text-white data-[active]:border-red-500',
                'BREB'      => 'bg-purple-100 text-purple-700 border-purple-300 data-[active]:bg-purple-500 data-[active]:text-white data-[active]:border-purple-500',
            ];
            @endphp
            @foreach($paymentMethods as $key => $label)
            <button type="button"
                    @click="method = '{{ $key }}'"
                    :data-active="method === '{{ $key }}' ? '' : null"
                    class="px-4 py-2.5 rounded-full text-sm font-semibold border transition-colors {{ $chipClasses[$key] }}">
                {{ $label }}
            </button>
            @endforeach
        </div>
        <input type="hidden" name="method" :value="method">

        <div class="flex gap-3 flex-wrap items-end">
            <div class="flex-1 min-w-48">
                <label class="block text-sm font-medium text-gray-700 mb-1">Monto del pago</label>
                <div class="flex items-center gap-1">
                    <span class="text-gray-500">$</span>
                    <input type="number" name="amount"
                           placeholder="0"
                           min="0.01" step="500"
                           class="border rounded px-3 py-3 text-base flex-1 focus:outline-none focus:ring-2 focus:ring-blue-400"
                           required>
                </div>
            </div>
            <div class="flex-1 min-w-48">
                <label class="block text-sm font-medium text-gray-700 mb-1">Notas (opcional)</label>
                <input type="text" name="notes" placeholder="Ej: pago semana 13"
                       class="border rounded px-3 py-3 text-base w-full focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>
        </div>

        @error('amount')
            <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
        @enderror

        <button type="submit"
                class="mt-4 w-full pos-btn pos-btn-success justify-center py-4 text-lg">
            Registrar pago consolidado
        </button>
    </form>
</div>
@endsection
