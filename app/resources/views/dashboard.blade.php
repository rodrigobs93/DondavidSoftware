@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    {{-- Today's sales --}}
    <div class="bg-white rounded-lg shadow p-4">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Ventas hoy</p>
        <p class="text-2xl font-bold text-gray-800 mt-1">{{ $todayStats->count }}</p>
        <p class="text-sm text-green-600 font-semibold">
            ${{ number_format($todayStats->total_sum, 0, ',', '.') }}
        </p>
    </div>

    {{-- Cartera --}}
    <a href="{{ route('cartera.index') }}" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Cartera pendiente</p>
        <p class="text-2xl font-bold text-yellow-600 mt-1">{{ $carteraCount }}</p>
        <p class="text-sm text-gray-500">facturas con saldo</p>
    </a>

    {{-- FE Pending --}}
    <a href="{{ route('fe-pending.index') }}" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow">
        <p class="text-xs text-gray-500 uppercase tracking-wide">FE pendiente</p>
        <p class="text-2xl font-bold text-blue-600 mt-1">{{ $fePendingCount }}</p>
        <p class="text-sm text-gray-500">por emitir</p>
    </a>

    {{-- Print errors --}}
    <div class="bg-white rounded-lg shadow p-4 {{ $printErrors > 0 ? 'border-2 border-red-400' : '' }}">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Errores impresión</p>
        <p class="text-2xl font-bold {{ $printErrors > 0 ? 'text-red-600' : 'text-gray-400' }} mt-1">
            {{ $printErrors }}
        </p>
        <p class="text-sm text-gray-500">últimas 24h</p>
    </div>
</div>

{{-- Print Worker down warning --}}
@if($workerDown)
<div class="mb-4 p-3 bg-orange-50 border border-orange-300 text-orange-800 rounded-lg flex items-start gap-3">
    <span class="text-xl leading-none mt-0.5">⚠️</span>
    <div class="text-sm">
        <strong>Servicio de impresión detenido.</strong>
        Hay trabajos de impresión en cola que no se están procesando.
        Ejecute en la terminal del servidor:
        <code class="block mt-1 bg-orange-100 px-2 py-1 rounded font-mono text-xs">php artisan app:print-worker</code>
    </div>
</div>
@endif

{{-- Quick actions --}}
<div class="grid md:grid-cols-3 gap-4 mb-6">
    <a href="{{ route('sales.create') }}"
       class="bg-green-600 text-white rounded-lg p-5 text-center hover:bg-green-700 transition-colors shadow">
        <div class="text-3xl mb-2">🛒</div>
        <div class="font-bold text-lg">Nueva Venta</div>
        <div class="text-green-200 text-sm">Crear factura</div>
    </a>

    <a href="{{ route('cartera.index') }}"
       class="bg-yellow-500 text-white rounded-lg p-5 text-center hover:bg-yellow-600 transition-colors shadow">
        <div class="text-3xl mb-2">💰</div>
        <div class="font-bold text-lg">Cartera</div>
        <div class="text-yellow-100 text-sm">Registrar abonos</div>
    </a>

    <a href="{{ route('invoices.index') }}"
       class="bg-blue-600 text-white rounded-lg p-5 text-center hover:bg-blue-700 transition-colors shadow">
        <div class="text-3xl mb-2">📋</div>
        <div class="font-bold text-lg">Facturas</div>
        <div class="text-blue-200 text-sm">Historial de ventas</div>
    </a>
</div>

{{-- LAN URL info --}}
<div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
    <h3 class="text-sm font-semibold text-blue-800 mb-1">Acceso desde celular (misma red WiFi)</h3>
    <p class="font-mono text-blue-700 text-lg">http://{{ $lanIp }}:8000</p>
    <p class="text-xs text-blue-500 mt-1">Comparte esta URL con otros dispositivos en la red local.</p>
</div>
@endsection
