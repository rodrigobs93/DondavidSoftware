@extends('layouts.app')
@section('title', 'Facturas')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-bold text-gray-800">Facturas</h1>
    <a href="{{ route('sales.create') }}" class="pos-btn-success">+ Nueva Venta</a>
</div>

<div x-data="invoiceFilter()">

    {{-- Filter bar --}}
    <div class="bg-white rounded-lg shadow p-3 mb-4 space-y-2">

        {{-- Row 1: search + dates + clear + spinner --}}
        <div class="flex gap-2 flex-wrap items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs text-gray-500 mb-1">Buscar</label>
                <input type="text" x-model="q" @input.debounce.400ms="fetchInvoices()"
                       placeholder="Consecutivo, cliente o razón social…"
                       class="border rounded px-3 py-2 text-sm w-full">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Desde</label>
                <input type="date" x-model="startDate" @change="fetchInvoices()"
                       class="border rounded px-2 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Hasta</label>
                <input type="date" x-model="endDate" @change="fetchInvoices()"
                       class="border rounded px-2 py-2 text-sm">
            </div>
            <button type="button" x-show="hasFilters" x-cloak
                    @click="clearFilters()" class="pos-btn-secondary self-end">
                Limpiar
            </button>
            <span x-show="loading" x-cloak class="text-sm text-gray-400 self-end pb-2">Buscando…</span>
        </div>

        {{-- Row 2: status chips --}}
        <div class="flex gap-2 flex-wrap items-center">
            <span class="text-xs text-gray-500">Estado:</span>
            <template x-for="chip in statusChips" :key="chip.value">
                <button type="button" @click="status = chip.value; fetchInvoices()"
                        class="px-3 py-1 rounded-full text-xs font-semibold border transition-colors"
                        :class="status === chip.value
                            ? 'bg-blue-600 text-white border-blue-600'
                            : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400'">
                    <span x-text="chip.label"></span>
                </button>
            </template>
        </div>

    </div>

    {{-- Table --}}
    <div class="bg-white rounded-lg shadow overflow-hidden"
         :class="loading ? 'opacity-50 pointer-events-none' : ''">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="text-left px-4 py-3 text-gray-600 font-semibold">#</th>
                    <th class="text-left px-4 py-3 text-gray-600 font-semibold">Fecha</th>
                    <th class="text-left px-4 py-3 text-gray-600 font-semibold">Cliente</th>
                    <th class="text-right px-4 py-3 text-gray-600 font-semibold">Total</th>
                    <th class="text-right px-4 py-3 text-gray-600 font-semibold">Saldo</th>
                    <th class="text-center px-4 py-3 text-gray-600 font-semibold">Estado</th>
                    <th class="text-center px-4 py-3 text-gray-600 font-semibold">FE</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <template x-for="inv in invoices" :key="inv.id">
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono font-semibold text-blue-600">
                            <a :href="'/invoices/' + inv.id" x-text="inv.consecutive"></a>
                        </td>
                        <td class="px-4 py-3 text-gray-600" x-text="inv.invoice_date"></td>
                        <td class="px-4 py-3" x-text="inv.customer_name"></td>
                        <td class="px-4 py-3 text-right font-semibold" x-text="fmt(inv.total)"></td>
                        <td class="px-4 py-3 text-right"
                            :class="parseFloat(inv.balance) > 0 ? 'text-yellow-700 font-semibold' : 'text-gray-400'"
                            x-text="fmt(inv.balance)"></td>
                        <td class="px-4 py-3 text-center">
                            <span :class="{
                                      'badge-paid':    inv.status === 'PAID',
                                      'badge-partial': inv.status === 'PARTIAL',
                                      'badge-pending': inv.status === 'PENDING',
                                  }"
                                  x-text="inv.status"></span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="text-xs px-1.5 py-0.5 rounded"
                                  :class="{
                                      'bg-green-100 text-green-700': inv.fe_status === 'ISSUED',
                                      'bg-blue-100 text-blue-700':   inv.fe_status === 'PENDING',
                                      'bg-gray-100 text-gray-400':   inv.fe_status === 'NONE',
                                  }"
                                  x-text="inv.fe_status"></span>
                        </td>
                        <td class="px-4 py-3">
                            <a :href="'/invoices/' + inv.id"
                               class="text-blue-600 hover:text-blue-800 text-xs">Ver</a>
                        </td>
                    </tr>
                </template>
                <tr x-show="!loading && invoices.length === 0">
                    <td colspan="8" class="px-4 py-8 text-center text-gray-400">No hay facturas.</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Pagination: only when no filters active --}}
    <div x-show="!hasFilters" class="mt-4">
        {{ $invoices->links() }}
    </div>

</div>{{-- end x-data --}}

<script>
const __initialInvoices  = {!! json_encode($initialData, JSON_HEX_TAG) !!};
const __initialQ         = @js($q);
const __initialStatus    = @js($status);
const __initialStartDate = @js($startDate);
const __initialEndDate   = @js($endDate);

function invoiceFilter() {
    return {
        invoices:  __initialInvoices,
        loading:   false,
        q:         __initialQ,
        status:    __initialStatus,
        startDate: __initialStartDate,
        endDate:   __initialEndDate,

        statusChips: [
            { value: '',        label: 'Todas'      },
            { value: 'PAID',    label: 'Pagadas'    },
            { value: 'PARTIAL', label: 'Parciales'  },
            { value: 'PENDING', label: 'Pendientes' },
        ],

        get hasFilters() {
            return !!(this.q || this.status || this.startDate || this.endDate);
        },

        async fetchInvoices() {
            this.loading = true;
            const params = new URLSearchParams();
            if (this.q)         params.set('q',          this.q);
            if (this.status)    params.set('status',     this.status);
            if (this.startDate) params.set('start_date', this.startDate);
            if (this.endDate)   params.set('end_date',   this.endDate);

            const res = await fetch(`/invoices?${params}`, {
                headers: { 'Accept': 'application/json' },
            });
            this.invoices = await res.json();
            history.replaceState({}, '', `/invoices${params.toString() ? '?' + params : ''}`);
            this.loading = false;
        },

        clearFilters() {
            this.q         = '';
            this.status    = '';
            this.startDate = '';
            this.endDate   = '';
            this.invoices  = __initialInvoices;
            history.replaceState({}, '', '/invoices');
        },

        fmt(val) {
            return '$' + parseFloat(val).toLocaleString('es-CO', { maximumFractionDigits: 0 });
        },
    };
}
</script>
@endsection
