@extends('layouts.app')
@section('title', 'Clientes')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-bold text-gray-800">Clientes</h1>
    <a href="{{ route('customers.create') }}" class="pos-btn-primary">+ Nuevo Cliente</a>
</div>

<div x-data="customerFilter()">

    {{-- Search bar --}}
    <div class="bg-white rounded-lg shadow p-3 mb-4 flex gap-3 items-center">
        <input type="text" x-ref="searchInput" value="{{ $search }}"
               placeholder="Buscar por nombre o razón social…"
               class="border rounded px-3 py-2 text-sm flex-1"
               @input.debounce.400ms="fetchCustomers()">
        <span x-show="loading" x-cloak class="text-sm text-gray-400 whitespace-nowrap">Buscando…</span>
        <button type="button" x-show="searching" x-cloak
                @click="clearSearch()" class="pos-btn-secondary whitespace-nowrap">
            Limpiar
        </button>
    </div>

    {{-- MOBILE CARDS --}}
    <div class="sm:hidden space-y-2 mb-4"
         :class="loading ? 'opacity-50 pointer-events-none' : ''">
        <template x-for="c in customers" :key="c.id">
            <div class="pos-card" :class="c.is_generic ? 'bg-gray-50' : ''">
                <div class="pos-card-row mb-1">
                    <span class="font-semibold text-gray-800">
                        <span x-text="c.name"></span>
                        <span x-show="c.is_generic" class="ml-1 text-xs text-gray-400 italic">(GENÉRICO)</span>
                    </span>
                    <span class="flex gap-1 items-center">
                        <span x-show="c.requires_fe" class="badge-fe">FE</span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                              :class="c.active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                              x-text="c.active ? 'Activo' : 'Inactivo'"></span>
                    </span>
                </div>
                <div class="pos-card-row" x-show="c.business_name">
                    <span class="pos-card-label">Razón social</span>
                    <span class="pos-card-value truncate max-w-[60%]" x-text="c.business_name"></span>
                </div>
                <div class="pos-card-row">
                    <span class="pos-card-label">Documento</span>
                    <span class="pos-card-value" x-text="c.doc_label || '—'"></span>
                </div>
                <div class="pos-card-row" x-show="c.phone">
                    <span class="pos-card-label">Teléfono</span>
                    <span class="pos-card-value" x-text="c.phone"></span>
                </div>
                <div class="mt-2 flex gap-2 justify-end" x-show="!c.is_generic">
                    <a :href="'/customers/' + c.id + '/edit'" class="pos-btn pos-btn-secondary text-xs py-1">Editar</a>
                    <form :action="'/customers/' + c.id" method="POST" class="inline"
                          @submit.prevent="if(confirm('¿Eliminar a «' + c.name + '»?')) $el.submit()">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="pos-btn pos-btn-danger text-xs py-1">Eliminar</button>
                    </form>
                </div>
            </div>
        </template>
        <div x-show="!loading && customers.length === 0" class="text-center py-8 text-gray-400 text-sm">
            No se encontraron clientes.
        </div>
    </div>

    {{-- DESKTOP TABLE --}}
    <div class="hidden sm:block bg-white rounded-lg shadow overflow-hidden"
         :class="loading ? 'opacity-50 pointer-events-none' : ''">
        <table class="pos-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Razón social</th>
                    <th>Documento</th>
                    <th>Teléfono</th>
                    <th class="text-center">FE</th>
                    <th class="text-center">Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="c in customers" :key="c.id">
                    <tr :class="c.is_generic ? 'bg-gray-50' : ''">
                        <td class="font-medium">
                            <span x-text="c.name"></span>
                            <span x-show="c.is_generic" class="ml-1 text-xs text-gray-400 italic">(GENÉRICO)</span>
                        </td>
                        <td class="text-gray-500 max-w-56">
                            <span class="block truncate" x-text="c.business_name || '—'"></span>
                        </td>
                        <td class="text-gray-500" x-text="c.doc_label || '—'"></td>
                        <td class="text-gray-500" x-text="c.phone || '—'"></td>
                        <td class="text-center">
                            <span x-show="c.requires_fe" class="badge-fe">Sí</span>
                            <span x-show="!c.requires_fe" class="text-gray-300 text-xs">No</span>
                        </td>
                        <td class="text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                                  :class="c.active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                  x-text="c.active ? 'Activo' : 'Inactivo'"></span>
                        </td>
                        <td class="text-right space-x-2 whitespace-nowrap">
                            <a x-show="!c.is_generic"
                               :href="'/customers/' + c.id + '/edit'"
                               class="text-blue-600 hover:text-blue-800 text-xs">Editar</a>
                            <form x-show="!c.is_generic"
                                  :action="'/customers/' + c.id" method="POST" class="inline"
                                  @submit.prevent="
                                      if (confirm('¿Eliminar a «' + c.name + '»? Si tiene facturas, el historial se conserva; si no, se eliminará definitivamente.'))
                                          $el.submit()
                                  ">
                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                <input type="hidden" name="_method" value="DELETE">
                                <button type="submit" class="text-xs text-gray-400 hover:text-red-600">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                </template>
                <tr x-show="!loading && customers.length === 0">
                    <td colspan="7" class="text-center py-8 text-gray-400">
                        No se encontraron clientes.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Pagination: only when not in search mode --}}
    <div x-show="!searching" class="mt-4">
        {{ $customers->links() }}
    </div>

</div>{{-- end x-data --}}

<script>
const __initialCustomers = {!! json_encode($initialData, JSON_HEX_TAG) !!};

function customerFilter() {
    return {
        customers: __initialCustomers,
        loading: false,
        searching: {{ $search ? 'true' : 'false' }},

        async fetchCustomers() {
            const term = this.$refs.searchInput.value.trim();

            if (!term) {
                this.clearSearch();
                return;
            }

            this.searching = true;
            this.loading = true;
            const params = new URLSearchParams({ search: term });
            const res = await fetch(`/customers?${params}`, {
                headers: { 'Accept': 'application/json' },
            });
            this.customers = await res.json();
            history.replaceState({}, '', `/customers?${params}`);
            this.loading = false;
        },

        clearSearch() {
            this.$refs.searchInput.value = '';
            this.searching = false;
            this.customers = __initialCustomers;
            history.replaceState({}, '', '/customers');
        },
    };
}
</script>
@endsection
