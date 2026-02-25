@extends('layouts.app')
@section('title', 'Editar Cliente')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <h1 class="text-xl font-bold text-gray-800">Editar Cliente</h1>

    {{-- Main customer form --}}
    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('customers.update', $customer) }}">
            @csrf
            @method('PUT')
            @include('customers._form', ['customer' => $customer])
            <div class="mt-4">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="active" value="1" @checked($customer->active)>
                    Cliente activo
                </label>
            </div>
            <div class="flex gap-2 mt-4">
                <button class="pos-btn-primary">Actualizar</button>
                <a href="{{ route('customers.index') }}" class="pos-btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>

    {{-- Special prices --}}
    <div class="bg-white rounded-lg shadow p-6"
         x-data="specialPrices({{ $customer->id }})" x-init="load()">

        <h2 class="font-semibold text-gray-700 mb-4">Precios especiales</h2>

        {{-- Existing prices table --}}
        <div x-show="prices.length > 0" class="mb-4 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-left px-3 py-2 text-gray-600">Producto</th>
                        <th class="text-center px-3 py-2 text-gray-600">Unidad</th>
                        <th class="text-right px-3 py-2 text-gray-600">Precio especial</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <template x-for="row in prices" :key="row.id">
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 font-medium" x-text="row.product.name"></td>
                            <td class="px-3 py-2 text-center">
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                                      :class="row.product.sale_unit === 'KG' ? 'bg-purple-100 text-purple-700' : 'bg-orange-100 text-orange-700'"
                                      x-text="row.product.sale_unit"></span>
                            </td>
                            <td class="px-3 py-2 text-right font-semibold text-green-700">
                                $<span x-text="parseFloat(row.price).toLocaleString('es-CO')"></span>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <button type="button" @click="remove(row)"
                                    class="text-xs text-gray-400 hover:text-red-600">Quitar</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div x-show="prices.length === 0" class="text-sm text-gray-400 mb-4">
            Este cliente no tiene precios especiales. Usa el formulario de abajo para agregar uno.
        </div>

        {{-- Add / update special price form --}}
        <div class="border-t pt-4">
            <p class="text-sm font-medium text-gray-700 mb-3">Agregar o actualizar precio especial</p>
            <div class="flex gap-2 flex-wrap items-end">
                {{-- Product search --}}
                <div class="relative flex-1 min-w-40">
                    <label class="block text-xs text-gray-500 mb-1">Producto</label>
                    <input type="text" x-model="productSearch"
                        @input.debounce.200ms="searchProducts()"
                        @focus="showDrop=true" @keydown.escape="showDrop=false"
                        placeholder="Buscar producto..."
                        class="w-full border rounded px-2 py-2 text-sm">
                    <div x-show="showDrop && productResults.length > 0"
                         class="absolute z-20 w-full bg-white border rounded shadow-lg mt-1 max-h-40 overflow-auto">
                        <template x-for="p in productResults" :key="p.id">
                            <button type="button" @click="selectProduct(p)"
                                class="w-full text-left px-3 py-2 hover:bg-blue-50 text-sm">
                                <span x-text="p.name"></span>
                                <span class="text-gray-400 text-xs ml-1"
                                      x-text="'(' + p.sale_unit + ')'"></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Price input --}}
                <div class="w-32">
                    <label class="block text-xs text-gray-500 mb-1">Precio $</label>
                    <input type="number" x-model.number="newPrice" min="0" step="100"
                        placeholder="0"
                        class="w-full border rounded px-2 py-2 text-sm text-right">
                </div>

                <button type="button" @click="save()"
                    :disabled="!selectedProduct || newPrice === ''"
                    class="pos-btn-primary py-2"
                    :class="(!selectedProduct || newPrice === '') ? 'opacity-50 cursor-not-allowed' : ''">
                    Guardar
                </button>
            </div>

            <div x-show="selectedProduct" class="mt-2 text-xs text-gray-500">
                Producto seleccionado: <strong x-text="selectedProduct?.name"></strong>
                (<span x-text="selectedProduct?.sale_unit"></span>)
            </div>
            <div x-show="saveMsg" class="mt-2 text-xs text-green-600 font-medium" x-text="saveMsg"></div>
        </div>
    </div>
</div>

<script>
function specialPrices(customerId) {
    const csrf = () => document.querySelector('meta[name="csrf-token"]').content;
    return {
        customerId,
        prices: [],
        productSearch: '',
        productResults: [],
        showDrop: false,
        selectedProduct: null,
        newPrice: '',
        saveMsg: '',

        async load() {
            const res = await fetch(`/customers/${this.customerId}/prices`);
            this.prices = await res.json();
        },

        async searchProducts() {
            if (this.productSearch.length < 1) { this.productResults = []; return; }
            const res = await fetch('/products/search?q=' + encodeURIComponent(this.productSearch));
            this.productResults = await res.json();
            this.showDrop = true;
        },

        selectProduct(p) {
            this.selectedProduct = p;
            this.productSearch = p.name;
            this.showDrop = false;
            // Pre-fill price if this product already has a special price
            const existing = this.prices.find(r => r.product_id === p.id);
            this.newPrice = existing ? parseFloat(existing.price) : '';
        },

        async save() {
            if (!this.selectedProduct || this.newPrice === '') return;
            const res = await fetch(`/customers/${this.customerId}/prices`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ product_id: this.selectedProduct.id, price: this.newPrice }),
            });
            const data = await res.json();
            if (data.success) {
                await this.load();
                this.productSearch = '';
                this.selectedProduct = null;
                this.newPrice = '';
                this.saveMsg = '✓ Precio guardado.';
                setTimeout(() => this.saveMsg = '', 3000);
            }
        },

        async remove(row) {
            if (!confirm(`¿Quitar precio especial de "${row.product.name}"?`)) return;
            await fetch(`/customers/${this.customerId}/prices/${row.product_id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrf() },
            });
            await this.load();
        },
    };
}
</script>
@endsection
