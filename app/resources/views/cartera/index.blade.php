@extends('layouts.app')
@section('title', 'Cartera')

@section('content')
<div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="text-xl font-bold text-gray-800">Cartera Pendiente</h1>
        <p class="text-sm text-gray-500">
            Total saldo: <strong class="text-yellow-700">${{ number_format($totalBalance, 0, ',', '.') }}</strong>
        </p>
    </div>
</div>

<form method="GET" class="flex gap-2 mb-4">
    <input type="text" name="customer" value="{{ request('customer') }}" placeholder="Filtrar por cliente..."
        class="border rounded px-3 py-2 text-sm w-48">
    <button class="pos-btn-secondary">Filtrar</button>
    <a href="{{ route('cartera.index') }}" class="pos-btn-secondary">Limpiar</a>
</form>

<div class="space-y-3">
    @forelse($invoices as $invoice)
    <div class="bg-white rounded-lg shadow p-4" x-data="{ showAbono: false }">
        <div class="flex items-start justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('invoices.show', $invoice) }}"
                       class="font-mono font-bold text-blue-600 hover:text-blue-800">
                        #{{ $invoice->consecutive }}
                    </a>
                    <span class="text-sm text-gray-500">{{ $invoice->invoice_date->format('d/m/Y') }}</span>
                    <span class="badge-{{ strtolower($invoice->status) }}">
                        {{ $invoice->status === 'PARTIAL' ? 'PARCIAL' : 'PENDIENTE' }}
                    </span>
                </div>
                <p class="text-sm text-gray-700 mt-0.5">{{ $invoice->customer->name }}</p>
                <div class="flex gap-4 text-sm mt-1">
                    <span class="text-gray-500">Total: ${{ number_format($invoice->total, 0, ',', '.') }}</span>
                    <span class="text-green-600">Pagado: ${{ number_format($invoice->paid_amount, 0, ',', '.') }}</span>
                    <span class="text-yellow-700 font-semibold">Saldo: ${{ number_format($invoice->balance, 0, ',', '.') }}</span>
                </div>
            </div>
            <button type="button" @click="showAbono = !showAbono"
                class="pos-btn-success text-sm">
                <span x-show="!showAbono">+ Abonar</span>
                <span x-show="showAbono">Cancelar</span>
            </button>
        </div>

        <div x-show="showAbono" x-cloak class="mt-3 pt-3 border-t">
            <form method="POST" action="{{ route('cartera.payments', $invoice) }}">
                @csrf
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                    <select name="method" class="border rounded px-2 py-2 text-sm">
                        @foreach(\App\Models\Payment::$methods as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="amount" placeholder="Monto a abonar"
                        min="0.01" step="0.01" max="{{ $invoice->balance }}"
                        class="border rounded px-2 py-2 text-sm" required>
                    <input type="text" name="notes" placeholder="Notas (opcional)"
                        class="border rounded px-2 py-2 text-sm">
                    <button class="pos-btn-success w-full">Registrar</button>
                </div>
                @error('amount') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </form>
        </div>
    </div>
    @empty
    <div class="bg-white rounded-lg shadow p-8 text-center text-gray-400">
        No hay facturas con saldo pendiente.
    </div>
    @endforelse
</div>

<div class="mt-4">{{ $invoices->links() }}</div>
@endsection
