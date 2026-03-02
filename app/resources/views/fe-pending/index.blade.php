@extends('layouts.app')
@section('title', 'Facturación Electrónica')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-bold text-gray-800">Facturación Electrónica</h1>
</div>

<div x-data="feFilter()">

    {{-- Filter bar --}}
    <div class="bg-white rounded-lg shadow p-3 mb-4 space-y-2">

        {{-- Row 1: search + dates + clear + spinner --}}
        @include('partials._filter-bar')

        {{-- Row 2: FE status chips --}}
        <div class="flex gap-2 flex-wrap items-center">
            <span class="text-xs text-gray-500">Estado FE:</span>
            <template x-for="chip in feChips" :key="chip.value">
                <button type="button" @click="feStatus = chip.value; search()"
                        class="px-3 py-1 rounded-full text-xs font-semibold border transition-colors"
                        :class="feStatus === chip.value
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
                    <th class="text-left px-4 py-3 text-gray-600 font-semibold">Documento</th>
                    <th class="text-right px-4 py-3 text-gray-600 font-semibold">Total</th>
                    <th class="text-center px-4 py-3 text-gray-600 font-semibold">Estado FE</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <template x-for="inv in invoices" :key="inv.id">
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono font-semibold text-blue-600"
                            x-text="inv.consecutive"></td>
                        <td class="px-4 py-3 text-gray-600" x-text="inv.invoice_date"></td>
                        <td class="px-4 py-3 font-medium" x-text="inv.customer_name"></td>
                        <td class="px-4 py-3 text-gray-500" x-text="inv.customer_doc || '—'"></td>
                        <td class="px-4 py-3 text-right font-semibold" x-text="fmt(inv.total)"></td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                                  :class="{
                                      'bg-green-100 text-green-700': inv.fe_status === 'ISSUED',
                                      'bg-blue-100  text-blue-700':  inv.fe_status === 'PENDING',
                                  }"
                                  x-text="inv.fe_status === 'ISSUED' ? 'EMITIDA' : 'PENDIENTE'"></span>
                        </td>
                        <td class="px-4 py-3">
                            <a :href="'/invoices/' + inv.id"
                               class="pos-btn-primary text-xs py-1">Ver / Marcar</a>
                        </td>
                    </tr>
                </template>
                <tr x-show="!loading && invoices.length === 0">
                    <td colspan="7" class="px-4 py-8 text-center text-gray-400">
                        No hay facturas con FE en este filtro.
                    </td>
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
const __initialFe       = {!! json_encode($initialData, JSON_HEX_TAG) !!};
const __initialFeQ      = @js($q);
const __initialFeStatus = @js($feStatus);
const __initialFeStart  = @js($startDate);
const __initialFeEnd    = @js($endDate);

function feFilter() {
    return {
        invoices:  __initialFe,
        loading:   false,
        q:         __initialFeQ,
        feStatus:  __initialFeStatus,
        startDate: __initialFeStart,
        endDate:   __initialFeEnd,

        feChips: [
            { value: '',        label: 'Todas'      },
            { value: 'PENDING', label: 'Pendientes' },
            { value: 'ISSUED',  label: 'Emitidas'   },
        ],

        get hasFilters() {
            return !!(this.q || this.feStatus || this.startDate || this.endDate);
        },

        async search() {
            this.loading = true;
            const params = new URLSearchParams();
            if (this.q)         params.set('q',          this.q);
            if (this.feStatus)  params.set('fe_status',  this.feStatus);
            if (this.startDate) params.set('start_date', this.startDate);
            if (this.endDate)   params.set('end_date',   this.endDate);

            const res = await fetch(`/fe-pending?${params}`, {
                headers: { 'Accept': 'application/json' },
            });
            this.invoices = await res.json();
            history.replaceState({}, '', `/fe-pending${params.toString() ? '?' + params : ''}`);
            this.loading = false;
        },

        clearFilters() {
            this.q         = '';
            this.feStatus  = '';
            this.startDate = '';
            this.endDate   = '';
            this.invoices  = __initialFe;
            history.replaceState({}, '', '/fe-pending');
        },

        fmt(val) {
            return '$' + parseFloat(val).toLocaleString('es-CO', { maximumFractionDigits: 0 });
        },
    };
}
</script>
@endsection
