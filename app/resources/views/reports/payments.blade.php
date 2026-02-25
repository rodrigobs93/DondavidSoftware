@extends('layouts.app')
@section('title', 'Reporte de Pagos')

@section('content')
<h1 class="text-xl font-bold text-gray-800 mb-4">Reporte de Pagos</h1>

<form method="GET" class="flex gap-2 mb-6 items-end">
    <div>
        <label class="block text-xs text-gray-600 mb-1">Desde</label>
        <input type="date" name="date_from" value="{{ $dateFrom }}" class="border rounded px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-xs text-gray-600 mb-1">Hasta</label>
        <input type="date" name="date_to" value="{{ $dateTo }}" class="border rounded px-3 py-2 text-sm">
    </div>
    <button class="pos-btn-primary">Ver Reporte</button>
</form>

<div class="grid md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4 md:col-span-2">
        <h2 class="font-semibold text-gray-700 mb-3">Pagos recibidos por método</h2>
        <table class="w-full text-sm">
            <thead class="border-b">
                <tr>
                    <th class="text-left py-2 text-gray-600">Método</th>
                    <th class="text-right py-2 text-gray-600">Transacciones</th>
                    <th class="text-right py-2 text-gray-600">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($byMethod as $row)
                <tr>
                    <td class="py-2 font-medium">
                        {{ \App\Models\Payment::$methods[$row->method] ?? $row->method }}
                    </td>
                    <td class="py-2 text-right text-gray-500">{{ $row->count }}</td>
                    <td class="py-2 text-right font-semibold text-green-700">
                        ${{ number_format($row->total, 0, ',', '.') }}
                    </td>
                </tr>
                @empty
                <tr><td colspan="3" class="py-4 text-center text-gray-400">Sin pagos en este período.</td></tr>
                @endforelse
            </tbody>
            @if($byMethod->count() > 0)
            <tfoot class="border-t font-bold">
                <tr>
                    <td class="pt-2">Total recaudado</td>
                    <td class="pt-2 text-right">{{ $byMethod->sum('count') }}</td>
                    <td class="pt-2 text-right text-green-700">
                        ${{ number_format($byMethod->sum('total'), 0, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>

    <div class="space-y-3">
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Ventas en período</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">
                ${{ number_format($totalSales, 0, ',', '.') }}
            </p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Cartera total pendiente</p>
            <p class="text-2xl font-bold text-yellow-700 mt-1">
                ${{ number_format($totalBalance, 0, ',', '.') }}
            </p>
        </div>
    </div>
</div>
@endsection
