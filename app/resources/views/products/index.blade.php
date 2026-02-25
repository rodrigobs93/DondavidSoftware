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
            <tr class="hover:bg-gray-50" x-data="priceEditor({{ $product->id }}, {{ $product->base_price }})">
                <td class="px-4 py-3 font-medium">{{ $product->name }}</td>
                <td class="px-4 py-3 text-center">
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold
                        {{ $product->sale_unit === 'KG' ? 'bg-purple-100 text-purple-700' : 'bg-orange-100 text-orange-700' }}">
                        {{ $product->sale_unit }}
                    </span>
                </td>
                <td class="px-4 py-3 text-right">
                    <span x-show="!editing" @dblclick="editing=true"
                          class="font-semibold cursor-pointer hover:text-blue-600"
                          title="Doble clic para editar">
                        $<span x-text="price.toLocaleString('es-CO')"></span>
                    </span>
                    <form x-show="editing" x-cloak @submit.prevent="save()" class="flex items-center gap-1 justify-end">
                        <span class="text-gray-500">$</span>
                        <input type="number" x-model.number="newPrice" x-ref="priceInput"
                            @keydown.escape="editing=false" min="0" step="100"
                            class="border rounded px-2 py-1 text-sm w-24 text-right" x-init="$watch('editing', v => { if(v) $nextTick(()=>$refs.priceInput.focus()); })">
                        <button type="submit" class="pos-btn-success py-1 text-xs">OK</button>
                        <button type="button" @click="editing=false" class="pos-btn-secondary py-1 text-xs">✕</button>
                    </form>
                    <span x-show="saved" x-cloak class="text-green-500 text-xs ml-1">✓ Guardado</span>
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
                <td class="px-4 py-3 text-right">
                    <form method="POST" action="{{ route('products.toggle', $product) }}" class="inline">
                        @csrf
                        <button class="text-xs {{ $product->active ? 'text-red-400 hover:text-red-600' : 'text-green-500 hover:text-green-700' }}">
                            {{ $product->active ? 'Desactivar' : 'Activar' }}
                        </button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<script>
function priceEditor(productId, currentPrice) {
    return {
        editing: false,
        saved: false,
        price: parseFloat(currentPrice),
        newPrice: parseFloat(currentPrice),
        productId,
        async save() {
            const res = await fetch(`/products/${this.productId}/price`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ base_price: this.newPrice }),
            });
            const data = await res.json();
            if (data.success) {
                this.price = this.newPrice;
                this.editing = false;
                this.saved = true;
                setTimeout(() => this.saved = false, 2000);
            }
        }
    };
}
</script>
@endsection
