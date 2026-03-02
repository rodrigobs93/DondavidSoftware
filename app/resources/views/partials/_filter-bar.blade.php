{{--
    Shared filter bar row: search input + date pickers + clear button + spinner.

    Requires the parent Alpine.js component to expose:
      - q (string)          : search term, bound with x-model
      - startDate (string)  : date from, bound with x-model
      - endDate (string)    : date to, bound with x-model
      - loading (bool)      : shows spinner while fetching
      - hasFilters (getter) : controls visibility of Clear button
      - search()            : method called on input / change events
      - clearFilters()      : method called by Clear button

    Optional variable: $placeholder (string) — input placeholder text.
--}}
<div class="flex gap-2 flex-wrap items-end">
    <div class="flex-1 min-w-[200px]">
        <label class="block text-xs text-gray-500 mb-1">Buscar</label>
        <input type="text" x-model="q" @input.debounce.400ms="search()"
               placeholder="{{ $placeholder ?? 'Consecutivo, cliente o razón social…' }}"
               class="border rounded px-3 py-2 text-sm w-full">
    </div>
    <div>
        <label class="block text-xs text-gray-500 mb-1">Desde</label>
        <input type="date" x-model="startDate" @change="search()"
               class="border rounded px-2 py-2 text-sm">
    </div>
    <div>
        <label class="block text-xs text-gray-500 mb-1">Hasta</label>
        <input type="date" x-model="endDate" @change="search()"
               class="border rounded px-2 py-2 text-sm">
    </div>
    <button type="button" x-show="hasFilters" x-cloak
            @click="clearFilters()" class="pos-btn-secondary self-end">
        Limpiar
    </button>
    <span x-show="loading" x-cloak class="text-sm text-gray-400 self-end pb-2">Buscando…</span>
</div>
