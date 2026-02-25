@extends('layouts.app')
@section('title', 'Productos')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-bold text-gray-800">Precios de Productos</h1>
</div>

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

{{-- Products table with inline price editing --}}
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="text-left px-4 py-3 text-gray-600 font-semibold">Producto</th>
                <th class="text-center px-4 py-3 text-gray-600 font-semibold">Unidad</th>
                <th class="text-right px-4 py-3 text-gray-600 font-semibold">Precio actual</th>
                <th class="text-left px-4 py-3 text-gray-600 font-semibold">Actualizado</th>
                <th class="text-center px-4 py-3 text-gray-600 font-semibold">Estado</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @foreach($products as $product)
            <tr class="hover:bg-gray-50" x-data="productRow({{ $product->id }}, {{ $product->base_price }}, @js($product->name))">
                {{-- Name: click to edit --}}
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
                <td class="px-4 py-3 text-center">
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold
                        {{ $product->sale_unit === 'KG' ? 'bg-purple-100 text-purple-700' : 'bg-orange-100 text-orange-700' }}">
                        {{ $product->sale_unit }}
                    </span>
                </td>
                {{-- Price: double-click to edit --}}
                <td class="px-4 py-3 text-right">
                    <span x-show="!editingPrice" @dblclick="editingPrice=true"
                          class="font-semibold cursor-pointer hover:text-blue-600"
                          title="Doble clic para editar precio">
                        $<span x-text="price.toLocaleString('es-CO')"></span>
                    </span>
                    <form x-show="editingPrice" x-cloak @submit.prevent="savePrice()" class="flex items-center gap-1 justify-end">
                        <span class="text-gray-500">$</span>
                        <input type="number" x-model.number="newPrice" x-ref="priceInput"
                            @keydown.escape="editingPrice=false" min="0" step="100"
                            class="border rounded px-2 py-1 text-sm w-24 text-right"
                            x-init="$watch('editingPrice', v => { if(v) $nextTick(()=>$refs.priceInput.focus()); })">
                        <button type="submit" class="pos-btn-success py-1 text-xs">OK</button>
                        <button type="button" @click="editingPrice=false" class="pos-btn-secondary py-1 text-xs">✕</button>
                    </form>
                    <span x-show="savedPrice" x-cloak class="text-green-500 text-xs ml-1">✓ Guardado</span>
                </td>
                <td class="px-4 py-3 text-xs text-gray-400">
                    @if($product->price_updated_at)
                        {{ $product->price_updated_at->setTimezone('America/Bogota')->format('d/m/Y H:i') }}
                        <br>
                        <span class="text-gray-500">{{ $product->priceUpdatedBy?->name ?? '—' }}</span>
                    @else
                        —
                    @endif
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="{{ $product->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}
                        px-2 py-0.5 rounded-full text-xs font-semibold">
                        {{ $product->active ? 'Activo' : 'Inactivo' }}
                    </span>
                </td>
                <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                    {{-- Toggle active --}}
                    <form method="POST" action="{{ route('products.toggle', $product) }}" class="inline">
                        @csrf
                        <button class="text-xs {{ $product->active ? 'text-red-400 hover:text-red-600' : 'text-green-500 hover:text-green-700' }}">
                            {{ $product->active ? 'Desactivar' : 'Activar' }}
                        </button>
                    </form>
                    {{-- Delete --}}
                    <form method="POST" action="{{ route('products.destroy', $product) }}" class="inline"
                          onsubmit="return confirm('¿Eliminar {{ addslashes($product->name) }}? Si tiene historial de ventas se desactivará; si no, se eliminará definitivamente.')">
                        @csrf
                        @method('DELETE')
                        <button class="text-xs text-gray-400 hover:text-red-600" title="Eliminar producto">
                            Eliminar
                        </button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<script>
function productRow(productId, currentPrice, currentName) {
    const csrf = () => document.querySelector('meta[name="csrf-token"]').content;
    return {
        productId,
        // ── Price ──
        editingPrice: false,
        savedPrice: false,
        price: parseFloat(currentPrice),
        newPrice: parseFloat(currentPrice),
        async savePrice() {
            const res = await fetch(`/products/${this.productId}/price`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ base_price: this.newPrice }),
            });
            const data = await res.json();
            if (data.success) {
                this.price = this.newPrice;
                this.editingPrice = false;
                this.savedPrice = true;
                setTimeout(() => this.savedPrice = false, 2000);
            }
        },
        // ── Name ──
        editingName: false,
        savedName: false,
        name: currentName,
        newName: currentName,
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
    };
}
</script>
@endsection
