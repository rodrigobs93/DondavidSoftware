@extends('layouts.app')
@section('title', 'Cartera')

@section('content')
<div x-data="carteraIndex()" x-init="init()" x-cloak>

    {{-- Header --}}
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-bold text-gray-800">Cartera Pendiente</h1>
        <div class="text-right">
            <div class="text-xs text-gray-500">Total general pendiente</div>
            <div class="text-2xl font-bold text-red-700 font-mono" x-text="formatCOP(globalBalance)"></div>
        </div>
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
            <div class="flex items-center gap-2">
                <input type="date" x-model="startDate" @change="fetch()"
                       class="border rounded px-2 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                <span class="text-gray-400 text-sm">—</span>
                <input type="date" x-model="endDate" @change="fetch()"
                       class="border rounded px-2 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>
            <button type="button" x-show="q || startDate || endDate"
                    @click="q=''; startDate=''; endDate=''; fetch()"
                    class="pos-btn pos-btn-secondary text-sm">
                Limpiar
            </button>
        </div>

        {{-- Filtered balance when filters are active --}}
        <div x-show="q || startDate || endDate" class="text-sm text-gray-500">
            Resultado:
            <span class="font-semibold text-gray-700"
                  x-text="filteredBalance > 0 ? formatCOP(filteredBalance) + ' pendiente' : 'sin resultados'"></span>
        </div>
    </div>

    {{-- Loading --}}
    <div x-show="loading" class="text-center py-8 text-gray-400 text-sm">Cargando…</div>

    {{-- Empty state --}}
    <div x-show="!loading && customers.length === 0"
         class="bg-white rounded-lg shadow p-8 text-center text-gray-400">
        <span x-text="q || startDate || endDate
            ? 'Sin resultados para este filtro.'
            : 'No hay facturas pendientes. ¡Todo al día!'"></span>
    </div>

    {{-- Customer rows --}}
    <div x-show="!loading && customers.length > 0" class="space-y-2">
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

<script>
const __carteraInitial      = {!! json_encode($initialData, JSON_HEX_TAG) !!};
const __carteraGlobalBalance = {{ $globalTotalBalance }};

function carteraIndex() {
    return {
        customers:       __carteraInitial,
        globalBalance:   __carteraGlobalBalance,
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

                this.customers     = data.customers;
                this.globalBalance = parseFloat(data.global_total_balance || 0);
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
