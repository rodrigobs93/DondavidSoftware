@extends('layouts.app')
@section('title', 'Factura #' . $invoice->consecutive)

@section('content')
<div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="text-xl font-bold text-gray-800">Factura #{{ $invoice->consecutive }}</h1>
        <p class="text-sm text-gray-500">{{ $invoice->invoice_date->format('d/m/Y') }} —
            {{ $invoice->createdBy->name }}</p>
    </div>
    <div class="flex gap-2">
        <form method="POST" action="{{ route('invoices.reprint', $invoice) }}">
            @csrf
            <button class="pos-btn-secondary">🖨 Reimprimir</button>
        </form>
        <a href="{{ route('invoices.index') }}" class="pos-btn-secondary">← Facturas</a>
    </div>
</div>

<div class="grid md:grid-cols-2 gap-6">
    {{-- Invoice details --}}
    <div class="space-y-4">
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="font-semibold text-gray-700 mb-3">Detalle</h2>

            {{-- Status badge --}}
            <div class="flex items-center gap-3 mb-3">
                <span @class([
                    'badge-paid'    => $invoice->isPaid(),
                    'badge-partial' => $invoice->isPartial(),
                    'badge-pending' => $invoice->isPending(),
                ])>
                    {{ match($invoice->status) {
                        'PAID' => 'PAGADO',
                        'PARTIAL' => 'PARCIAL',
                        default => 'PENDIENTE'
                    } }}
                </span>
                <span class="text-sm px-2 py-0.5 rounded-full text-xs font-semibold
                    {{ $invoice->fe_status === 'ISSUED' ? 'bg-green-100 text-green-800' :
                       ($invoice->fe_status === 'PENDING' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600') }}">
                    {{ $invoice->fe_label }}
                </span>
            </div>

            {{-- Customer --}}
            <div class="text-sm space-y-1">
                <div><span class="text-gray-500">Cliente:</span>
                    <strong>{{ $invoice->customer->name }}</strong>
                    @if($invoice->customer->doc_label)
                        <span class="text-gray-500">({{ $invoice->customer->doc_label }})</span>
                    @endif
                </div>
            </div>

            {{-- Items table --}}
            <table class="w-full text-sm mt-4">
                <thead>
                    <tr class="border-b text-gray-500 text-xs uppercase">
                        <th class="text-left pb-2">Producto</th>
                        <th class="text-right pb-2">Cant.</th>
                        <th class="text-right pb-2">P.Unit</th>
                        <th class="text-right pb-2">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->items as $item)
                    <tr class="border-b last:border-0">
                        <td class="py-2">{{ $item->product_name_snapshot }}</td>
                        <td class="py-2 text-right text-gray-600">{{ $item->formatted_quantity }}</td>
                        <td class="py-2 text-right">${{ number_format($item->unit_price, 0, ',', '.') }}</td>
                        <td class="py-2 text-right font-semibold">${{ number_format($item->line_total, 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="text-sm">
                        <td colspan="3" class="pt-2 text-right text-gray-600">Subtotal</td>
                        <td class="pt-2 text-right">${{ number_format($invoice->subtotal, 0, ',', '.') }}</td>
                    </tr>
                    @if($invoice->delivery_fee > 0)
                    <tr class="text-sm">
                        <td colspan="3" class="text-right text-gray-600">Domicilio</td>
                        <td class="text-right">${{ number_format($invoice->delivery_fee, 0, ',', '.') }}</td>
                    </tr>
                    @endif
                    <tr class="font-bold text-base border-t">
                        <td colspan="3" class="pt-2 text-right">TOTAL</td>
                        <td class="pt-2 text-right text-green-700">${{ number_format($invoice->total, 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Payments --}}
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="font-semibold text-gray-700 mb-3">Pagos</h2>
            @foreach($invoice->payments as $payment)
            <div class="flex justify-between text-sm py-1 border-b last:border-0">
                <span class="text-gray-600">{{ \App\Models\Payment::$methods[$payment->method] ?? $payment->method }}</span>
                <span class="font-semibold">${{ number_format($payment->amount, 0, ',', '.') }}</span>
            </div>
            @endforeach
            <div class="flex justify-between text-sm mt-2 pt-2 border-t">
                <span class="text-gray-600">Total pagado</span>
                <span>${{ number_format($invoice->paid_amount, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between font-bold mt-1"
                 style="{{ $invoice->balance > 0 ? 'color:#b45309' : 'color:#15803d' }}">
                <span>Saldo</span>
                <span>${{ number_format($invoice->balance, 0, ',', '.') }}</span>
            </div>
        </div>
    </div>

    {{-- Right column: FE + Cartera + Print --}}
    <div class="space-y-4">

        {{-- FE Mark issued --}}
        @if($invoice->fe_status === 'PENDING' && auth()->user()->isAdmin())
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h2 class="font-semibold text-blue-800 mb-2">Marcar FE como Emitida</h2>
            <form method="POST" action="{{ route('invoices.fe-mark-issued', $invoice) }}">
                @csrf
                <div class="flex gap-2">
                    <input type="text" name="fe_reference" placeholder="CUFE o referencia DIAN..."
                        class="flex-1 border rounded px-3 py-2 text-sm" required>
                    <button class="pos-btn-primary">Marcar Emitida</button>
                </div>
                @error('fe_reference')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </form>
        </div>
        @elseif($invoice->fe_status === 'ISSUED')
        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <h2 class="font-semibold text-green-800 mb-1">Factura Electrónica Emitida</h2>
            <p class="text-sm text-green-700">Ref: <strong>{{ $invoice->fe_reference }}</strong></p>
            @if($invoice->fe_issued_at)
                <p class="text-xs text-green-600 mt-1">{{ $invoice->fe_issued_at->setTimezone('America/Bogota')->format('d/m/Y H:i') }}</p>
            @endif
        </div>
        @endif

        {{-- Add payment (cartera) --}}
        @if($invoice->balance > 0)
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <h2 class="font-semibold text-yellow-800 mb-3">
                Registrar Abono
                <span class="text-sm text-yellow-600 font-normal">
                    (Saldo: ${{ number_format($invoice->balance, 0, ',', '.') }})
                </span>
            </h2>
            <form method="POST" action="{{ route('cartera.payments', $invoice) }}">
                @csrf
                <div class="grid grid-cols-2 gap-2 mb-2">
                    <select name="method" class="border rounded px-2 py-2 text-sm">
                        @foreach(\App\Models\Payment::$methods as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="amount" placeholder="Monto"
                        min="0.01" step="0.01" max="{{ $invoice->balance }}"
                        class="border rounded px-2 py-2 text-sm" required>
                </div>
                <input type="text" name="notes" placeholder="Notas (opcional)"
                    class="w-full border rounded px-2 py-2 text-sm mb-2">
                <button class="w-full pos-btn-success">Registrar Abono</button>
                @error('amount')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </form>
        </div>
        @endif

        {{-- Print job status --}}
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="font-semibold text-gray-700 mb-2">Cola de Impresión</h2>
            @foreach($invoice->printJobs as $job)
            <div class="flex justify-between text-xs py-1">
                <span class="text-gray-500">Job #{{ $job->id }}</span>
                <span class="px-2 py-0.5 rounded-full font-semibold
                    {{ match($job->status) {
                        'PRINTED' => 'bg-green-100 text-green-700',
                        'FAILED' => 'bg-red-100 text-red-700',
                        'PRINTING' => 'bg-yellow-100 text-yellow-700',
                        default => 'bg-gray-100 text-gray-600'
                    } }}">
                    {{ $job->status }}
                </span>
            </div>
            @if($job->error_message)
                <p class="text-red-500 text-xs mt-0.5">{{ $job->error_message }}</p>
            @endif
            @endforeach
            @if($invoice->printJobs->isEmpty())
                <p class="text-gray-400 text-xs">Sin trabajos de impresión.</p>
            @endif
        </div>
    </div>
</div>
@endsection
