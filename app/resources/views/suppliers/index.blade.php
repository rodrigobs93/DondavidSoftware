@extends('layouts.app')
@section('title', 'Proveedores')

@section('content')
<div class="flex items-center justify-between mb-4 gap-2 flex-wrap">
    <h1 class="text-xl font-bold text-gray-800">Proveedores</h1>
    <div class="flex gap-2" x-data>
        <button type="button" @click="$dispatch('open-supplier-invoice')" class="pos-btn pos-btn-success">+ Nueva Compra</button>
        <a href="{{ route('suppliers.create') }}" class="pos-btn-primary">+ Nuevo Proveedor</a>
    </div>
</div>

@if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm mb-4">
        {{ session('success') }}
    </div>
@endif

{{-- Global totals --}}
<div class="grid grid-cols-2 gap-3 mb-4">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500">Total por pagar</div>
        <div class="text-lg font-bold text-red-600">{{ format_cop($globalPending) }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500">Saldo a favor (total)</div>
        <div class="text-lg font-bold text-green-600">{{ format_cop($globalCredit) }}</div>
    </div>
</div>

<div x-data="supplierFilter()">

    {{-- Search bar --}}
    <div class="bg-white rounded-lg shadow p-3 mb-4 flex gap-3 items-center">
        <input type="text" x-ref="searchInput" value="{{ $search }}"
               placeholder="Buscar por nombre o NIT…"
               class="border rounded px-3 py-2 text-sm flex-1"
               @input.debounce.400ms="fetchSuppliers()">
        <span x-show="loading" x-cloak class="text-sm text-gray-400 whitespace-nowrap">Buscando…</span>
        <button type="button" x-show="searching" x-cloak
                @click="clearSearch()" class="pos-btn-secondary whitespace-nowrap">
            Limpiar
        </button>
    </div>

    {{-- MOBILE CARDS --}}
    <div class="sm:hidden space-y-2 mb-4"
         :class="loading ? 'opacity-50 pointer-events-none' : ''">
        <template x-for="s in suppliers" :key="s.id">
            <div class="pos-card">
                <div class="pos-card-row mb-1">
                    <a :href="'/suppliers/' + s.id" class="font-semibold text-blue-600" x-text="s.name"></a>
                </div>
                <div class="pos-card-row" x-show="s.tax_id">
                    <span class="pos-card-label">NIT</span>
                    <span class="pos-card-value" x-text="s.tax_id"></span>
                </div>
                <div class="pos-card-row" x-show="s.phone">
                    <span class="pos-card-label">Teléfono</span>
                    <span class="pos-card-value" x-text="s.phone"></span>
                </div>
                <div class="pos-card-row">
                    <span class="pos-card-label">Por pagar</span>
                    <span class="pos-card-value text-red-600" x-text="fmt(s.pending_balance)"></span>
                </div>
                <div class="pos-card-row" x-show="parseFloat(s.credit_balance) > 0">
                    <span class="pos-card-label">Saldo a favor</span>
                    <span class="pos-card-value text-green-600" x-text="fmt(s.credit_balance)"></span>
                </div>
                <div class="mt-2 flex gap-2 justify-end">
                    <a :href="'/suppliers/' + s.id" class="pos-btn pos-btn-secondary text-sm py-2">Ver Compras</a>
                    <a :href="'/suppliers/' + s.id + '/edit'" class="pos-btn pos-btn-secondary text-sm py-2">Editar</a>
                    <form :action="'/suppliers/' + s.id" method="POST" class="inline"
                          @submit.prevent="if(confirm('¿Eliminar «' + s.name + '»?')) $el.submit()">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="pos-btn pos-btn-danger text-sm py-2">Eliminar</button>
                    </form>
                </div>
            </div>
        </template>
        <div x-show="!loading && suppliers.length === 0" class="text-center py-8 text-gray-400 text-sm">
            No se encontraron proveedores.
        </div>
    </div>

    {{-- DESKTOP TABLE --}}
    <div class="hidden sm:block bg-white rounded-lg shadow overflow-hidden"
         :class="loading ? 'opacity-50 pointer-events-none' : ''">
        <table class="pos-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>NIT</th>
                    <th>Teléfono</th>
                    <th class="text-right">Por pagar</th>
                    <th class="text-right">Saldo a favor</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="s in suppliers" :key="s.id">
                    <tr>
                        <td class="font-medium">
                            <a :href="'/suppliers/' + s.id" class="text-blue-600 hover:underline" x-text="s.name"></a>
                        </td>
                        <td class="text-gray-500" x-text="s.tax_id || '—'"></td>
                        <td class="text-gray-500" x-text="s.phone || '—'"></td>
                        <td class="text-right font-semibold text-red-600" x-text="fmt(s.pending_balance)"></td>
                        <td class="text-right" :class="parseFloat(s.credit_balance) > 0 ? 'text-green-600 font-semibold' : 'text-gray-300'"
                            x-text="parseFloat(s.credit_balance) > 0 ? fmt(s.credit_balance) : '—'"></td>
                        <td class="text-right space-x-2 whitespace-nowrap">
                            <a :href="'/suppliers/' + s.id" class="pos-btn-link">Ver Compras</a>
                            <a :href="'/suppliers/' + s.id + '/edit'" class="pos-btn-link">Editar</a>
                            <form :action="'/suppliers/' + s.id" method="POST" class="inline"
                                  @submit.prevent="if(confirm('¿Eliminar «' + s.name + '»? Si tiene historial, se conserva; si no, se elimina definitivamente.')) $el.submit()">
                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                <input type="hidden" name="_method" value="DELETE">
                                <button type="submit" class="pos-btn-link pos-btn-link-danger">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                </template>
                <tr x-show="!loading && suppliers.length === 0">
                    <td colspan="6" class="text-center py-8 text-gray-400">
                        No se encontraron proveedores.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

</div>{{-- end x-data --}}

<script>
const __initialSuppliers = {!! json_encode($initialData, JSON_HEX_TAG) !!};

function supplierFilter() {
    return {
        suppliers: __initialSuppliers,
        loading: false,
        searching: {{ $search ? 'true' : 'false' }},

        fmt(v) { return '$' + Math.round(parseFloat(v) || 0).toLocaleString('es-CO'); },

        async fetchSuppliers() {
            const term = this.$refs.searchInput.value.trim();
            if (!term) { this.clearSearch(); return; }

            this.searching = true;
            this.loading = true;
            const params = new URLSearchParams({ search: term });
            const res = await fetch(`/suppliers?${params}`, {
                headers: { 'Accept': 'application/json' },
            });
            this.suppliers = await res.json();
            history.replaceState({}, '', `/suppliers?${params}`);
            this.loading = false;
        },

        clearSearch() {
            this.$refs.searchInput.value = '';
            this.searching = false;
            this.suppliers = __initialSuppliers;
            history.replaceState({}, '', '/suppliers');
        },
    };
}
</script>

@include('partials._supplier-invoice-modal')
@endsection
