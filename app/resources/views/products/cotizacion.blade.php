@extends('layouts.app')
@section('title', 'Cotización')

@section('content')
@php
$productsData = $products->map(fn ($p) => [
    'id'            => $p->id,
    'name'          => $p->name,
    'sale_unit'     => $p->sale_unit,
    'base_price'    => (string) $p->base_price,
    'category_id'   => $p->category_id,
    'category_name' => $p->category?->name,
]);
$categoriesData = $categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]);
@endphp
@include('products._tabs', ['active' => 'cotizacion'])

<div x-data="cotizacionBuilder()">

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- ── Picker ─────────────────────────────────────────────────────── --}}
        <div class="bg-white rounded-lg shadow">
            <div class="p-3 border-b border-gray-100">
                <div class="flex gap-3 flex-wrap items-end">
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Buscar</label>
                        <input type="text" x-model.debounce.200ms="search"
                               placeholder="Nombre del producto..."
                               class="border rounded px-2 py-2 text-sm w-48">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Categoría</label>
                        <select x-model="categoryId" class="border rounded px-2 py-2 text-sm">
                            <option value="">Todas las categorías</option>
                            <template x-for="cat in categories" :key="cat.id">
                                <option :value="String(cat.id)" x-text="cat.name"></option>
                            </template>
                        </select>
                    </div>
                </div>
                <div class="flex gap-2 flex-wrap items-center mt-3">
                    <button type="button" class="pos-btn pos-btn-secondary" @click="selectAllFiltered()">
                        Seleccionar todos (<span x-text="filtered.filter(p => hasPrice(p)).length"></span>)
                    </button>
                    <button type="button" class="pos-btn pos-btn-ghost" x-show="selectedIds.size > 0"
                            @click="clearSelection()">
                        Quitar selección
                    </button>
                    <span class="text-sm text-gray-500 ml-auto"
                          x-text="selectedIds.size + ' seleccionado(s)'"></span>
                </div>
            </div>

            <div class="max-h-[60vh] overflow-y-auto">
                <template x-for="p in filtered" :key="p.id">
                    <label class="flex items-center gap-3 px-3 py-2 border-b border-gray-100 cursor-pointer hover:bg-gray-50"
                           style="min-height: 44px;"
                           :class="{ 'opacity-50 cursor-not-allowed': !hasPrice(p) }">
                        <input type="checkbox" class="w-5 h-5 shrink-0"
                               :checked="selectedIds.has(p.id)"
                               :disabled="!hasPrice(p)"
                               @change="toggle(p.id)">
                        <span class="flex-1 font-medium text-gray-800" x-text="p.name"></span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold shrink-0"
                              :class="p.sale_unit === 'KG' ? 'bg-purple-100 text-purple-700' : 'bg-orange-100 text-orange-700'"
                              x-text="p.sale_unit === 'KG' ? 'kg' : 'und'"></span>
                        <template x-if="hasPrice(p)">
                            <span class="text-sm text-gray-700 tabular-nums shrink-0" x-text="formatCOP(p.base_price)"></span>
                        </template>
                        <template x-if="!hasPrice(p)">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500 shrink-0">Sin precio</span>
                        </template>
                    </label>
                </template>
                <p x-show="filtered.length === 0" x-cloak class="p-4 text-sm text-gray-500 text-center">
                    No se encontraron productos.
                </p>
            </div>
        </div>

        {{-- ── Preview ────────────────────────────────────────────────────── --}}
        <div class="bg-white rounded-lg shadow p-4 lg:sticky lg:top-4 self-start">
            <h2 class="text-base font-bold text-gray-800 mb-3">Vista previa</h2>

            <template x-if="selectedIds.size > 0">
                <pre class="whitespace-pre-wrap font-mono text-sm bg-gray-50 rounded p-3 mb-3" x-text="quoteText"></pre>
            </template>
            <template x-if="selectedIds.size === 0">
                <p class="text-sm text-gray-500 bg-gray-50 rounded p-3 mb-3">
                    Selecciona productos para generar la cotización.
                </p>
            </template>

            <div x-show="errorMsg" x-cloak class="mb-3 p-2 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg" x-text="errorMsg"></div>
            <div x-show="successMsg" x-cloak class="mb-3 p-2 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg" x-text="successMsg"></div>

            <div class="flex gap-2 flex-wrap">
                <button type="button" class="pos-btn pos-btn-secondary"
                        :disabled="selectedIds.size === 0"
                        @click="copyText()">
                    Copiar cotización
                </button>
                <button type="button" class="pos-btn pos-btn-primary"
                        :disabled="printing || selectedIds.size === 0"
                        @click="printQuote()"
                        x-text="printing ? 'Imprimiendo…' : 'Imprimir cotización'">
                </button>
                <button type="button" class="pos-btn pos-btn-ghost" @click="clearSelection()">
                    Cancelar
                </button>
            </div>
        </div>

    </div>
