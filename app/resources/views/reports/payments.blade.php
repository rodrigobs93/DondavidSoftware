@extends('layouts.app')
@section('title', 'Validación de Pagos')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-bold text-gray-800">Validación de Pagos</h1>
</div>

<div x-data="paymentReport()" x-cloak>

    {{-- Filter bar --}}
    <div class="bg-white rounded-lg shadow p-3 mb-4 space-y-2">

        {{-- Row 1: search + dates + clear + spinner --}}
        @include('partials._filter-bar', ['placeholder' => 'Consecutivo, cliente o razón social…'])

        {{-- Row 2: method chips + unverified toggle --}}
        <div class="flex gap-2 flex-wrap items-center">
            <span class="text-xs text-gray-500">Método:</span>
            <template x-for="chip in methodChips" :key="chip.value">
                <button type="button" @click="method = chip.value; search()"
                        class="px-3 py-2 rounded-full text-sm font-semibold border transition-colors"
                        :class="method === chip.value
                            ? 'bg-blue-600 text-white border-blue-600'
                            : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400'">
                    <span x-text="chip.label"></span>
                </button>
            </template>

            <span class="text-gray-300 mx-1">|</span>

            <button type="button" @click="unverifiedOnly = !unverifiedOnly; search()"
                    class="px-3 py-1 rounded-full text-xs font-semibold border transition-colors"
                    :class="unverifiedOnly
                        ? 'bg-yellow-500 text-white border-yellow-500'
                        : 'bg-white text-gray-600 border-gray-300 hover:border-yellow-400'">
                Solo no verificados
            </button>
        </div>
    </div>

    {{-- Bulk action bar — appears when rows are selected --}}
    <div x-show="selected.size > 0"
         class="bg-yellow-50 border border-yellow-200 rounded-lg px-4 py-2 mb-3 flex items-center gap-3">
        <span class="text-sm text-yellow-800 font-medium"
              x-text="selected.size + ' pago(s) seleccionado(s)'"></span>
        <button type="button" @click="verifyBulk()"
                :disabled="bulkLoading"
                class="pos-btn-primary text-sm py-2">
            Verificar seleccionados
        </button>
        <button type="button" @click="selected = new Set()"
                class="text-xs text-gray-500 hover:text-gray-700">
            Cancelar
        </button>
        <span x-show="bulkLoading" class="text-xs text-gray-400">Procesando…</span>
    </div>

    {{-- MOBILE CARDS --}}
    <div class="sm:hidden space-y-2 mb-4"
         :class="loading ? 'opacity-50 pointer-events-none' : ''">
        <template x-for="pay in payments" :key="pay._type + pay.id">
            <div class="pos-card" :class="pay.verified ? 'bg-green-50/40' : ''">
                <div class="pos-card-row mb-1">
                    <template x-if="pay._type === 'customer_payment'">
                        <span class="font-mono font-bold text-purple-600"
                              x-text="'COBRO — ' + pay.customer_name"></span>
                    </template>
                    <template x-if="pay._type !== 'customer_payment'">
                        <a :href="pay.quick_sale_id ? '/quick-sales/' + pay.quick_sale_id : '/invoices/' + pay.invoice_id"
                           class="font-mono font-bold text-blue-600" x-text="pay.consecutive"></a>
                    </template>
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                          :class="pay.verified ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'"
                          x-text="pay.verified ? '✓ Verificado' : 'Pendiente'"></span>
                </div>
                <div class="pos-card-row">
                    <span class="pos-card-label">Cliente</span>
                    <span class="pos-card-value truncate max-w-[60%]" x-text="pay.customer_name"></span>
                </div>
                <div class="pos-card-row">
                    <span class="pos-card-label">Método</span>
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                          :class="{
                              'bg-green-100 text-green-700':   pay.method === 'CASH',
                              'bg-blue-100  text-blue-700':    pay.method === 'CARD',
                              'bg-pink-100  text-pink-700':    pay.method === 'NEQUI',
                              'bg-red-100   text-red-700':     pay.method === 'DAVIPLATA',
                              'bg-purple-100 text-purple-700': pay.method === 'BREB',
                          }"
                          x-text="pay.method_label"></span>
                </div>
                <div class="pos-card-row">
                    <span class="pos-card-label">Monto</span>
                    <span class="pos-card-value font-semibold font-mono" x-text="formatCOP(pay.amount)"></span>
                </div>
                <div class="mt-2 text-right" x-show="!pay.verified">
                    <button type="button" @click="verifySingle(pay)"
                            class="pos-btn-primary text-sm py-2">
                        Verificar
                    </button>
                </div>
                <div class="mt-1 text-right text-xs text-gray-400" x-show="pay.verified" x-text="pay.verified_at || ''"></div>
            </div>
        </template>
        <div x-show="!loading && payments.length === 0" class="text-center py-8 text-gray-400 text-sm">
            No hay pagos en este filtro.
        </div>
    </div>

    {{-- DESKTOP TABLE --}}
    <div class="hidden sm:block bg-white rounded-lg shadow overflow-hidden"
         :class="loading ? 'opacity-50 pointer-events-none' : ''">
        <table class="pos-table">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-3 py-3 w-8">
                        <input type="checkbox" @change="toggleAll($event.target.checked)"
                               class="rounded" title="Seleccionar todos los no verificados">
                    </th>
                    <th class="text-left px-4 py-3 text-gray-600 font-semibold">#</th>
                    <th class="text-left px-4 py-3 text-gray-600 font-semibold">Fecha pago</th>
                    <th class="text-left px-4 py-3 text-gray-600 font-semibold">Cliente</th>
                    <th class="text-left px-4 py-3 text-gray-600 font-semibold hidden sm:table-cell">Razón social</th>
                    <th class="text-left px-4 py-3 text-gray-600 font-semibold">Método</th>
                    <th class="text-right px-4 py-3 text-gray-600 font-semibold">Monto</th>
                    <th class="text-center px-4 py-3 text-gray-600 font-semibold">Estado</th>
                    <th class="px-4 py-3 w-28"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <template x-for="pay in payments" :key="pay._type + pay.id">
                    <tr class="hover:bg-gray-50"
                        :class="[pay.verified ? 'bg-green-50/40' : '', pay._type === 'customer_payment' ? 'bg-purple-50/30' : '']">

                        {{-- Checkbox --}}
                        <td class="px-3 py-3">
                            <input type="checkbox"
                                   :checked="selected.has(pay._type + pay.id)"
                                   @change="toggleRow(pay._type + pay.id, pay._type, pay.id, $event.target.checked)"
                                   :disabled="pay.verified"
                                   class="rounded disabled:opacity-30">
                        </td>

                        {{-- Consecutive / label --}}
                        <td class="px-4 py-3">
                            <template x-if="pay._type === 'customer_payment'">
                                <span class="font-mono font-semibold text-purple-600"
                                      title="Pago consolidado de cartera">COBRO</span>
                            </template>
                            <template x-if="pay._type !== 'customer_payment'">
                                <a :href="pay.quick_sale_id ? '/quick-sales/' + pay.quick_sale_id : '/invoices/' + pay.invoice_id"
                                   class="font-mono font-semibold text-blue-600 hover:underline"
                                   x-text="pay.consecutive"></a>
                            </template>
                        </td>

                        {{-- Date --}}
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap" x-text="pay.paid_at"></td>

                        {{-- Customer name --}}
                        <td class="px-4 py-3 font-medium" x-text="pay.customer_name"></td>

                        {{-- Business name --}}
                        <td class="px-4 py-3 text-gray-500 hidden sm:table-cell max-w-48">
                            <span class="block truncate" x-text="pay.business_name || '—'"></span>
                        </td>

                        {{-- Method badge --}}
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                                  :class="{
                                      'bg-green-100 text-green-700':   pay.method === 'CASH',
                                      'bg-blue-100  text-blue-700':    pay.method === 'CARD',
                                      'bg-pink-100  text-pink-700':    pay.method === 'NEQUI',
                                      'bg-red-100   text-red-700':     pay.method === 'DAVIPLATA',
                                      'bg-purple-100 text-purple-700': pay.method === 'BREB',
                                  }"
                                  x-text="pay.method_label"></span>
                        </td>

                        {{-- Amount --}}
                        <td class="px-4 py-3 text-right font-semibold font-mono" x-text="fmt(pay.amount)"></td>

                        {{-- Verified badge --}}
                        <td class="px-4 py-3 text-center">
                            <span x-show="pay.verified"
                                  class="px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700"
                                  :title="'Verificado: ' + (pay.verified_at || '')">
                                ✓ Verificado
                            </span>
                            <span x-show="!pay.verified"
                                  class="px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">
                                Pendiente
                            </span>
                        </td>

                        {{-- Action --}}
                        <td class="px-4 py-3 text-right">
                            <button type="button"
                                    x-show="!pay.verified"
                                    @click="verifySingle(pay)"
                                    class="pos-btn-primary text-sm py-2">
                                Verificar
                            </button>
                            <span x-show="pay.verified"
                                  class="text-xs text-gray-400 whitespace-nowrap"
                                  x-text="pay.verified_at || ''"></span>
                        </td>
                    </tr>
                </template>

                <tr x-show="!loading && payments.length === 0">
                    <td colspan="9" class="px-4 py-8 text-center text-gray-400">
                        No hay pagos en este filtro.
                    </td>
                </tr>
            </tbody>

            {{-- Summary footer --}}
            <tfoot x-show="payments.length > 0" class="bg-gray-50 border-t">
                <tr>
                    <td colspan="6" class="px-4 py-2 text-xs text-gray-500">
                        <span x-text="payments.length + ' pago(s)'"></span>
                        <span x-show="unverifiedCount > 0"
                              class="ml-3 text-yellow-700 font-semibold"
                              x-text="unverifiedCount + ' sin verificar'"></span>
                    </td>
                    <td class="px-4 py-2 text-right font-bold font-mono text-sm"
                        x-text="fmt(filteredTotal)"></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>{{-- end desktop table --}}

    {{-- Pagination: only when no filters active --}}
    <div x-show="!hasFilters" class="mt-4">
        {{ $payments->links() }}
    </div>

