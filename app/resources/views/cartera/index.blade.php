@extends('layouts.app')
@section('title', 'Cartera')

@section('content')
<div x-data="carteraFilter()">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-xl font-bold text-gray-800">Cartera Pendiente</h1>
            <p class="text-sm text-gray-500">
                Total saldo: <strong class="text-yellow-700">${{ number_format($totalBalance, 0, ',', '.') }}</strong>
                <span x-show="hasFilters" x-cloak class="ml-2">
                    · Filtrado: <strong class="text-yellow-700" x-text="'$' + filteredBalance()"></strong>
                </span>
            </p>
        </div>
    </div>

    {{-- Filter bar --}}
    <div class="bg-white rounded-lg shadow p-3 mb-4">
        @include('partials._filter-bar')
    </div>

    {{-- Cards --}}
    <div class="space-y-3" :class="loading ? 'opacity-50 pointer-events-none' : ''">
        <template x-for="inv in invoices" :key="inv.id">
            <div class="bg-white rounded-lg shadow p-4" x-data="{ showAbono: false }">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <a :href="'/invoices/' + inv.id"
                               class="font-mono font-bold text-blue-600 hover:text-blue-800"
                               x-text="'#' + inv.consecutive"></a>
                            <span class="text-sm text-gray-500" x-text="inv.invoice_date"></span>
                            <span :class="{
                                      'badge-partial': inv.status === 'PARTIAL',
                                      'badge-pending': inv.status === 'PENDING',
                                  }"
                                  x-text="inv.status === 'PARTIAL' ? 'PARCIAL' : 'PENDIENTE'"></span>
                        </div>
                        <p class="text-sm text-gray-700 mt-0.5" x-text="inv.customer_name"></p>
                        <div class="flex gap-4 text-sm mt-1">
                            <span class="text-gray-500"      x-text="'Total: '  + fmt(inv.total)"></span>
                            <span class="text-green-600"     x-text="'Pagado: ' + fmt(inv.paid_amount)"></span>
                            <span class="text-yellow-700 font-semibold" x-text="'Saldo: ' + fmt(inv.balance)"></span>
                        </div>
                    </div>
                    <button type="button" @click="showAbono = !showAbono"
                            class="pos-btn-success text-sm">
                        <span x-show="!showAbono">+ Abonar</span>
                        <span x-show="showAbono">Cancelar</span>
                    </button>
                </div>

                <div x-show="showAbono" x-cloak class="mt-3 pt-3 border-t">
                    <form method="POST" :action="'/cartera/' + inv.id + '/payments'">
                        <input type="hidden" name="_token" :value="__csrf">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                            <select name="method" class="border rounded px-2 py-2 text-sm">
                                <option value="CASH">Efectivo</option>
                                <option value="CARD">Tarjeta</option>
                                <option value="NEQUI">Nequi</option>
                                <option value="DAVIPLATA">Daviplata</option>
                                <option value="BREB">Bre-B</option>
                            </select>
                            <input type="number" name="amount" placeholder="Monto a abonar"
                                   min="0.01" step="0.01" :max="parseFloat(inv.balance)"
                                   class="border rounded px-2 py-2 text-sm" required>
                            <input type="text" name="notes" placeholder="Notas (opcional)"
                                   class="border rounded px-2 py-2 text-sm">
                            <button class="pos-btn-success w-full">Registrar</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        {{-- Empty state --}}
        <div x-show="!loading && invoices.length === 0"
             class="bg-white rounded-lg shadow p-8 text-center text-gray-400">
            No hay facturas con saldo pendiente.
        </div>
    </div>

    {{-- Pagination: only when no filters active --}}
    <div x-show="!hasFilters" class="mt-4">
        {{ $invoices->links() }}
    </div>

</div>{{-- end x-data --}}

<script>
const __initialCartera  = {!! json_encode($initialData, JSON_HEX_TAG) !!};
const __initialCQ       = @js($q);
const __initialCStart   = @js($startDate);
const __initialCEnd     = @js($endDate);
const __csrf            = '{{ csrf_token() }}';

function carteraFilter() {
    return {
        invoices:  __initialCartera,
        loading:   false,
        q:         __initialCQ,
        startDate: __initialCStart,
        endDate:   __initialCEnd,

        get hasFilters() {
            return !!(this.q || this.startDate || this.endDate);
        },

        async search() {
            this.loading = true;
            const params = new URLSearchParams();
            if (this.q)         params.set('q',          this.q);
            if (this.startDate) params.set('start_date', this.startDate);
            if (this.endDate)   params.set('end_date',   this.endDate);

            const res = await fetch(`/cartera?${params}`, {
                headers: { 'Accept': 'application/json' },
            });
            this.invoices = await res.json();
            history.replaceState({}, '', `/cartera${params.toString() ? '?' + params : ''}`);
            this.loading = false;
        },

        clearFilters() {
            this.q         = '';
            this.startDate = '';
            this.endDate   = '';
            this.invoices  = __initialCartera;
            history.replaceState({}, '', '/cartera');
        },

        filteredBalance() {
            const sum = this.invoices.reduce((acc, inv) => acc + parseFloat(inv.balance), 0);
            return sum.toLocaleString('es-CO', { maximumFractionDigits: 0 });
        },

        fmt(val) {
            return '$' + parseFloat(val).toLocaleString('es-CO', { maximumFractionDigits: 0 });
        },
    };
}
</script>
@endsection
