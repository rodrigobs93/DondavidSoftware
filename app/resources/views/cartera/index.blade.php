@extends('layouts.app')
@section('title', 'Cartera')

@section('content')
<div x-data="carteraIndex()" x-init="init()" x-cloak>

    {{-- Header --}}
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-bold text-gray-800">Cartera</h1>
        <div class="text-right">
            <template x-if="activeTab === 'cartera'">
                <div>
                    <div class="text-xs text-gray-500">Total pendiente</div>
                    <div class="text-2xl font-bold text-red-700 font-mono" x-text="formatCOP(globalBalance)"></div>
                </div>
            </template>
            <template x-if="activeTab === 'saldo'">
                <div>
                    <div class="text-xs text-gray-500">Total saldo a favor</div>
                    <div class="text-2xl font-bold text-green-700 font-mono" x-text="formatCOP(globalSaldo)"></div>
                </div>
            </template>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex gap-1 mb-4 border-b border-gray-200">
        <button type="button"
                @click="activeTab = 'cartera'"
                class="px-5 py-2.5 text-sm font-semibold border-b-2 transition-colors"
                :class="activeTab === 'cartera'
                    ? 'border-blue-500 text-blue-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700'">
            Cartera
            <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-xs"
                  :class="activeTab === 'cartera' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500'"
                  x-text="customers.length"></span>
        </button>
        <button type="button"
                @click="activeTab = 'saldo'"
                class="px-5 py-2.5 text-sm font-semibold border-b-2 transition-colors"
                :class="activeTab === 'saldo'
                    ? 'border-green-500 text-green-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700'">
            Saldo a favor
            <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-xs"
                  :class="activeTab === 'saldo' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                  x-text="saldoCustomers.length"></span>
        </button>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-lg shadow p-4 mb-4 space-y-3">
        <div class="flex gap-2 flex-wrap items-end">
            <div class="flex-1 min-w-48">
                <input type="text" x-model="q"
                       @input.debounce.300ms="fetch()"
                       placeholder="Buscar cliente o razón social…"
                       class="border rounded px-3 py-2.5 text-base w-full focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>

            {{-- Date filters only apply to the Cartera tab --}}
            <template x-if="activeTab === 'cartera'">
                <div class="flex items-center gap-2">
                    <input type="date" x-model="startDate" @change="fetch()"
                           class="border rounded px-2 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                    <span class="text-gray-400 text-sm">—</span>
                    <input type="date" x-model="endDate" @change="fetch()"
                           class="border rounded px-2 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                </div>
            </template>

            <button type="button" x-show="q || startDate || endDate"
                    @click="q=''; startDate=''; endDate=''; fetch()"
                    class="pos-btn pos-btn-secondary text-sm">
                Limpiar
            </button>
        </div>

        <div x-show="activeTab === 'cartera' && (q || startDate || endDate)" class="text-sm text-gray-500">
            Resultado:
            <span class="font-semibold text-gray-700"
                  x-text="filteredBalance > 0 ? formatCOP(filteredBalance) + ' pendiente' : 'sin resultados'"></span>
        </div>
    </div>

    {{-- Loading --}}
    <div x-show="loading" class="text-center py-8 text-gray-400 text-sm">Cargando…</div>

    {{-- ── TAB: Cartera ─────────────────────────────────────────────────── --}}
    <div x-show="!loading && activeTab === 'cartera'">
        <div x-show="customers.length === 0"
             class="bg-white rounded-lg shadow p-8 text-center text-gray-400">
            <span x-text="q || startDate || endDate
                ? 'Sin resultados para este filtro.'
                : 'No hay facturas pendientes. ¡Todo al día!'"></span>
        </div>
        <div x-show="customers.length > 0" class="space-y-2">
            <template x-for="row in customers" :key="row.customer.id">
                <a :href="'/cartera/' + row.customer.id"
                   class="block bg-white rounded-lg shadow hover:shadow-md transition-shadow border border-transparent hover:border-blue-200 p-4">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-gray-800 text-base truncate"
                                 x-text="row.customer.name"></div>
                            <div x-show="row.customer.business_name"
                                 class="text-sm text-gray-500 truncate"
                                 x-text="row.customer.business_name"></div>
                            <div class="text-xs text-gray-400 mt-0.5"
                                 x-text="row.invoice_count + (row.invoice_count === 1 ? ' factura pendiente' : ' facturas pendientes')">
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-xl font-bold text-red-700 font-mono"
                                 x-text="formatCOP(row.total_balance)"></div>
                            <div x-show="parseFloat(row.customer.credit_balance) > 0"
                                 class="text-xs text-green-600 font-semibold mt-0.5"
                                 x-text="'Crédito: ' + formatCOP(row.customer.credit_balance)"></div>
                            <div class="text-xs text-blue-500 mt-1">Ver detalle →</div>
                        </div>
                    </div>
                </a>
            </template>
        </div>
    </div>

    {{-- ── TAB: Saldo a favor ────────────────────────────────────────────── --}}
    <div x-show="!loading && activeTab === 'saldo'">
        <div x-show="saldoCustomers.length === 0"
             class="bg-white rounded-lg shadow p-8 text-center text-gray-400">
            <span x-text="q ? 'Sin resultados para este filtro.' : 'Ningún cliente tiene saldo a favor.'"></span>
        </div>
        <div x-show="saldoCustomers.length > 0" class="space-y-2">
            <template x-for="row in saldoCustomers" :key="row.customer.id">
                <a :href="'/cartera/' + row.customer.id"
                   class="block bg-white rounded-lg shadow hover:shadow-md transition-shadow border border-transparent hover:border-green-200 p-4">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-gray-800 text-base truncate"
                                 x-text="row.customer.name"></div>
                            <div x-show="row.customer.business_name"
                                 class="text-sm text-gray-500 truncate"
                                 x-text="row.customer.business_name"></div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-xl font-bold text-green-700 font-mono"
                                 x-text="formatCOP(row.customer.credit_balance)"></div>
                            <div class="text-xs text-green-500 mt-1">Saldo a favor →</div>
                        </div>
                    </div>
                </a>
            </template>
        </div>
    </div>

