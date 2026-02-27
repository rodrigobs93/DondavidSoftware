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

    {{-- Table --}}
    <div class="bg-white rounded-lg shadow overflow-hidden"
         :class="loading ? 'opacity-50 pointer-events-none' : ''">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="text-left px-4 py-3 text-gray-600 font-semibold">Nombre</th>
                    <th class="text-left px-4 py-3 text-gray-600 font-semibold">Documento</th>
                    <th class="text-left px-4 py-3 text-gray-600 font-semibold">Teléfono</th>
                    <th class="text-center px-4 py-3 text-gray-600 font-semibold">FE</th>
                    <th class="text-center px-4 py-3 text-gray-600 font-semibold">Estado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <template x-for="c in customers" :key="c.id">
                    <tr class="hover:bg-gray-50" :class="c.is_generic ? 'bg-gray-50' : ''">
                        <td class="px-4 py-3 font-medium">
                            <span x-text="c.name"></span>
                            <span x-show="c.is_generic" class="ml-1 text-xs text-gray-400 italic">(GENÉRICO)</span>
                        </td>
                        <td class="px-4 py-3 text-gray-500" x-text="c.doc_label || '—'"></td>
                        <td class="px-4 py-3 text-gray-500" x-text="c.phone || '—'"></td>
                        <td class="px-4 py-3 text-center">
                            <span x-show="c.requires_fe"
                                  class="px-2 py-0.5 rounded-full text-xs bg-blue-100 text-blue-700 font-semibold">Sí</span>
                            <span x-show="!c.requires_fe" class="text-gray-300 text-xs">No</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                                  :class="c.active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                  x-text="c.active ? 'Activo' : 'Inactivo'"></span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
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
                    <td colspan="6" class="text-center py-8 text-gray-400 text-sm">
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
