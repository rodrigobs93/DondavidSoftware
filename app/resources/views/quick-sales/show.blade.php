@extends('layouts.app')
@section('title', 'Recibo ' . $quickSale->receipt_number)

@section('content')
<div class="max-w-md mx-auto">

    <div class="flex items-center gap-3 mb-4">
        <a href="{{ route('reports.payments') }}" class="text-gray-400 hover:text-gray-600 text-sm">← Validación</a>
        <h1 class="text-xl font-bold text-gray-800">Venta Rápida</h1>
    </div>

    <div class="bg-white rounded-xl shadow p-6 space-y-4">

        {{-- Receipt number --}}
        <div class="text-center">
            <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Recibo</p>
            <p class="text-4xl font-mono font-bold text-gray-900">{{ $quickSale->receipt_number }}</p>
        </div>

        <hr>

        {{-- Details --}}
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between">
                <dt class="text-gray-500">Fecha</dt>
                <dd class="font-semibold">{{ $quickSale->sale_date->format('d/m/Y') }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">Total</dt>
                <dd class="font-bold text-lg">${{ number_format($quickSale->total_amount, 0, ',', '.') }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">Método</dt>
                <dd class="font-semibold">{{ \App\Models\Payment::$methods[$quickSale->payment_method] ?? $quickSale->payment_method }}</dd>
            </div>
            @if($quickSale->payment_method === 'CASH' && $quickSale->cash_received !== null)
                <div class="flex justify-between">
                    <dt class="text-gray-500">Recibido</dt>
                    <dd>${{ number_format($quickSale->cash_received, 0, ',', '.') }}</dd>
                </div>
                <div class="flex justify-between text-green-700">
                    <dt class="font-semibold">Cambio</dt>
                    <dd class="font-bold">${{ number_format($quickSale->change_amount, 0, ',', '.') }}</dd>
                </div>
            @endif
            @if($quickSale->notes)
                <div class="flex justify-between">
                    <dt class="text-gray-500">Nota</dt>
                    <dd class="text-right max-w-xs">{{ $quickSale->notes }}</dd>
                </div>
            @endif
            <div class="flex justify-between">
                <dt class="text-gray-500">Registrado por</dt>
                <dd>{{ $quickSale->createdBy->name ?? '—' }}</dd>
            </div>
        </dl>

        <hr>

        {{-- Payment verification status --}}
        @if($quickSale->payment)
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-500">Estado pago</span>
                @if($quickSale->payment->verified)
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700"
                          title="Verificado: {{ $quickSale->payment->verified_at?->format('d/m/Y H:i') }}">
                        ✓ Verificado
                    </span>
                @else
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">
                        Pendiente
                    </span>
                @endif
            </div>
        @endif

        {{-- Reprint button --}}
        <form method="POST" action="{{ route('quick-sales.print', $quickSale) }}">
            @csrf
            <button type="submit" class="w-full pos-btn-secondary justify-center">
                🖨 Reimprimir
            </button>
        </form>

    </div>
</div>
@endsection
