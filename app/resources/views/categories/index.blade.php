@extends('layouts.app')
@section('title', 'Categorías')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-bold text-gray-800">Categorías de Productos</h1>
    <a href="{{ route('products.index') }}" class="text-sm text-blue-600 hover:text-blue-800">← Volver a Productos</a>
</div>

{{-- New category form --}}
<div class="bg-white rounded-lg shadow p-4 mb-4" x-data="{ open: false }">
    <button type="button" @click="open = !open"
        class="text-blue-600 font-semibold text-sm hover:text-blue-800">
        <span x-show="!open">+ Agregar categoría nueva</span>
        <span x-show="open">— Cancelar</span>
    </button>
    <div x-show="open" x-cloak class="mt-3 pt-3 border-t">
        <form method="POST" action="{{ route('categories.store') }}" class="flex gap-2 items-end">
            @csrf
            <div>
                <label class="block text-xs text-gray-600 mb-1">Nombre</label>
                <input type="text" name="name" class="border rounded px-2 py-2 text-sm w-56" required
                    placeholder="Ej: Res, Cerdo, Embutidos">
            </div>
            <button class="pos-btn-primary">Crear</button>
        </form>
    </div>
</div>

{{-- Categories table --}}
<div class="bg-white rounded-lg shadow overflow-hidden">
    @if($categories->isEmpty())
        <p class="text-gray-500 text-sm text-center py-8">No hay categorías aún. Agrega la primera arriba.</p>
    @else
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="text-left px-4 py-3 text-gray-600 font-semibold">Nombre</th>
                <th class="text-center px-4 py-3 text-gray-600 font-semibold">Productos asignados</th>
                <th class="text-center px-4 py-3 text-gray-600 font-semibold">Estado</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @foreach($categories as $category)
            <tr class="hover:bg-gray-50"
                x-data="categoryRow({{ $category->id }}, @js($category->name), {{ $category->active ? 'true' : 'false' }}, {{ $category->products_count }})">
                {{-- Name: click to edit inline --}}
                <td class="px-4 py-3 font-medium">
                    <span x-show="!editingName" @click="editingName=true"
                          class="cursor-pointer hover:text-blue-600"
                          title="Clic para editar nombre" x-text="name"></span>
                    <form x-show="editingName" x-cloak @submit.prevent="saveName()" class="flex items-center gap-1">
                        <input type="text" x-model="newName" x-ref="nameInput"
                            @keydown.escape="cancelName()" maxlength="100"
                            class="border rounded px-2 py-1 text-sm w-48"
                            x-init="$watch('editingName', v => { if(v) $nextTick(()=>$refs.nameInput.select()); })">
                        <button type="submit" class="pos-btn-success py-1 text-xs">OK</button>
                        <button type="button" @click="cancelName()" class="pos-btn-secondary py-1 text-xs">✕</button>
                    </form>
                    <span x-show="savedName" x-cloak class="text-green-500 text-xs ml-1">✓</span>
                </td>
                <td class="px-4 py-3 text-center text-gray-500" x-text="productCount"></td>
                <td class="px-4 py-3 text-center">
                    <span :class="active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                          class="px-2 py-0.5 rounded-full text-xs font-semibold"
                          x-text="active ? 'Activa' : 'Inactiva'"></span>
                </td>
                <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                    {{-- Toggle active --}}
                    <form method="POST" action="{{ route('categories.toggle', $category) }}" class="inline">
                        @csrf
                        <button class="text-xs {{ $category->active ? 'text-red-400 hover:text-red-600' : 'text-green-500 hover:text-green-700' }}">
                            {{ $category->active ? 'Desactivar' : 'Activar' }}
                        </button>
                    </form>
                    {{-- Delete (confirm via Alpine so we can show product count) --}}
                    <form method="POST" action="{{ route('categories.destroy', $category) }}" class="inline"
                          @submit.prevent="
                              const msg = productCount > 0
                                  ? 'Esta categoría tiene ' + productCount + ' producto(s). Al eliminarla quedarán sin categoría. ¿Continuar?'
                                  : '¿Eliminar la categoría «' + name + '»?';
                              if (confirm(msg)) $el.submit()
                          ">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs text-gray-400 hover:text-red-600">Eliminar</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</div>

<script>
function categoryRow(categoryId, currentName, currentActive, currentCount) {
    const csrf = () => document.querySelector('meta[name="csrf-token"]').content;
    return {
        categoryId,
        name: currentName,
        newName: currentName,
        active: currentActive,
        productCount: currentCount,
        editingName: false,
        savedName: false,
        async saveName() {
            const res = await fetch(`/categories/${this.categoryId}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ name: this.newName, active: this.active }),
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
