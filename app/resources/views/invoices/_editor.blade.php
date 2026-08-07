{{-- Inline invoice editor (edit mode). Runs inside the invoiceEditor() Alpine
     scope defined in invoices/show.blade.php. Only rendered for admins on
     non-FE-issued invoices ($canEdit). --}}
<div x-show="editing" x-cloak>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded p-2 mb-3">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    {{-- novalidate: the visible qty/price editors carry no name (data travels via
         the hidden items[..] inputs) and the inactive KG/UNIT editor stays in the
         DOM hidden. Native HTML5 validation would try to validate that hidden,
         non-focusable control and silently block the submit. We validate in Alpine
         (canSave) and again server-side, so native validation is not needed. --}}
    <form method="POST" action="{{ route('invoices.update', $invoice) }}"
          novalidate @submit.prevent="submitForm($event)">
        @csrf
        @method('PUT')
        <input type="hidden" name="customer_id"  :value="customerId">
        <input type="hidden" name="invoice_date" :value="invoiceDate">

        {{-- Date + Customer --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Fecha de factura</label>
                <input type="date" x-model="invoiceDate" :max="todayStr"
                       class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>
            <div class="relative">
                <label class="block text-xs font-medium text-gray-600 mb-1">Cliente</label>
                <div class="text-sm mb-1">Actual: <strong x-text="customerName"></strong>
                    <span x-show="custIsGeneric" class="text-xs text-gray-400">(GENÉRICO)</span>
                </div>
                <input type="text" x-model="customerSearch"
                       @input.debounce.300ms="searchCustomers()"
                       @focus="showCustomerDropdown = true"
                       @keydown.escape="showCustomerDropdown = false"
                       placeholder="Cambiar cliente…" autocomplete="off"
                       class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                <div x-show="showCustomerDropdown && customerResults.length > 0"
                     class="absolute z-30 w-full bg-white border rounded shadow-lg mt-1 max-h-48 overflow-auto">
                    <template x-for="c in customerResults" :key="c.id">
                        <button type="button" @click="selectCustomer(c)"
                                class="w-full text-left px-3 py-2 hover:bg-blue-50 text-sm"
                                :class="c.is_generic ? 'text-gray-500 italic' : ''">
                            <span x-text="c.name"></span>
                            <span x-show="c.business_name" class="text-xs text-gray-500 ml-1 italic"
                                  x-text="'· ' + c.business_name"></span>
                            <span x-show="c.doc_number" class="text-xs text-gray-400 ml-1"
                                  x-text="'(' + (c.doc_type||'') + ' ' + (c.doc_number||'') + ')'"></span>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        {{-- FE warning --}}
        <p x-show="feError" x-cloak class="text-red-600 text-xs mb-3" x-text="feError"></p>

        {{-- Items --}}
        <div class="border-t border-b divide-y">
            <template x-for="(item, idx) in items" :key="item._key">
                <div class="py-2.5">
                    <template x-if="item.product_id">
                        <input type="hidden" :name="'items['+idx+'][product_id]'" :value="item.product_id">
                    </template>
                    <input type="hidden" :name="'items['+idx+'][product_name]'" :value="item.product_name">
                    <input type="hidden" :name="'items['+idx+'][sale_unit]'"    :value="item.sale_unit">
                    <input type="hidden" :name="'items['+idx+'][unit_price]'"   :value="item.unit_price">
                    <input type="hidden" :name="'items['+idx+'][quantity]'"     :value="item.quantity">

                    {{-- Row 1: name + actions --}}
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-sm font-medium truncate">
                            <span x-text="item.product_name"></span>
                            <span x-show="!item.product_id"
                                  class="ml-1 align-middle px-1.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">temporal</span>
                        </span>
                        <div class="flex items-center gap-1 shrink-0">
                            <button type="button" @click="openSwap(idx)"
                                    class="pos-btn-link text-xs">Cambiar</button>
                            <button type="button" @click="removeItem(idx)"
                                    class="pos-btn-icon pos-btn-icon-danger">&times;</button>
                        </div>
                    </div>

                    {{-- Row 2: qty + price + line total --}}
                    <div class="flex items-center gap-2 mt-1">
                        {{-- Quantity --}}
                        <div class="flex items-center gap-1 w-28 shrink-0">
                            {{-- KG: grams --}}
                            <input x-show="item.sale_unit === 'KG'" type="text" inputmode="numeric"
                                   x-init="$el.value = fmtGrams(item.quantity)"
                                   @focus="$el.value = String(window.KgGrams.toGrams(item.quantity) || '')"
                                   @input="onRowGrams(item, $event)"
                                   @blur="$el.value = fmtGrams(item.quantity)"
                                   placeholder="0"
                                   class="border rounded px-2 py-2 text-sm text-right w-full focus:outline-none focus:ring-1 focus:ring-blue-400">
                            {{-- UNIT: integer --}}
                            <input x-show="item.sale_unit !== 'KG'" type="number" inputmode="numeric"
                                   min="1" step="1" x-model.number="item.quantity"
                                   @input="computeLineTotal(item)"
                                   class="border rounded px-2 py-2 text-sm text-right w-full focus:outline-none focus:ring-1 focus:ring-blue-400">
                            <span class="text-xs text-gray-400" x-text="item.sale_unit === 'KG' ? 'g' : 'und'"></span>
                        </div>
                        {{-- Unit price --}}
                        <div class="flex items-center gap-0.5 flex-1 min-w-0">
                            <span class="text-sm text-gray-400">$</span>
                            <input type="number" x-model.number="item.unit_price"
                                   @input="computeLineTotal(item)"
                                   inputmode="numeric" min="0" step="100" placeholder="0"
                                   class="border rounded px-2 py-2 text-sm text-right w-full focus:outline-none focus:ring-1 focus:ring-blue-400">
                        </div>
                        {{-- Line total --}}
                        <span class="text-sm font-semibold text-gray-700 font-mono w-24 text-right shrink-0"
                              x-text="'$'+formatNum(item.line_total)"></span>
                    </div>
                </div>
            </template>
            <div x-show="items.length === 0" class="py-3 text-center text-red-500 text-sm">
                La factura debe tener al menos un producto.
            </div>
        </div>

        {{-- Add product / temporal --}}
        <div class="flex gap-2 mt-3" x-show="!showPicker">
            <button type="button" @click="openPicker()"
                    class="pos-btn pos-btn-secondary text-sm">+ Producto</button>
            <button type="button" @click="showPicker = true; openTempForm()"
                    class="px-4 py-2 rounded-lg text-sm font-semibold border border-dashed border-amber-400 bg-amber-50 text-amber-700 hover:bg-amber-100">
                + Temporal
            </button>
        </div>

        {{-- Picker panel --}}
        <div x-show="showPicker" x-cloak x-ref="pickerBox"
             class="mt-3 border-2 border-blue-300 rounded-lg p-3 bg-blue-50/50">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-semibold text-gray-700"
                      x-text="swapIndex !== null ? 'Reemplazar producto' : 'Agregar producto'"></span>
                <button type="button" @click="closePicker(); cancelTempForm()" class="pos-btn-icon">&times;</button>
            </div>

            {{-- Temporary product form --}}
            <div x-show="showTempForm" class="border border-amber-300 rounded-lg p-3 bg-amber-50 space-y-2 mb-3">
                <input type="text" x-model="tempForm.name" x-ref="tempNameInput" maxlength="150"
                       placeholder="Descripción del producto *" autocomplete="off"
                       class="border rounded px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-amber-400">
                <div class="flex gap-2">
                    <button type="button" @click="setTempUnit('UNIT')"
                            class="flex-1 px-3 py-2 rounded-lg text-sm font-semibold border"
                            :class="tempForm.sale_unit === 'UNIT' ? 'bg-orange-500 text-white border-orange-500' : 'bg-white text-gray-600 border-gray-300'">Por unidad</button>
                    <button type="button" @click="setTempUnit('KG')"
                            class="flex-1 px-3 py-2 rounded-lg text-sm font-semibold border"
                            :class="tempForm.sale_unit === 'KG' ? 'bg-purple-500 text-white border-purple-500' : 'bg-white text-gray-600 border-gray-300'">Por kilo</button>
                </div>
                <div class="flex gap-2">
                    <div class="flex-1">
                        <label class="block text-xs text-amber-800 mb-1"
                               x-text="tempForm.sale_unit === 'KG' ? 'Precio por kg *' : 'Precio por unidad *'"></label>
                        <input type="number" x-model.number="tempForm.unit_price" min="0" step="100" placeholder="0"
                               class="border rounded px-3 py-2 text-sm text-right w-full focus:outline-none focus:ring-2 focus:ring-amber-400">
                    </div>
                    <div class="flex-1">
                        <label class="block text-xs text-amber-800 mb-1"
                               x-text="tempForm.sale_unit === 'KG' ? 'Cantidad (g) *' : 'Cantidad (und) *'"></label>
                        <input x-show="tempForm.sale_unit === 'KG'" type="text" inputmode="numeric"
                               x-model="tempForm.qty" @input="onTempGramsInput($event)" placeholder="0"
                               class="border rounded px-3 py-2 text-sm text-center w-full focus:outline-none focus:ring-2 focus:ring-amber-400">
                        <input x-show="tempForm.sale_unit !== 'KG'" type="number" inputmode="numeric" min="1" step="1"
                               x-model="tempForm.qty" placeholder="1"
                               class="border rounded px-3 py-2 text-sm text-center w-full focus:outline-none focus:ring-2 focus:ring-amber-400">
                    </div>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs text-amber-900" x-show="tempLineTotal > 0"
                          x-text="'Total: $' + formatNum(tempLineTotal)"></span>
                    <button type="button" @click="addTempItem()" :disabled="!tempValid"
                            :class="tempValid ? 'bg-amber-600 hover:bg-amber-700 text-white' : 'bg-gray-300 text-gray-400 cursor-not-allowed'"
                            class="ml-auto px-4 py-2 rounded-lg font-semibold text-sm">
                        <span x-text="swapIndex !== null ? 'Reemplazar' : 'Agregar'"></span>
                    </button>
                </div>
                <p x-show="tempError" x-cloak class="text-red-600 text-xs" x-text="tempError"></p>
            </div>

            {{-- Catalog picker --}}
            <div x-show="!showTempForm">
                {{-- Global search --}}
                <div class="relative mb-2">
                    <input type="text" x-model="globalSearch" placeholder="Buscar en todo el catálogo…"
                           autocomplete="off"
                           class="border rounded px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-blue-400">
                    <div x-show="globalResults.length > 0"
                         class="absolute z-20 w-full bg-white border rounded shadow-lg mt-1 max-h-52 overflow-auto">
                        <template x-for="p in globalResults" :key="'egs-'+p.id">
                            <button type="button" @click="selectPending(p); globalSearch=''"
                                    class="w-full text-left px-3 py-2 hover:bg-blue-50 text-sm flex items-center justify-between">
                                <span x-text="p.name"></span>
                                <span class="text-xs text-gray-400"
                                      x-text="'$'+formatNum(p.base_price)+' / '+(p.sale_unit==='KG'?'kg':'und')"></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Category chips --}}
                <div class="flex gap-2 flex-wrap mb-2">
                    <template x-for="cat in categories" :key="cat.id">
                        <button type="button"
                                @click="activeCategory && activeCategory.id===cat.id ? clearCategory() : selectCategory(cat)"
                                class="px-3 py-1.5 rounded-full text-xs font-semibold border"
                                :class="activeCategory && activeCategory.id===cat.id ? catColor(cat.colorIndex,'active') : catColor(cat.colorIndex,'chip')"
                                x-text="cat.name"></button>
                    </template>
                </div>

                {{-- Category filter --}}
                <div x-show="activeCategory && !pendingProduct" class="mb-2">
                    <input type="text" x-model="categoryFilter" placeholder="Filtrar en esta categoría…"
                           autocomplete="off"
                           class="border rounded px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-blue-300">
                </div>

                {{-- Product grid --}}
                <div x-show="activeCategory && !pendingProduct" class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    <template x-for="p in filteredProducts" :key="'epc-'+p.id">
                        <button type="button" @click="selectPending(p)"
                                class="text-left px-3 py-2 rounded-lg border text-sm hover:shadow-sm"
                                :class="catColor(activeCategory.colorIndex,'chip')">
                            <div class="font-medium leading-snug truncate" x-text="p.name"></div>
                            <div class="text-xs mt-0.5 opacity-70"
                                 x-text="'$'+formatNum(p.base_price)+' / '+(p.sale_unit==='KG'?'kg':'und')"></div>
                        </button>
                    </template>
                    <div x-show="filteredProducts.length===0 && categoryFilter"
                         class="col-span-3 text-center text-gray-400 text-sm py-2">Sin resultados.</div>
                </div>

                {{-- Quantity panel --}}
                <div x-show="pendingProduct" class="border-2 border-blue-400 rounded-lg p-3 bg-white mt-2">
                    <div class="font-semibold text-gray-800 mb-2 text-sm" x-text="pendingProduct?.name"></div>
                    <div class="flex items-center gap-2">
                        <div class="flex-1">
                            <div x-show="pendingProduct?.sale_unit==='KG'" class="flex items-center gap-2">
                                <input type="text" inputmode="numeric" x-ref="qtyInput" x-model="pendingInput"
                                       @input="onPendingGramsInput($event)"
                                       @keydown.enter.prevent="confirmPending()"
                                       placeholder="0"
                                       class="border-2 border-blue-400 rounded px-3 py-2 text-lg text-center w-28 focus:outline-none focus:border-blue-600">
                                <span class="text-gray-500 text-sm">g</span>
                                <span class="text-xs text-gray-400" x-show="pendingKg > 0"
                                      x-text="'= '+pendingKg.toFixed(3)+' kg'"></span>
                            </div>
                            <div x-show="pendingProduct?.sale_unit!=='KG'" class="flex items-center gap-2">
                                <input type="number" inputmode="numeric" min="1" step="1" x-ref="qtyInput" x-model="pendingInput"
                                       @keydown.enter.prevent="confirmPending()"
                                       placeholder="1"
                                       class="border-2 border-blue-400 rounded px-3 py-2 text-lg text-center w-28 focus:outline-none focus:border-blue-600">
                                <span class="text-gray-500 text-sm">und</span>
                            </div>
                        </div>
                        <button type="button" @click="confirmPending()" :disabled="!pendingValid"
                                :class="pendingValid ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-gray-300 text-gray-400 cursor-not-allowed'"
                                class="px-4 py-2 rounded-lg font-semibold text-sm">OK</button>
                        <button type="button" @click="cancelPending()"
                                class="px-3 py-2 rounded-lg text-sm text-gray-500 hover:text-gray-700">Cancelar</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Domicilio + Notas --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Domicilio</label>
                <div class="flex items-center gap-2">
                    <span class="text-gray-500 text-sm">$</span>
                    <input type="number" name="delivery_fee" x-model.number="deliveryFee"
                           inputmode="numeric" min="0" step="500" placeholder="0"
                           class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Notas</label>
                <textarea name="notes" x-model="notes" rows="1" placeholder="Notas (opcional)"
                          class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"></textarea>
            </div>
        </div>

        {{-- Totals preview --}}
        <div class="mt-4 pt-3 border-t space-y-1 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-600">Subtotal</span>
                <span class="font-mono">$<span x-text="formatNum(subtotal)"></span></span>
            </div>
            <div class="flex justify-between" x-show="deliveryFee > 0">
                <span class="text-gray-600">Domicilio</span>
                <span class="font-mono">$<span x-text="formatNum(deliveryFee)"></span></span>
            </div>
            <div class="flex justify-between text-xs text-gray-500" x-show="roundingAdjustment > 0">
                <span>Ajuste redondeo</span>
                <span class="font-mono">$<span x-text="formatNum(roundingAdjustment)"></span></span>
            </div>
            <div class="flex justify-between text-lg font-bold border-t pt-2">
                <span>TOTAL</span>
                <span class="font-mono text-green-700">$<span x-text="formatNum(total)"></span></span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Pagado</span>
                <span class="font-mono">$<span x-text="formatNum(paidAmount)"></span></span>
            </div>
            <div class="flex justify-between font-semibold"
                 :class="displayBalance > 0 ? 'text-yellow-700' : 'text-green-700'">
                <span>Saldo</span>
                <span class="font-mono">$<span x-text="formatNum(displayBalance)"></span></span>
            </div>
            <div x-show="refundAmount > 0" x-cloak
                 class="mt-1 bg-green-50 border border-green-200 rounded p-2 text-xs text-green-700">
                El nuevo total es menor a lo pagado. Se devolverán
                <strong>$<span x-text="formatNum(refundAmount)"></span></strong>
                al saldo a favor del cliente.
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex gap-2 mt-4">
            <button type="submit" :disabled="!canSave"
                    :class="canSave ? 'pos-btn-success' : 'pos-btn-secondary'"
                    class="flex-1 justify-center">
                <span x-show="!submitting">Guardar cambios</span>
                <span x-show="submitting">Guardando…</span>
            </button>
            <button type="button" @click="cancelEdit()" class="pos-btn-secondary">Cancelar</button>
        </div>
    </form>
</div>
