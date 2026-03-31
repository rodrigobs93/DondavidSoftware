@extends('layouts.app')
@section('title', 'Cartera — ' . $customer->name)

@section('content')
@php
    $fmt = fn($v) => '$' . number_format((int) round((float) $v), 0, ',', '.');
@endphp

{{-- Back link --}}
<a href="{{ route('cartera.index') }}"
   class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-4">
    ← Volver a Cartera
</a>

<div x-data="carteraCustomer()" x-cloak>

{{-- Flash messages --}}
@if(session('success'))
<div class="mb-4 bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">
    {{ session('success') }}
</div>
@endif

{{-- Customer summary card --}}
<div class="bg-white rounded-lg shadow p-5 mb-5">
    <div class="flex items-start justify-between gap-3 mb-4">
        <div>
            <h1 class="text-xl font-bold text-gray-800">{{ $customer->name }}</h1>
            @if($customer->business_name)
                <div class="text-gray-500 text-sm">{{ $customer->business_name }}</div>
            @endif
        </div>
        {{-- Print button — carries current group param --}}
        <form method="POST"
              action="{{ route('cartera.customer.print', $customer) }}?group={{ $group }}"
              class="shrink-0" x-show="invoices.length > 0">
            @csrf
            <button type="submit" class="pos-btn pos-btn-secondary text-sm gap-1.5">
                🖨 Imprimir cobro
            </button>
        </form>
    </div>

    {{-- Summary: stacked on mobile, 3-col on sm+ --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="flex sm:flex-col items-center justify-between sm:justify-start sm:text-center p-3 bg-red-50 rounded-lg border border-red-100">
            <div class="text-xs text-red-500 font-medium uppercase tracking-wide">Deuda total</div>
            <div class="text-2xl font-bold text-red-700 font-mono" x-text="fmt(totalDebt)"></div>
        </div>
        <div class="flex sm:flex-col items-center justify-between sm:justify-start sm:text-center p-3 rounded-lg border"
             :class="creditBalance > 0 ? 'bg-green-50 border-green-100' : 'bg-gray-50 border-gray-100'">
            <div class="text-xs font-medium uppercase tracking-wide"
                 :class="creditBalance > 0 ? 'text-green-500' : 'text-gray-400'">Saldo a favor</div>
            <div class="text-2xl font-bold font-mono"
                 :class="creditBalance > 0 ? 'text-green-700' : 'text-gray-400'"
                 x-text="fmt(creditBalance)"></div>
        </div>
        <div class="flex sm:flex-col items-center justify-between sm:justify-start sm:text-center p-3 rounded-lg border"
             :class="netAmount > 0 ? 'bg-yellow-50 border-yellow-100' : 'bg-green-50 border-green-100'">
            <div class="text-xs font-medium uppercase tracking-wide"
                 :class="netAmount > 0 ? 'text-yellow-600' : 'text-green-600'">Neto a cobrar</div>
            <div class="text-2xl font-bold font-mono"
                 :class="netAmount > 0 ? 'text-yellow-700' : 'text-green-700'"
                 x-text="fmt(netAmount)"></div>
        </div>
    </div>
</div>

{{-- Pending invoices --}}
<div class="bg-white rounded-lg shadow mb-5" x-show="invoices.length > 0">
    <div class="px-5 py-3 border-b flex items-center justify-between gap-3 flex-wrap">
        <h2 class="font-semibold text-gray-700">
            Facturas pendientes
            <span class="text-gray-400 font-normal text-sm" x-text="'(' + invoices.length + ')'"></span>
        </h2>

        {{-- Grouping toggle --}}
        <div class="flex items-center gap-1 text-sm">
            <span class="text-gray-400 mr-1 hidden sm:inline">Agrupar:</span>
            @foreach(['none' => 'Sin agrupar', 'day' => 'Por día', 'week' => 'Por semana'] as $key => $label)
                <a href="{{ request()->fullUrlWithQuery(['group' => $key]) }}"
                   class="px-3 py-1.5 rounded-full border text-sm font-medium transition-colors
                          {{ $group === $key
                              ? 'bg-blue-500 text-white border-blue-500'
                              : 'bg-white text-gray-600 border-gray-300 hover:border-blue-300' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- ── MOBILE cards ──────────────────────────────────────────────────── --}}
    <div class="sm:hidden p-4 space-y-3">
        <template x-for="section in sections" :key="section.label || 'all'">
            <div>
                {{-- Group label --}}
                <template x-if="section.label">
                    <div class="px-2 py-1.5 mb-2 bg-gray-50 rounded text-sm font-semibold text-gray-600 flex justify-between">
                        <span x-text="section.label"></span>
                        <span class="font-normal text-gray-400 text-xs"
                              x-text="section.invoices.length + ' factura' + (section.invoices.length !== 1 ? 's' : '') + ' — ' + fmt(section.invoices.reduce((s,i)=>s+i.balance,0))"></span>
                    </div>
                </template>

                <template x-for="inv in section.invoices" :key="inv.id">
                    <div class="pt-3 border-t border-gray-100 first:border-0 first:pt-0">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <a :href="'/invoices/' + inv.id"
                                       class="font-mono font-bold text-blue-600 hover:text-blue-800"
                                       x-text="'#' + inv.consecutive"></a>
                                    <span class="text-sm text-gray-500" x-text="inv.date"></span>
                                </div>
                                <div class="flex gap-3 text-sm mt-1">
                                    <span class="text-gray-500">Total: <strong x-text="fmt(inv.total)"></strong></span>
                                    <span class="text-green-600">Pagado: <strong x-text="fmt(inv.paid_amount)"></strong></span>
                                    <span class="text-yellow-700 font-semibold" x-text="'Saldo: ' + fmt(inv.balance)"></span>
                                </div>
                            </div>
                            <button type="button" @click="inv.showAbono = !inv.showAbono"
                                    class="pos-btn pos-btn-success text-sm shrink-0">
                                <span x-text="inv.showAbono ? 'Cancelar' : '+ Abonar'"></span>
                            </button>
                        </div>

                        {{-- Inline abono form --}}
                        <div x-show="inv.showAbono" x-cloak class="mt-3 pt-3 border-t border-gray-100">
                            <div class="space-y-2">
                                <select x-model="inv.method" class="border rounded px-3 py-2.5 text-base w-full">
                                    @foreach($paymentMethods as $k => $lbl)
                                        <option value="{{ $k }}">{{ $lbl }}</option>
                                    @endforeach
                                </select>
                                <input type="number" x-model="inv.abonoAmount"
                                       placeholder="Monto a abonar"
                                       min="1" step="1" :max="inv.balance"
                                       class="border rounded px-3 py-2.5 text-base w-full">
                                <input type="text" x-model="inv.notes"
                                       placeholder="Notas (opcional)"
                                       class="border rounded px-3 py-2.5 text-base w-full">
                                <div x-show="inv.abonoError" class="text-red-500 text-xs" x-text="inv.abonoError"></div>
                                <button type="button"
                                        @click="submitAbono(inv)"
                                        :disabled="saving"
                                        class="w-full pos-btn pos-btn-success justify-center py-3">
                                    <span x-show="!saving">Registrar abono</span>
                                    <span x-show="saving">Guardando…</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>

    {{-- ── DESKTOP table ─────────────────────────────────────────────────── --}}
    <div class="hidden sm:block overflow-x-auto">
        <template x-for="section in sections" :key="section.label || 'all'">
            <div>
                {{-- Group header row --}}
                <template x-if="section.label">
                    <div class="px-5 py-2 bg-gray-50 border-y text-sm font-semibold text-gray-600 flex items-center justify-between">
                        <span x-text="section.label"></span>
                        <span class="font-mono text-gray-500 text-xs font-normal"
                              x-text="section.invoices.length + ' factura' + (section.invoices.length !== 1 ? 's' : '') + ' — ' + fmt(section.invoices.reduce((s,i)=>s+i.balance,0))">
                        </span>
                    </div>
                </template>

                <table class="pos-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Fecha</th>
                            <th class="text-right">Total</th>
                            <th class="text-right">Pagado</th>
                            <th class="text-right">Saldo</th>
                            <th></th>
                        </tr>
                    </thead>

                    {{-- One <tbody> per invoice — single root in x-for, same as mobile's <div> pattern --}}
                    <template x-for="inv in section.invoices" :key="inv.id">
                        <tbody>
                            {{-- Data row --}}
                            <tr class="hover:bg-gray-50">
                                <td>
                                    <a :href="'/invoices/' + inv.id"
                                       class="font-mono font-bold text-blue-600 hover:text-blue-800"
                                       x-text="'#' + inv.consecutive"></a>
                                </td>
                                <td class="text-sm text-gray-600" x-text="inv.date"></td>
                                <td class="text-right font-mono text-sm" x-text="fmt(inv.total)"></td>
                                <td class="text-right font-mono text-sm text-green-700" x-text="fmt(inv.paid_amount)"></td>
                                <td class="text-right font-mono text-sm font-semibold text-yellow-700" x-text="fmt(inv.balance)"></td>
                                <td class="text-right">
                                    <button type="button" @click="inv.showAbono = !inv.showAbono"
                                            class="pos-btn pos-btn-success text-sm">
                                        <span x-text="inv.showAbono ? 'Cancelar' : '+ Abonar'"></span>
                                    </button>
                                </td>
                            </tr>
                            {{-- Inline abono row — inside same tbody so inv scope is guaranteed --}}
                            <tr x-show="inv.showAbono">
                                <td colspan="6" class="bg-yellow-50 px-4 py-3">
                                    <div class="flex gap-2 flex-wrap items-end">
                                        <select x-model="inv.method"
                                                class="border rounded px-3 py-2.5 text-base">
                                            @foreach($paymentMethods as $k => $lbl)
                                                <option value="{{ $k }}">{{ $lbl }}</option>
                                            @endforeach
                                        </select>
                                        <input type="number" x-model="inv.abonoAmount"
                                               placeholder="Monto" min="1" step="1" :max="inv.balance"
                                               class="border rounded px-3 py-2.5 text-base w-36">
                                        <input type="text" x-model="inv.notes"
                                               placeholder="Notas (opcional)"
                                               class="border rounded px-3 py-2.5 text-base flex-1">
                                        <button type="button"
                                                @click="submitAbono(inv)"
                                                :disabled="saving"
                                                class="pos-btn pos-btn-success">
                                            <span x-show="!saving">Registrar abono</span>
                                            <span x-show="saving">Guardando…</span>
                                        </button>
                                    </div>
                                    <div x-show="inv.abonoError" class="text-red-500 text-xs mt-1"
                                         x-text="inv.abonoError"></div>
                                </td>
                            </tr>
                        </tbody>
                    </template>
                </table>
            </div>
        </template>
    </div>
</div>

<div x-show="invoices.length === 0" class="bg-white rounded-lg shadow p-6 text-center text-gray-400 mb-5">
    No hay facturas pendientes para este cliente.
</div>

{{-- Consolidated payment form --}}
<div class="bg-white rounded-lg shadow p-5">
    <h2 class="font-semibold text-gray-700 mb-1">Registrar pago consolidado</h2>
    <p class="text-sm text-gray-500 mb-4">
        El pago se distribuirá automáticamente desde la factura más antigua (FIFO).
        <template x-if="creditBalance > 0">
            <span class="text-green-700 font-medium"
                  x-text="'Este cliente tiene ' + fmt(creditBalance) + ' en saldo a favor.'">
            </span>
        </template>
    </p>

    <form method="POST" action="{{ route('cartera.customer.payments', $customer) }}"
          x-data="{ method: 'CASH' }">
        @csrf
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
                           placeholder="0" min="1" step="1"
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

</div>{{-- end x-data carteraCustomer --}}

<script>
const __invoicesData    = @json($invoicesJson);
const __creditBalance   = {{ (int) round((float) $customer->credit_balance) }};
const __group           = @js($group);
const __csrf            = '{{ csrf_token() }}';

function carteraCustomer() {
    return {
        invoices: __invoicesData.map(inv => ({
            ...inv,
            showAbono:   false,
            method:      'CASH',
            abonoAmount: '',
            notes:       '',
            abonoError:  '',
        })),
        creditBalance: __creditBalance,
        saving: false,

        get totalDebt() {
            return this.invoices.reduce((s, inv) => s + inv.balance, 0);
        },

        get netAmount() {
            return Math.max(0, this.totalDebt - this.creditBalance);
        },

        /** Build display sections based on active grouping */
        get sections() {
            if (__group === 'day') {
                const map = new Map();
                for (const inv of this.invoices) {
                    const k = inv.day_key;
                    if (!map.has(k)) {
                        // Format: "Miércoles 31/03/2026" — use native Date for simplicity
                        const [y, m, d] = k.split('-');
                        const dt = new Date(+y, +m - 1, +d);
                        const label = dt.toLocaleDateString('es-CO', {
                            weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric'
                        });
                        map.set(k, { label: label.charAt(0).toUpperCase() + label.slice(1), invoices: [] });
                    }
                    map.get(k).invoices.push(inv);
                }
                return [...map.values()];
            }
            if (__group === 'week') {
                const map = new Map();
                for (const inv of this.invoices) {
                    const k = inv.week_key; // Sunday date of the week
                    if (!map.has(k)) {
                        const [y, m, d] = k.split('-').map(Number);
                        const sun = new Date(y, m - 1, d);
                        const sat = new Date(y, m - 1, d + 6);
                        const fmtD = dt => dt.toLocaleDateString('es-CO', {
                            weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric'
                        });
                        map.set(k, {
                            label: 'Del ' + fmtD(sun) + ' al ' + fmtD(sat),
                            invoices: [],
                        });
                    }
                    map.get(k).invoices.push(inv);
                }
                return [...map.values()];
            }
            // no grouping
            return [{ label: null, invoices: this.invoices }];
        },

        async submitAbono(inv) {
            inv.abonoError = '';
            if (!inv.abonoAmount || inv.abonoAmount < 1) {
                inv.abonoError = 'Ingresa un monto válido (mínimo $1).';
                return;
            }
            this.saving = true;
            try {
                const res = await fetch(`/cartera/${inv.id}/payments`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN':  __csrf,
                        'Content-Type': 'application/json',
                        'Accept':        'application/json',
                    },
                    body: JSON.stringify({
                        method: inv.method,
                        amount: parseInt(inv.abonoAmount, 10),
                        notes:  inv.notes || null,
                    }),
                });
                const data = await res.json();
                if (!res.ok) {
                    inv.abonoError = data.error ?? 'Error al registrar el abono.';
                    return;
                }
                // Update reactive state
                inv.balance     = data.balance;
                inv.paid_amount = data.paid_amount;
                inv.showAbono   = false;
                inv.abonoAmount = '';
                inv.notes       = '';
                // Remove fully-paid invoices from the list
                if (data.balance <= 0) {
                    this.invoices = this.invoices.filter(i => i.id !== inv.id);
                }
            } catch (e) {
                inv.abonoError = 'Error de red. Intenta de nuevo.';
            } finally {
                this.saving = false;
            }
        },

        fmt(v) {
            return '$' + Math.round(v).toLocaleString('es-CO');
        },
    };
}
</script>
@endsection