</div>{{-- end x-data --}}

<script>
const __initialPayments = {!! json_encode($initialData, JSON_HEX_TAG) !!};
const __initPQ          = @js($q);
const __initPMethod     = @js($method);
const __initPUnverified = @js($unverifiedOnly);
const __initPStart      = @js($startDate);
const __initPEnd        = @js($endDate);
const __csrf            = '{{ csrf_token() }}';

function paymentReport() {
    return {
        payments:       __initialPayments,
        loading:        false,
        bulkLoading:    false,
        q:              __initPQ,
        method:         __initPMethod,
        unverifiedOnly: __initPUnverified,
        startDate:      __initPStart,
        endDate:        __initPEnd,
        selected:       new Set(),
        selectedMeta:   new Map(),

        methodChips: [
            { value: '',          label: 'Todos'      },
            { value: 'CARD',      label: 'Tarjeta'    },
            { value: 'NEQUI',     label: 'Nequi'      },
            { value: 'DAVIPLATA', label: 'Daviplata'  },
            { value: 'BREB',      label: 'Bre-B'      },
        ],

        get hasFilters() {
            return !!(this.q || this.method || this.unverifiedOnly || this.startDate || this.endDate);
        },

        get filteredTotal() {
            return this.payments.reduce((s, p) => s + parseFloat(p.amount), 0);
        },

        get unverifiedCount() {
            return this.payments.filter(p => !p.verified).length;
        },

        async search() {
            this.loading = true;
            const params = new URLSearchParams();
            if (this.q)              params.set('q',               this.q);
            if (this.method)         params.set('method',          this.method);
            if (this.unverifiedOnly) params.set('unverified_only', '1');
            if (this.startDate)      params.set('start_date',      this.startDate);
            if (this.endDate)        params.set('end_date',         this.endDate);

            const res = await fetch(`/reports/payments?${params}`, {
                headers: { 'Accept': 'application/json' },
            });
            this.payments      = await res.json();
            this.selected      = new Set();
            this.selectedMeta  = new Map();
            history.replaceState({}, '', `/reports/payments${params.toString() ? '?' + params : ''}`);
            this.loading = false;
        },

        clearFilters() {
            this.q              = '';
            this.method         = '';
            this.unverifiedOnly = false;
            this.startDate      = '';
            this.endDate        = '';
            this.payments       = __initialPayments;
            this.selected       = new Set();
            this.selectedMeta   = new Map();
            history.replaceState({}, '', '/reports/payments');
        },

        toggleRow(key, type, id, checked) {
            const s = new Set(this.selected);
            // Store composite key so we can look up type later
            if (checked) {
                s.add(key);
                this.selectedMeta.set(key, { type, id });
            } else {
                s.delete(key);
                this.selectedMeta.delete(key);
            }
            this.selected = s;
        },

        toggleAll(checked) {
            const s    = new Set();
            const meta = new Map();
            if (checked) {
                this.payments.filter(p => !p.verified).forEach(p => {
                    const key = p._type + p.id;
                    s.add(key);
                    meta.set(key, { type: p._type, id: p.id });
                });
            }
            this.selected     = s;
            this.selectedMeta = meta;
        },

        async verifySingle(pay) {
            const url = pay._type === 'customer_payment'
                ? `/customer-payments/${pay.id}/verify`
                : `/payments/${pay.id}/verify`;
            const res = await fetch(url, {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': __csrf, 'Accept': 'application/json' },
            });
            if (res.ok) {
                const data      = await res.json();
                pay.verified    = true;
                pay.verified_at = data.verified_at;
                const key = pay._type + pay.id;
                const s   = new Set(this.selected);
                s.delete(key);
                this.selected = s;
                this.selectedMeta.delete(key);
            }
        },

        async verifyBulk() {
            if (!this.selected.size) return;
            this.bulkLoading = true;

            const ids   = [];
            const cpIds = [];
            for (const [, meta] of this.selectedMeta) {
                if (meta.type === 'customer_payment') cpIds.push(meta.id);
                else ids.push(meta.id);
            }

            const res = await fetch('/payments/verify-bulk', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN':  __csrf,
                    'Content-Type': 'application/json',
                    'Accept':        'application/json',
                },
                body: JSON.stringify({ ids, cp_ids: cpIds }),
            });
            if (res.ok) {
                await this.search();
            }
            this.bulkLoading = false;
        },

        fmt: window.formatCOP,
    };
}
</script>
@endsection
