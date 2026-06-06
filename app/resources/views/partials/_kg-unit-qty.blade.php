{{--
    Reusable KG/UNIT quantity input — same scale behavior as /sales/new.

    Requires Alpine scope to provide:
      - `row` : { sale_unit: 'KG'|'UNIT', quantity, gramsDisplay }
      - `idx` : row index (for the items[idx][quantity] field name)

    `row.quantity` always holds the value the server receives:
      - KG   -> kilograms (grams / 1000)
      - UNIT -> integer units

    All parsing/formatting uses the shared window.KgGrams helpers (defined in the
    layout) so this stays consistent with the Sales module.
--}}
<div class="col-span-2">
    {{-- KG: user types GRAMS from the scale; stored internally as kg --}}
    <template x-if="row.sale_unit === 'KG'">
        <div>
            <div class="flex items-center gap-1">
                <input type="text" inputmode="numeric" data-keyboard="numeric"
                       :value="row.gramsDisplay"
                       @input="row.gramsDisplay = window.KgGrams.rawGrams($event.target.value); row.quantity = window.KgGrams.toKg(row.gramsDisplay)"
                       @blur="row.gramsDisplay = window.KgGrams.formatGrams(row.quantity)"
                       placeholder="gramos"
                       class="w-full border rounded px-2 py-1 text-sm">
                <span class="text-xs text-gray-400">g</span>
            </div>
            <input type="hidden" :name="`items[${idx}][quantity]`" :value="row.quantity || 0">
            <p class="text-[11px] text-green-700 leading-tight mt-0.5"
               x-show="(parseFloat(row.quantity) || 0) > 0"
               x-text="'Equivale a: ' + window.KgGrams.kgLabel(row.quantity) + ' kg'"></p>
        </div>
    </template>
    {{-- UNIT: integer units --}}
    <template x-if="row.sale_unit !== 'KG'">
        <input type="number" step="1" min="0" :name="`items[${idx}][quantity]`" x-model="row.quantity"
               placeholder="Cant." data-keyboard="numeric"
               class="w-full border rounded px-2 py-1 text-sm">
    </template>
</div>