</div>

<script>
const __cotProducts   = {!! json_encode($productsData, JSON_HEX_TAG) !!};
const __cotCategories = {!! json_encode($categoriesData, JSON_HEX_TAG) !!};
const __cotShopName   = @json($shop['name']);
const __cotToday      = @json($today);

function cotizacionBuilder() {
    return {
        products:    __cotProducts,
        categories:  __cotCategories,
        search:      '',
        categoryId:  '',
        selectedIds: new Set(),      // reassigned on mutation so Alpine reacts
        printing:    false,
        successMsg:  '',
        errorMsg:    '',

        hasPrice(p) { return parseFloat(p.base_price) > 0; },

        get filtered() {
            const q = this.search.trim().toLowerCase();
            return this.products.filter(p =>
                (!q || p.name.toLowerCase().includes(q)) &&
                (!this.categoryId || String(p.category_id) === this.categoryId));
        },
        get selectedProducts() {
            return this.products.filter(p => this.selectedIds.has(p.id));
        },
        // Selected products grouped by category (alphabetical), uncategorized last as "Otros"
        // — same grouping the server applies to the printed ticket.
        get groupedSelected() {
            const groups = new Map();
            for (const p of this.selectedProducts) {
                const label = p.category_name || 'Otros';
                if (!groups.has(label)) groups.set(label, []);
                groups.get(label).push(p);
            }
            return [...groups.entries()]
                .sort((a, b) =>
                    (a[0] === 'Otros') - (b[0] === 'Otros')
                    || a[0].localeCompare(b[0], 'es'))
                .map(([label, items]) => ({ label, items }));
        },
        get quoteText() {
            if (this.selectedIds.size === 0) return '';
            const sections = this.groupedSelected.map(g =>
                g.label.toUpperCase() + '\n' + g.items.map(p =>
                    `- ${p.name}: ${formatCOP(p.base_price)} / ${p.sale_unit === 'KG' ? 'kg' : 'unidad'}`
                ).join('\n'));
            return `COTIZACION\n${__cotShopName}\nFecha: ${__cotToday}\n\nPRODUCTOS\n\n${sections.join('\n\n')}\n\n`
                 + 'Nota: Precios sujetos a cambios segun disponibilidad y actualizacion del dia.';
        },

        toggle(id) {
            const s = new Set(this.selectedIds);
            s.has(id) ? s.delete(id) : s.add(id);
            this.selectedIds = s;
            this.successMsg = ''; this.errorMsg = '';
        },
        selectAllFiltered() {
            const s = new Set(this.selectedIds);
            this.filtered.filter(p => this.hasPrice(p)).forEach(p => s.add(p.id));
            this.selectedIds = s;
            this.successMsg = ''; this.errorMsg = '';
        },
        clearSelection() {
            this.selectedIds = new Set();
            this.successMsg = ''; this.errorMsg = '';
        },

        async copyText() {
            this.errorMsg = ''; this.successMsg = '';
            const text = this.quoteText;
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(text);
                } else {
                    // Fallback: la app corre por http:// en LAN → Clipboard API no disponible
                    const ta = document.createElement('textarea');
                    ta.value = text;
                    ta.style.position = 'fixed';
                    ta.style.opacity = '0';
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    ta.remove();
                }
                this.successMsg = 'Cotización copiada al portapapeles.';
                setTimeout(() => this.successMsg = '', 3000);
            } catch {
                this.errorMsg = 'No se pudo copiar. Selecciona el texto y cópialo manualmente.';
            }
        },

        async printQuote() {
            this.errorMsg = ''; this.successMsg = '';
            if (this.selectedIds.size === 0) {
                this.errorMsg = 'Selecciona al menos un producto para la cotización.';
                return;
            }
            this.printing = true;
            try {
                const res = await fetch('{{ route('products.cotizacion.print') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ product_ids: [...this.selectedIds] }),
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.ok) {
                    this.successMsg = 'Cotización impresa.';
                    setTimeout(() => this.successMsg = '', 3000);
                } else {
                    this.errorMsg = data.error
                        || data.message
                        || 'Error al imprimir. Verifica la impresora.';
                }
            } catch {
                this.errorMsg = 'Error de conexión. Verifica la impresora.';
            } finally {
                this.printing = false;
            }
        },
    };
}
</script>
@endsection
