@extends('layouts.app')
@section('title', 'Productos')

@section('content')
@php
$productsData = $products->map(fn ($p) => [
    'id'               => $p->id,
    'name'             => $p->name,
    'sale_unit'        => $p->sale_unit,
    'base_price'       => (string) $p->base_price,
    'category_id'      => $p->category_id,
    'category_name'    => $p->category?->name,
    'active'           => $p->active,
    'price_updated_at' => $p->price_updated_at?->setTimezone('America/Bogota')->format('d/m/Y H:i'),
    'price_updated_by' => $p->priceUpdatedBy?->name,
]);
$categoriesData = $categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]);
@endphp
<div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-bold text-gray-800">Precios de Productos</h1>
    <a href="{{ route('categories.index') }}" class="text-sm text-blue-600 hover:text-blue-800">Gestionar categorías →</a>
</div>

<div x-data="productFilter()">

    {{-- Filter bar --}}
    <form @submit.prevent="fetchProducts()" class="bg-white rounded-lg shadow p-3 mb-4 flex gap-3 flex-wrap items-end">
        <div>
            <label class="block text-xs text-gray-600 mb-1">Buscar</label>
            <input type="text" x-ref="searchInput" value="{{ $search }}"
                   placeholder="Nombre del producto..."
                   class="border rounded px-2 py-2 text-sm w-48"
                   @input.debounce.400ms="fetchProducts()">
        </div>
        <div>
            <label class="block text-xs text-gray-600 mb-1">Categoría</label>
            <select x-ref="categorySelect" class="border rounded px-2 py-2 text-sm"
                    @change="fetchProducts()">
                <option value="">Todas las categorías</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ $categoryId == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="pos-btn-primary">
            <span x-show="!loading">Filtrar</span>
            <span x-show="loading" x-cloak>Buscando…</span>
        </button>
        <button type="button" x-show="$refs.searchInput?.value || $refs.categorySelect?.value"
                @click="clearFilter()" class="pos-btn-secondary" x-cloak>
            Limpiar
        </button>
    </form>

    {{-- New product form --}}
    <div class="bg-white rounded-lg shadow p-4 mb-4" x-data="{ open: false }">
        <button type="button" @click="open = !open"
            class="text-blue-600 font-semibold text-sm hover:text-blue-800">
            <span x-show="!open">+ Agregar producto nuevo</span>
            <span x-show="open">— Cancelar</span>
        </button>
        <div x-show="open" x-cloak class="mt-3 pt-3 border-t">
            <form method="POST" action="{{ route('products.store') }}" class="flex gap-2 items-end flex-wrap">
                @csrf
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Nombre</label>
                    <input type="text" name="name" class="border rounded px-2 py-2 text-sm w-48" required
                        placeholder="Nombre del producto">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Categoría</label>
                    <select name="category_id" class="border rounded px-2 py-2 text-sm">
                        <option value="">Sin categoría</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Unidad</label>
                    <select name="sale_unit" class="border rounded px-2 py-2 text-sm">
                        <option value="KG">KG — por kilo</option>
                        <option value="UNIT">UNIT — por unidad</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Precio base</label>
                    <input type="number" name="base_price" min="0" step="100"
                        class="border rounded px-2 py-2 text-sm w-28" required placeholder="0">
                </div>
                <button class="pos-btn-primary">Crear</button>
            </form>
        </div>
    </div>

    {{-- Products table --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="text-left px-4 py-3 text-gray-600 font-semibold">Producto</th>
                    <th class="text-left px-4 py-3 text-gray-600 font-semibold">Categoría</th>
                    <th class="text-center px-4 py-3 text-gray-600 font-semibold">Unidad</th>
                    <th class="text-right px-4 py-3 text-gray-600 font-semibold">Precio actual</th>
                    <th class="text-left px-4 py-3 text-gray-600 font-semibold">Actualizado</th>
                    <th class="text-center px-4 py-3 text-gray-600 font-semibold">Estado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody :class="loading ? 'opacity-50 pointer-events-none' : ''">
                <template x-for="product in products" :key="product.id">
                    <tr class="hover:bg-gray-50 border-b last:border-0" x-data="productRowData(product)">
                        {{-- Name --}}
                        <td class="px-4 py-3 font-medium">
                            <span x-show="!editingName" @click="editingName=true"
                                  class="cursor-pointer hover:text-blue-600"
                                  title="Clic para editar nombre" x-text="name"></span>
                            <form x-show="editingName" x-cloak @submit.prevent="saveName()" class="flex items-center gap-1">
                                <input type="text" x-model="newName" x-ref="nameInput"
                                    @keydown.escape="cancelName()" maxlength="150"
                                    class="border rounded px-2 py-1 text-sm w-44"
                                    x-init="$watch('editingName', v => { if(v) $nextTick(()=>$refs.nameInput.select()); })">
                                <button type="submit" class="pos-btn-success py-1 text-xs">OK</button>
                                <button type="button" @click="cancelName()" class="pos-btn-secondary py-1 text-xs">✕</button>
                            </form>
                            <span x-show="savedName" x-cloak class="text-green-500 text-xs ml-1">✓</span>
                        </td>
                        {{-- Category --}}
                        <td class="px-4 py-3">
                            <div x-show="!editingCategory">
                                <span x-show="categoryName" @click="editingCategory=true"
                                      class="cursor-pointer px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700 hover:bg-blue-200"
                                      x-text="categoryName" title="Clic para cambiar categoría"></span>
                                <span x-show="!categoryName" @click="editingCategory=true"
                                      class="cursor-pointer text-gray-300 hover:text-blue-400 text-xs"
                                      title="Clic para asignar categoría">—</span>
                            </div>
                            <div x-show="editingCategory" x-cloak class="flex items-center gap-1">
                                <select x-ref="catSelect" class="border rounded px-1 py-0.5 text-xs"
                                        @change="saveCategory($event.target.value)"
                                        @keydown.escape="editingCategory=false"
                                        x-init="$watch('editingCategory', v => { if(v) $nextTick(()=>$refs.catSelect.focus()); })">
                                    <option value="">Sin categoría</option>
                                    <template x-for="cat in __allCategories" :key="cat.id">
                                        <option :value="cat.id" x-text="cat.name"></option>
                                    </template>
                                </select>
                                <button type="button" @click="editingCategory=false"
                                        class="text-xs text-gray-400 hover:text-gray-600">✕</button>
                            </div>
                        </td>
                        {{-- Unit --}}
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                                  :class="product.sale_unit === 'KG' ? 'bg-purple-100 text-purple-700' : 'bg-orange-100 text-orange-700'"
                                  x-text="product.sale_unit"></span>
                        </td>
                        {{-- Price --}}
                        <td class="px-4 py-3 text-right">
                            <span x-show="!editingPrice" @dblclick="editingPrice=true"
                                  class="font-semibold cursor-pointer hover:text-blue-600"
                                  title="Doble clic para editar precio">
                                $<span x-text="price.toLocaleString('es-CO')"></span>
                            </span>
                            <form x-show="editingPrice" x-cloak @submit.prevent="savePrice()" class="flex items-center gap-1 justify-end">
                                <span class="text-gray-500">$</span>
                                <input type="number" x-model.number="newPrice" x-ref="priceInput"
                                    @keydown.escape="editingPrice=false; priceError=''" min="0" step="100"
                                    class="border rounded px-2 py-1 text-sm w-24 text-right"
                                    x-init="$watch('editingPrice', v => { if(v) $nextTick(()=>$refs.priceInput.focus()); })">
                                <button type="submit" class="pos-btn-success py-1 text-xs">OK</button>
                                <button type="button" @click="editingPrice=false; priceError=''" class="pos-btn-secondary py-1 text-xs">✕</button>
                            </form>
                            <span x-show="savedPrice" x-cloak class="text-green-500 text-xs block mt-0.5">✓ Guardado</span>
                            <span x-show="priceError" x-cloak x-text="priceError" class="text-red-500 text-xs block mt-0.5"></span>
                        </td>
                        {{-- Updated --}}
                        <td class="px-4 py-3 text-xs text-gray-400">
                            <span x-show="product.price_updated_at">
                                <span x-text="product.price_updated_at"></span>
                                <br>
                                <span class="text-gray-500" x-text="product.price_updated_by || '—'"></span>
                            </span>
                            <span x-show="!product.price_updated_at">—</span>
                        </td>
                        {{-- Status --}}
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                                  :class="product.active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                  x-text="product.active ? 'Activo' : 'Inactivo'"></span>
                        </td>
                        {{-- Actions --}}
                        <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                            <form :action="'/products/' + productId + '/toggle'" method="POST" class="inline">
                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                <button type="submit" class="text-xs"
                                        :class="product.active ? 'text-red-400 hover:text-red-600' : 'text-green-500 hover:text-green-700'"
                                        x-text="product.active ? 'Desactivar' : 'Activar'"></button>
                            </form>
                            <form :action="'/products/' + productId" method="POST" class="inline"
                                  @submit.prevent="
                                      if(confirm('¿Eliminar ' + name + '? Si tiene historial de ventas se desactivará; si no, se eliminará definitivamente.'))
                                          $el.submit()
                                  ">
                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                <input type="hidden" name="_method" value="DELETE">
                                <button type="submit" class="text-xs text-gray-400 hover:text-red-600">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                </template>
                <tr x-show="!loading && products.length === 0">
                    <td colspan="7" class="text-center py-8 text-gray-400 text-sm">
                        No se encontraron productos.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

</div>{{-- end x-data="productFilter()" --}}

<script>
const __initialProducts = {!! json_encode($productsData, JSON_HEX_TAG) !!};
const __allCategories   = {!! json_encode($categoriesData, JSON_HEX_TAG) !!};

function productFilter() {
    return {
        products: __initialProducts,
        loading: false,
        async fetchProducts() {
            this.loading = true;
            const params = new URLSearchParams({
                search:      this.$refs.searchInput.value,
                category_id: this.$refs.categorySelect.value,
            });
            const res = await fetch(`/products?${params}`, {
                headers: { 'Accept': 'application/json' },
            });
            this.products = await res.json();
            history.replaceState({}, '', `/products?${params}`);
            this.loading = false;
        },
        clearFilter() {
            this.$refs.searchInput.value = '';
            this.$refs.categorySelect.value = '';
            this.fetchProducts();
        },
    };
}

function productRowData(p) {
    const csrf = () => document.querySelector('meta[name="csrf-token"]').content;
    return {
        productId: p.id,
        product: p,
        // ── Price ──
        editingPrice: false,
        savedPrice: false,
        priceError: '',
        price: parseFloat(p.base_price),
        newPrice: parseFloat(p.base_price),
        async savePrice() {
            this.priceError = '';
            try {
                const res = await fetch(`/products/${this.productId}/price`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ base_price: this.newPrice }),
                });
                const data = await res.json();
                if (data.success) {
                    this.price = this.newPrice;
                    this.editingPrice = false;
                    this.savedPrice = true;
                    setTimeout(() => this.savedPrice = false, 2000);
                } else {
                    this.priceError = data.message || 'Error al guardar.';
                }
            } catch {
                this.priceError = 'Error de conexión.';
            }
        },
        // ── Name ──
        editingName: false,
        savedName: false,
        name: p.name,
        newName: p.name,
        async saveName() {
            const res = await fetch(`/products/${this.productId}/name`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ name: this.newName }),
            });
            const data = await res.json();
            if (data.success) {
                this.name = data.name;
                this.newName = data.name;
                this.editingName = false;
                this.savedName = true;
                setTimeout(() => this.savedName = false, 2000);
            }
        },
        cancelName() {
            this.newName = this.name;
            this.editingName = false;
        },
        // ── Category ──
        editingCategory: false,
        categoryId: p.category_id,
        categoryName: p.category_name,
        async saveCategory(newCategoryId) {
            const res = await fetch(`/products/${this.productId}/category`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ category_id: newCategoryId || null }),
            });
            const data = await res.json();
            if (data.success) {
                this.categoryId = data.category_id;
                this.categoryName = data.category_name;
                this.editingCategory = false;
            }
        },
    };
}
</script>
@endsection
