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
                        class="px-3 py-2 rounded-full text-sm font-semibold border transition-colors"
                        :class="status === chip.value
                            ? 'bg-blue-600 text-white border-blue-600'
                            : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400'">
                    <span x-text="chip.label"></span>
                </button>
            </template>
        </div>

    </div>

    {{-- MOBILE CARDS --}}
    <div class="sm:hidden space-y-2 mb-4"
         :class="loading ? 'opacity-50 pointer-events-none' : ''">
        <template x-for="inv in invoices" :key="inv.id">
            <a :href="'/invoices/' + inv.id" class="pos-card block">
                <div class="pos-card-row mb-1">
                    <span class="font-mono font-bold text-blue-600" x-text="inv.consecutive"></span>
                    <span :class="statusBadge(inv.status)" x-text="statusLabel(inv.status)"></span>
                </div>
                <div class="pos-card-row">
                    <span class="pos-card-label">Cliente</span>
                    <span class="pos-card-value truncate max-w-[60%]" x-text="inv.customer_name"></span>
                </div>
                <div class="pos-card-row" x-show="inv.customer_business_name">
                    <span class="pos-card-label">Empresa</span>
                    <span class="pos-card-value truncate max-w-[60%]" x-text="inv.customer_business_name"></span>
                </div>
                <div class="pos-card-row">
                    <span class="pos-card-label">Fecha</span>
                    <span class="pos-card-value" x-text="inv.invoice_date"></span>
                </div>
                <div class="pos-card-row">
                    <span class="pos-card-label">Total</span>
                    <span class="pos-card-value font-semibold" x-text="formatCOP(inv.total)"></span>
                </div>
            </a>
        </template>
        <div x-show="!loading && invoices.length === 0" class="text-center py-8 text-gray-400 text-sm">
            No hay facturas.
        </div>
    </div>

    {{-- DESKTOP TABLE --}}
    <div class="hidden sm:block bg-white rounded-lg shadow overflow-hidden"
         :class="loading ? 'opacity-50 pointer-events-none' : ''">
        <table class="pos-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Empresa</th>
                    <th class="text-right">Total</th>
                    <th class="text-center">Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="inv in invoices" :key="inv.id">
                    <tr>
                        <td class="font-mono font-semibold text-blue-600">
                            <a :href="'/invoices/' + inv.id" x-text="inv.consecutive"></a>
                        </td>
                        <td class="text-gray-600 whitespace-nowrap" x-text="inv.invoice_date"></td>
                        <td x-text="inv.customer_name"></td>
                        <td class="text-gray-500 text-sm" x-text="inv.customer_business_name"></td>
                        <td class="text-right font-semibold" x-text="formatCOP(inv.total)"></td>
                        <td class="text-center">
                            <span :class="statusBadge(inv.status)" x-text="statusLabel(inv.status)"></span>
                        </td>
                        <td>
                            <a :href="'/invoices/' + inv.id"
                               class="text-blue-600 hover:text-blue-800 text-xs">Ver</a>
                        </td>
                    </tr>
                </template>
                <tr x-show="!loading && invoices.length === 0">
                    <td colspan="7" class="py-8 text-center text-gray-400">No hay facturas.</td>
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

        statusLabel(s) {
            return { PAID: 'Pagada', PARTIAL: 'Parcial', PENDING: 'Pendiente' }[s] ?? s;
        },

        statusBadge(s) {
            return {
                PAID:    'badge-paid',
                PARTIAL: 'badge-partial',
                PENDING: 'badge-pending',
            }[s] ?? 'badge-pending';
        },

        fmt: window.formatCOP,
    };
}
</script>
@endsection