</div>

<script>
const __carteraInitial      = {!! json_encode($initialData,    JSON_HEX_TAG) !!};
const __saldoInitial        = {!! json_encode($initialSaldo,   JSON_HEX_TAG) !!};
const __carteraGlobalBal    = {{ $globalTotalBalance }};
const __saldoGlobalBal      = {{ $globalSaldoBalance }};

function carteraIndex() {
    return {
        activeTab:       'cartera',
        customers:       __carteraInitial,
        saldoCustomers:  __saldoInitial,
        globalBalance:   __carteraGlobalBal,
        globalSaldo:     __saldoGlobalBal,
        filteredBalance: 0,
        q:               @js($q),
        startDate:       @js($startDate),
        endDate:         @js($endDate),
        loading:         false,

        init() {
            this.computeFilteredBalance();
        },

        computeFilteredBalance() {
            this.filteredBalance = this.customers.reduce((sum, row) => {
                return sum + parseFloat(row.total_balance || 0);
            }, 0);
        },

        async fetch() {
            this.loading = true;
            try {
                const params = new URLSearchParams();
                if (this.q)         params.set('q',          this.q);
                if (this.startDate) params.set('start_date', this.startDate);
                if (this.endDate)   params.set('end_date',   this.endDate);

                const res  = await window.fetch('/cartera?' + params.toString(), {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await res.json();

                this.customers        = data.customers;
                this.saldoCustomers   = data.saldo_customers;
                this.globalBalance    = parseFloat(data.global_total_balance  || 0);
                this.globalSaldo      = parseFloat(data.global_saldo_balance  || 0);
                this.computeFilteredBalance();
            } catch (e) {
                console.error('Error cargando cartera:', e);
            } finally {
                this.loading = false;
            }
        },
    };
}
</script>
@endsection
