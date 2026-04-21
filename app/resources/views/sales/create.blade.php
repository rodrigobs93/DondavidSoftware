@extends('layouts.app')
@section('title', 'Nueva Venta')

@section('content')
<div x-data="saleForm()" x-init="init()" x-cloak class="sales-screen">

    {{-- =====================================================================
         MOBILE TAB BAR — hidden on md+
         ===================================================================== --}}
    <div class="flex md:hidden bg-white shadow rounded-lg mb-4 overflow-hidden text-sm font-semibold">
        <button type="button" @click="activeTab='cliente'"
                :class="activeTab==='cliente' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-50'"
                class="flex-1 py-3.5 text-center transition-colors">
            Cliente
        </button>
        <button type="button" @click="activeTab='productos'"
                :class="activeTab==='productos' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-50'"
                class="flex-1 py-3.5 text-center transition-colors">
            Productos
        </button>
        <button type="button" @click="activeTab='pagos'"
                :class="activeTab==='pagos' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-50'"
                class="flex-1 py-3.5 text-center transition-colors">
            Pagos
            <span x-show="items.length > 0"
                  class="ml-1 text-xs rounded-full px-1.5 py-0.5 bg-white text-blue-600"
                  x-text="items.length"></span>
        </button>
    </div>

    <h1 class="text-xl font-bold text-gray-800 mb-4 hidden md:block">Nueva Venta</h1>

    <form method="POST" action="{{ route('sales.store') }}" @submit.prevent="submitForm($event)">
        @csrf
        <input type="hidden" name="submission_key" :value="submissionKey">

        <div class="md:grid md:grid-cols-3 md:gap-6 pb-28 md:pb-0">

            {{-- ===========================================================
                 LEFT COLUMN: md:col-span-2
                 =========================================================== --}}
            <div class="md:col-span-2 space-y-4">

                {{-- --- Customer card ---------------------------------------- --}}
                <div class="bg-white rounded-lg shadow p-4"
                     :class="activeTab==='cliente' ? 'block' : 'hidden md:block'">
                    <h2 class="font-semibold text-gray-700 mb-3">Cliente</h2>

                    <div class="flex gap-2 items-start">
                        <div class="relative flex-1">
                            <input type="text" x-model="customerSearch"
                                   x-ref="customerInput"
                                   inputmode="text" data-keyboard="text"
                                   @input.debounce.300ms="searchCustomers()"
                                   @focus="showCustomerDropdown=true"
                                   @keydown.escape="showCustomerDropdown=false"
                                   placeholder="Nombre o razón social…"
                                   autocomplete="off"
                                   class="border rounded px-3 py-3 text-base w-full focus:outline-none focus:ring-2 focus:ring-blue-400">
                            <input type="hidden" name="customer_id" :value="selectedCustomer ? selectedCustomer.id : __genericId">

                            <div x-show="showCustomerDropdown && customerResults.length > 0"
                                 class="absolute z-20 w-full bg-white border rounded shadow-lg mt-1 max-h-48 overflow-auto">
                                <template x-for="c in customerResults" :key="c.id">
                                    <button type="button" @click="selectCustomer(c)"
                                            class="w-full text-left px-3 py-3 hover:bg-blue-50 text-base"
                                            :class="c.is_generic ? 'text-gray-500 italic' : ''">
                                        <span x-text="c.name"></span>
                                        <span x-show="c.business_name"
                                              class="text-xs text-gray-500 ml-1 italic"
                                              x-text="'· ' + c.business_name"></span>
                                        <span x-show="c.doc_number"
                                              class="text-xs text-gray-400 ml-1"
                                              x-text="'(' + (c.doc_type||'') + ' ' + (c.doc_number||'') + ')'"></span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        {{-- Quick GENÉRICO chip --}}
                        @if($generic)
                        <button type="button"
                                @click="selectCustomer({ id: {{ $generic->id }}, name: '{{ $generic->name }}', is_generic: true, doc_type: null, doc_number: null, requires_fe: false })"
                                class="px-4 py-3 rounded-lg text-base font-semibold border border-gray-300 text-gray-500 hover:border-blue-400 hover:text-blue-600 whitespace-nowrap shrink-0">
                            GENÉRICO
                        </button>
                        @endif
                    </div>

                    <div x-show="selectedCustomer" class="mt-2 text-sm text-gray-600">
                        Cliente: <strong x-text="selectedCustomer?.name"></strong>
                        <span x-show="selectedCustomer?.is_generic" class="text-xs text-gray-400">(GENÉRICO)</span>
                    </div>

                    {{-- FE Toggle --}}
                    <div class="mt-3 pt-3 border-t"
                         :class="requiresFe ? 'fe-active-box' : ''">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" x-model="requiresFe"
                                   @change="onFeToggle()" class="rounded">
                            <input type="hidden" name="requires_fe" :value="requiresFe ? 1 : 0">
                            <span class="text-sm font-medium text-gray-700">Requiere Factura Electrónica (FE)</span>
                            <span x-show="requiresFe" class="badge-fe text-xs">FE</span>
                        </label>

                        {{-- Error when FE + valid customer missing doc --}}
                        <div x-show="feError && !isGenericSelected"
                             class="text-red-500 text-xs mt-1" x-text="feError"></div>

                        {{-- OK state --}}
                        <div x-show="requiresFe && selectedCustomer && !selectedCustomer.is_generic && selectedCustomer.doc_number"
                             class="text-xs text-green-600 mt-1">
                            ✓ Cliente con documento válido para FE
                        </div>

                        {{-- Inline FE customer form when GENÉRICO is selected --}}
                        <div x-show="requiresFe && isGenericSelected" x-cloak
                             class="mt-2 border border-amber-300 bg-amber-50 rounded-lg p-3 space-y-2">
                            <p class="text-sm font-semibold text-amber-800">Datos del cliente para FE</p>
                            <input x-model="feForm.name" placeholder="Nombre completo *"
                                   data-keyboard="text"
                                   class="border rounded px-3 py-2.5 text-base w-full">
                            <input x-model="feForm.email" placeholder="Email (opcional)"
                                   data-keyboard="text"
                                   class="border rounded px-3 py-2.5 text-base w-full">
                            <div class="flex gap-2">
                                <select x-model="feForm.doc_type" class="border rounded px-3 py-2.5 text-base w-1/3">
                                    <option value="">Tipo doc</option>
                                    <option value="CC">CC</option>
                                    <option value="NIT">NIT</option>
                                </select>
                                <input x-model="feForm.doc_number" placeholder="Número de doc *"
                                       data-keyboard="numeric"
                                       class="border rounded px-3 py-2.5 text-base flex-1">
                            </div>
                            <input x-show="feForm.doc_type === 'NIT'" x-model="feForm.business_name"
                                   placeholder="Razón social *"
                                   data-keyboard="text"
                                   class="border rounded px-3 py-2.5 text-base w-full">
                            @if($isAdmin)
                            <button type="button" @click="createFeCustomer()" :disabled="feCreating"
                                    class="pos-btn pos-btn-primary w-full text-sm justify-center disabled:opacity-50">
                                <span x-show="!feCreating">Crear cliente con estos datos</span>
                                <span x-show="feCreating">Creando…</span>
                            </button>
                            @else
                            <p class="text-xs text-amber-700">Solo un administrador puede crear clientes. Selecciona un cliente existente o pide ayuda.</p>
                            @endif
                            <p x-show="feCreateError" x-text="feCreateError"
                               class="text-xs text-red-600"></p>
                        </div>
                    </div>
                </div>

                {{-- --- Products card ---------------------------------------- --}}
                <div class="bg-white rounded-lg shadow p-4"
                     :class="activeTab==='productos' ? 'block' : 'hidden md:block'">
                    <h2 class="font-semibold text-gray-700 mb-3">Productos</h2>

                    {{-- Global search --}}
                    <div class="relative mb-3">
                        <input type="text" x-model="globalSearch"
                               @input.debounce.200ms=""
                               placeholder="Buscar en todo el catálogo…"
                               autocomplete="off"
                               class="border rounded px-3 py-3 text-base w-full focus:outline-none focus:ring-2 focus:ring-blue-400">
                        <div x-show="globalResults.length > 0"
                             class="absolute z-20 w-full bg-white border rounded shadow-lg mt-1 max-h-52 overflow-auto">
                            <template x-for="p in globalResults" :key="'gs-'+p.id">
                                <button type="button" @click="selectPending(p); globalSearch=''"
                                        class="w-full text-left px-3 py-3 hover:bg-blue-50 text-base flex items-center justify-between">
                                    <span x-text="p.name"></span>
                                    <span class="text-xs text-gray-400"
                                          x-text="'$'+formatNum(p.base_price)+' / '+(p.sale_unit==='KG'?'kg':'und')"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    {{-- Category chips --}}
                    <div class="flex gap-2 flex-wrap mb-3">
                        <template x-for="cat in categories" :key="cat.id">
                            <button type="button"
                                    @click="activeCategory && activeCategory.id===cat.id ? clearCategory() : selectCategory(cat)"
                                    class="px-4 py-2.5 rounded-full text-sm font-semibold border transition-colors"
                                    :class="activeCategory && activeCategory.id===cat.id
                                        ? catColor(cat.colorIndex,'active')
                                        : catColor(cat.colorIndex,'chip')"
                                    x-text="cat.name">
                            </button>
                        </template>
                    </div>

                    {{-- Category filter (shown when a category is active + no pendingProduct) --}}
                    <div x-show="activeCategory && !pendingProduct" class="mb-2">
                        <input type="text" x-model="categoryFilter"
                               x-ref="catFilterInput"
                               placeholder="Filtrar en esta categoría…"
                               autocomplete="off"
                               class="border rounded px-3 py-2.5 text-base w-full focus:outline-none focus:ring-2 focus:ring-blue-300">
                    </div>

                    {{-- Product chips grid --}}
                    <div x-show="activeCategory && !pendingProduct"
                         class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        <template x-for="p in filteredProducts" :key="'pc-'+p.id">
                            <button type="button" @click="selectPending(p)"
                                    class="text-left px-3 py-3 rounded-lg border text-sm transition-colors hover:shadow-sm"
                                    :class="catColor(activeCategory.colorIndex,'chip')">
                                <div class="font-medium leading-snug truncate" x-text="p.name"></div>
                                <div class="text-xs mt-0.5 opacity-70"
                                     x-text="'$'+formatNum(p.base_price)+' / '+(p.sale_unit==='KG'?'kg':'und')"></div>
                            </button>
                        </template>
                        <div x-show="filteredProducts.length===0 && categoryFilter"
                             class="col-span-3 text-center text-gray-400 text-sm py-3">
                            Sin resultados en esta categoría.
                        </div>
                    </div>

                    {{-- Quantity panel --}}
                    <div x-show="pendingProduct"
                         class="border-2 border-blue-400 rounded-lg p-4 bg-blue-50">
                        <div class="font-semibold text-gray-800 mb-3" x-text="pendingProduct?.name"></div>
                        <div class="flex items-center gap-3">
                            <div class="flex-1">
                                {{-- KG: user types grams --}}
                                <div x-show="pendingProduct?.sale_unit==='KG'" class="flex items-center gap-2">
                                    <input type="text" inputmode="numeric" data-keyboard="numeric"
                                           x-ref="qtyInput"
                                           x-model="pendingInput"
                                           @input="onPendingGramsInput($event)"
                                           @keydown.enter.prevent="confirmPending()"
                                           @keydown.escape.prevent="cancelPending()"
                                           placeholder="0"
                                           class="border-2 border-blue-400 rounded px-3 py-3 text-xl text-center w-32 focus:outline-none focus:border-blue-600">
                                    <span class="text-gray-500 font-medium">g</span>
                                    <span class="text-xs text-gray-400"
                                          x-show="pendingKg > 0"
                                          x-text="'= '+pendingKg.toFixed(3)+' kg'"></span>
                                </div>
                                {{-- UNIT --}}
                                <div x-show="pendingProduct?.sale_unit!=='KG'" class="flex items-center gap-2">
                                    <input type="number" inputmode="numeric" data-keyboard="numeric" min="1" step="1"
                                           x-ref="qtyInput"
                                           x-model="pendingInput"
                                           @keydown.enter.prevent="confirmPending()"
                                           @keydown.escape.prevent="cancelPending()"
                                           placeholder="1"
                                           class="border-2 border-blue-400 rounded px-3 py-3 text-xl text-center w-32 focus:outline-none focus:border-blue-600">
                                    <span class="text-gray-500 font-medium">und</span>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" @click="confirmPending()"
                                        :disabled="!pendingValid"
                                        :class="pendingValid ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-gray-300 text-gray-400 cursor-not-allowed'"
                                        class="px-6 py-3 rounded-lg font-semibold text-base min-w-20 transition-colors">
                                    OK
                                </button>
                                <button type="button" @click="cancelPending()"
                                        class="px-4 py-3 rounded-lg text-base text-gray-500 hover:text-gray-700">
                                    Cancelar
                                </button>
                            </div>
                        </div>
                        <div x-show="pendingProduct?.sale_unit==='KG' && pendingInput && !pendingValid"
                             class="text-red-500 text-xs mt-2">
                            Ingresa al menos 1 g.
                        </div>
                    </div>

                    {{-- Placeholder when no category selected --}}
                    <div x-show="!activeCategory && !pendingProduct && globalSearch.length === 0"
                         class="text-gray-400 text-sm text-center py-4">
                        Selecciona una categoría o busca en el catálogo.
                    </div>
                </div>

                {{-- --- Extras card ------------------------------------------ --}}
                <div class="bg-white rounded-lg shadow p-4"
                     :class="activeTab==='cliente' ? 'block' : 'hidden md:block'">
                    <h2 class="font-semibold text-gray-700 mb-3">Extras</h2>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Domicilio (opcional)</label>
                            <div class="flex items-center gap-2">
                                <span class="text-gray-500 text-sm">$</span>
                                <input type="number" name="delivery_fee" x-model.number="deliveryFee"
                                       inputmode="numeric" data-keyboard="numeric"
                                       min="0" step="500" placeholder="0"
                                       class="border rounded px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-blue-400">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notas (opcional)</label>
                            <textarea name="notes" rows="2" placeholder="Instrucciones especiales, referencias…"
                                      data-keyboard="text"
                                      class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"></textarea>
                        </div>
                    </div>
                </div>

                {{-- --- Admin date override ----------------------------------- --}}
                @if(auth()->user()->isAdmin())
                <div class="bg-white rounded-lg shadow p-4"
                     :class="activeTab==='cliente' ? 'block' : 'hidden md:block'">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Fecha de factura</span>
                        <button type="button" @click="showDatePicker = !showDatePicker"
                                class="text-xs text-gray-400 underline hover:text-gray-600">
                            ¿Cambiar fecha?
                        </button>
                    </div>
                    <div x-show="showDatePicker" class="mt-2 flex items-center gap-2 justify-end">
                        <input type="date" x-model="invoiceDate" :max="todayStr"
                               class="border rounded px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                        <button type="button" @click="showDatePicker=false; invoiceDate=''"
                                class="text-sm text-gray-400 hover:text-gray-600">✕</button>
                    </div>
                    <input type="hidden" name="invoice_date" :value="invoiceDate || ''">
                </div>
                @endif

            </div>{{-- end left column --}}

            {{-- ===========================================================
                 RIGHT COLUMN: md:col-span-1 (sticky cart + payments)
                 =========================================================== --}}
            <div class="md:col-span-1 md:sticky md:top-4 md:self-start space-y-3"
                 :class="activeTab==='pagos' ? 'block' : 'hidden md:block'">

                {{-- Cart items --}}
                <div class="bg-white rounded-lg shadow p-4">
                    <h2 class="font-semibold text-gray-700 mb-3">
                        Carrito <span class="text-gray-400 text-sm" x-text="'(' + items.length + ')'"></span>
                    </h2>

                    <div x-show="items.length === 0" class="text-gray-400 text-sm text-center py-3">
                        Agrega productos desde el catálogo.
                    </div>

                    <template x-for="(item, idx) in items" :key="item._key">
                        <div class="py-2 border-b last:border-0">
                            <input type="hidden" :name="'items['+idx+'][product_id]'"   :value="item.product_id">
                            <input type="hidden" :name="'items['+idx+'][product_name]'" :value="item.product_name">
                            <input type="hidden" :name="'items['+idx+'][sale_unit]'"    :value="item.sale_unit">
                            <input type="hidden" :name="'items['+idx+'][unit_price]'"   :value="item.unit_price">
                            <input type="hidden" :name="'items['+idx+'][quantity]'"     :value="item.quantity">

                            {{-- Row 1: name + remove --}}
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-sm font-medium truncate" x-text="item.product_name"></span>
                                <button type="button" @click="removeItem(idx)"
                                        class="text-red-400 hover:text-red-600 text-xl leading-none shrink-0 p-2 min-w-9 min-h-9 flex items-center justify-center">&times;</button>
                            </div>

                            {{-- Row 2: qty display + price input + total --}}
                            <div class="flex items-center gap-2 mt-1">
                                {{-- Qty display --}}
                                <span class="text-xs text-gray-500 w-16 text-center"
                                      x-text="item.sale_unit==='KG' ? formatGrams(item.quantity)+' g' : item.quantity+' und'"></span>

                                {{-- Unit price (editable) --}}
                                <div class="flex items-center gap-0.5 flex-1">
                                    <span class="text-xs text-gray-400">$</span>
                                    <input type="number" x-model.number="item.unit_price"
                                           @input="computeLineTotal(item)"
                                           inputmode="numeric" data-keyboard="numeric"
                                           min="0" step="100" placeholder="0"
                                           class="border rounded px-2 py-2 text-sm text-right w-full focus:outline-none focus:ring-1 focus:ring-blue-400"
                                           :class="item.unit_price !== item.base_price ? 'border-purple-400 text-purple-700' : ''">
                                    <span x-show="item.unit_price !== item.base_price"
                                          class="text-purple-500 text-xs font-bold" title="Precio modificado">*</span>
                                </div>

                                {{-- Line total --}}
                                <span class="text-sm font-semibold text-gray-700 font-mono w-20 text-right"
                                      x-text="'$'+formatNum(item.line_total)"></span>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Totals summary --}}
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal</span>
                            <span class="font-mono">$<span x-text="formatNum(subtotal)"></span></span>
                        </div>
                        <div class="flex justify-between" x-show="deliveryFee > 0">
                            <span class="text-gray-600">Domicilio</span>
                            <span class="font-mono">$<span x-text="formatNum(deliveryFee)"></span></span>
                        </div>
                        <div x-show="roundingAdjustment > 0" class="flex justify-between text-xs text-gray-500">
                            <span>Ajuste redondeo</span>
                            <span class="font-mono">$<span x-text="formatNum(roundingAdjustment)"></span></span>
                        </div>
                        <div class="flex justify-between text-xl font-bold border-t pt-2 mt-2">
                            <span>TOTAL</span>
                            <span class="font-mono text-green-700 text-2xl">$<span x-text="formatNum(total)"></span></span>
                        </div>
                    </div>
                </div>

                {{-- Payment method chips --}}
                <div class="bg-white rounded-lg shadow p-4">
                    <h2 class="font-semibold text-gray-700 mb-3">Pagos</h2>

                    {{-- Quick-add chips --}}
                    <div class="flex gap-2 flex-wrap mb-3">
                        <button type="button" @click="addPaymentMethod('NEQUI')"
                                class="px-4 py-2.5 rounded-full text-sm font-semibold bg-pink-100 text-pink-700 border border-pink-300 hover:bg-pink-500 hover:text-white hover:border-pink-500 transition-colors">
                            Nequi
                        </button>
                        <button type="button" @click="addPaymentMethod('DAVIPLATA')"
                                class="px-4 py-2.5 rounded-full text-sm font-semibold bg-red-100 text-red-700 border border-red-300 hover:bg-red-500 hover:text-white hover:border-red-500 transition-colors">
                            Daviplata
                        </button>
                        <button type="button" @click="addPaymentMethod('BREB')"
                                class="px-4 py-2.5 rounded-full text-sm font-semibold bg-purple-100 text-purple-700 border border-purple-300 hover:bg-purple-500 hover:text-white hover:border-purple-500 transition-colors">
                            Bre-B
                        </button>
                        <button type="button" @click="addPaymentMethod('CARD')"
                                class="px-4 py-2.5 rounded-full text-sm font-semibold bg-blue-100 text-blue-700 border border-blue-300 hover:bg-blue-500 hover:text-white hover:border-blue-500 transition-colors">
                            Tarjeta
                        </button>
                        <button type="button" @click="addPaymentMethod('CASH')"
                                class="px-4 py-2.5 rounded-full text-sm font-semibold bg-green-100 text-green-700 border border-green-300 hover:bg-green-500 hover:text-white hover:border-green-500 transition-colors">
                            Efectivo
                        </button>
                    </div>

                    {{-- Payment rows --}}
                    <template x-for="(pay, pidx) in payments" :key="pay._key">
                        <div class="flex items-center gap-2 mb-2">
                            <input type="hidden" :name="'payments['+pidx+'][method]'" :value="pay.method">
                            <input type="hidden" :name="'payments['+pidx+'][amount]'" :value="pay.amount">

                            <span class="text-xs font-semibold w-20 shrink-0 text-center py-1 rounded-full"
                                  :class="{
                                      'bg-green-100 text-green-700':   pay.method==='CASH',
                                      'bg-blue-100 text-blue-700':     pay.method==='CARD',
                                      'bg-pink-100 text-pink-700':     pay.method==='NEQUI',
                                      'bg-red-100 text-red-700':       pay.method==='DAVIPLATA',
                                      'bg-purple-100 text-purple-700': pay.method==='BREB',
                                  }"
                                  x-text="methodLabel(pay.method)"></span>

                            <input type="number" x-model.number="pay.amount" min="0" step="1"
                                   inputmode="numeric" data-keyboard="numeric"
                                   data-payment-amount
                                   placeholder="0"
                                   class="border rounded px-2 py-3 text-base flex-1 text-right focus:outline-none focus:ring-2 focus:ring-blue-400">

                            <button type="button" @click="removePayment(pidx)"
                                    x-show="payments.length > 1"
                                    class="text-red-400 hover:text-red-600 text-xl leading-none shrink-0 p-2 min-w-9 min-h-9 flex items-center justify-center">&times;</button>
                        </div>
                    </template>

                    {{-- Balance summary --}}
                    <div class="border-t pt-2 mt-2 space-y-1">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Total pagado</span>
                            <span class="font-mono" :class="overpay ? 'text-red-600 font-bold' : ''">
                                $<span x-text="formatNum(paidAmount)"></span>
                            </span>
                        </div>
                        <div class="flex justify-between text-base font-bold"
                             :class="balance > 0 ? 'text-yellow-700' : 'text-green-700'">
                            <span>Saldo</span>
                            <span class="font-mono">$<span x-text="formatNum(balance)"></span></span>
                        </div>
                        <div x-show="overpay" class="text-red-500 text-xs font-semibold">
                            El pago supera el total. Ajusta los montos.
                        </div>
                    </div>
                </div>

                {{-- Finalize button --}}
                <button type="submit"
                        :disabled="!canSubmit || submitting"
                        :class="submitting
                            ? 'bg-gray-400 text-white cursor-not-allowed'
                            : (canSubmit
                                ? (balance > 0 ? 'bg-yellow-600 hover:bg-yellow-700 text-white' : 'bg-green-600 hover:bg-green-700 text-white')
                                : 'bg-gray-300 text-gray-500 cursor-not-allowed')"
                        class="w-full py-4 rounded-lg font-bold text-lg transition-colors shadow">
                    <span x-show="submitting">Procesando…</span>
                    <span x-show="!submitting && !canSubmit && items.length === 0">Agrega al menos un producto</span>
                    <span x-show="!submitting && !canSubmit && items.length > 0 && overpay">Pago inválido — ajusta los montos</span>
                    <span x-show="!submitting && !canSubmit && items.length > 0 && !overpay && !!feError">Error en FE</span>
                    <span x-show="!submitting && canSubmit && balance === 0">
                        Finalizar PAGADA — $<span x-text="formatNum(total)"></span>
                    </span>
                    <span x-show="!submitting && canSubmit && paidAmount > 0 && balance > 0">
                        Finalizar PARCIAL — abona $<span x-text="formatNum(paidAmount)"></span>
                    </span>
                    <span x-show="!submitting && canSubmit && paidAmount === 0">
                        Finalizar PENDIENTE — $<span x-text="formatNum(total)"></span> por cobrar
                    </span>
                </button>

            </div>{{-- end right column --}}

        </div>{{-- end grid --}}

    </form>

    {{-- =====================================================================
         MOBILE sticky bottom bar
         ===================================================================== --}}
    <div class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t shadow-lg px-4 py-3 flex items-center gap-3 z-30">
        <div class="flex-1 text-base">
            <span class="text-gray-500">Total:</span>
            <span class="font-bold text-green-700 font-mono ml-1">$<span x-text="formatNum(total)"></span></span>
        </div>
        <div class="text-base" x-show="paidAmount > 0 || balance > 0">
            <span class="text-gray-500">Saldo:</span>
            <span class="font-semibold font-mono ml-1"
                  :class="balance > 0 ? 'text-yellow-700' : 'text-green-700'"
                  x-text="'$'+formatNum(balance)"></span>
        </div>
        <button type="button" @click="activeTab='pagos'"
                x-show="activeTab !== 'pagos'"
                class="px-4 py-3 bg-blue-600 text-white rounded-lg text-sm font-semibold">
            Ver pagos →
        </button>
    </div>

</div>{{-- end x-data --}}

<script>
const __initialCategories = {!! json_encode($cats, JSON_HEX_TAG) !!};
const __genericId          = {{ $generic?->id ?? 'null' }};
const __genericName        = @js($generic?->name ?? '');
const __todayStr           = new Date().toISOString().slice(0, 10);
// Explicit class maps — full strings required so Tailwind CDN scanner detects them
const CAT_CHIP = [
    'bg-red-50    text-red-700    border-red-300    hover:bg-red-100',
    'bg-blue-50   text-blue-700   border-blue-300   hover:bg-blue-100',
    'bg-green-50  text-green-700  border-green-300  hover:bg-green-100',
    'bg-purple-50 text-purple-700 border-purple-300 hover:bg-purple-100',
    'bg-orange-50 text-orange-700 border-orange-300 hover:bg-orange-100',
    'bg-teal-50   text-teal-700   border-teal-300   hover:bg-teal-100',
];
const CAT_ACTIVE = [
    'bg-red-500    text-white border-red-500',
    'bg-blue-500   text-white border-blue-500',
    'bg-green-500  text-white border-green-500',
    'bg-purple-500 text-white border-purple-500',
    'bg-orange-500 text-white border-orange-500',
    'bg-teal-500   text-white border-teal-500',
];

function saleForm() {
    return {
        // ── Existing state ─────────────────────────────────────────────────
        items:                [],
        payments:             [],
        deliveryFee:          0,
        requiresFe:           false,
        feError:              '',
        feForm:               { name: '', email: '', doc_type: '', doc_number: '', business_name: '' },
        feCreating:           false,
        feCreateError:        '',
        customerSearch:       '',
        customerResults:      [],
        showCustomerDropdown: false,
        selectedCustomer:     null,
        customPrices:         {},
        _itemKey:             1,
        _payKey:              1,
        submitting:           false,
        submissionKey:        (typeof crypto !== 'undefined' && crypto.randomUUID) ? crypto.randomUUID() : Math.random().toString(36).slice(2),

        // ── New state ───────────────────────────────────────────────────────
        categories:      __initialCategories,
        activeCategory:  null,
        categoryFilter:  '',
        globalSearch:    '',
        pendingProduct:  null,
        pendingInput:    '',

        showDatePicker:  false,
        invoiceDate:     '',

        activeTab:       'productos',

        // ── Computed ────────────────────────────────────────────────────────
        get filteredProducts() {
            if (!this.activeCategory) return [];
            const f = this.categoryFilter.toLowerCase();
            if (!f) return this.activeCategory.products;
            return this.activeCategory.products.filter(p => p.name.toLowerCase().includes(f));
        },

        get globalResults() {
            const f = this.globalSearch.toLowerCase();
            if (!f) return [];
            return this.categories
                .flatMap(c => c.products)
                .filter(p => p.name.toLowerCase().includes(f))
                .slice(0, 12);
        },

        get pendingKg() {
            const raw = this.pendingInput.replace(/[^0-9]/g, '');
            return (parseInt(raw) || 0) / 1000;
        },

        get pendingValid() {
            if (!this.pendingProduct) return false;
            if (this.pendingProduct.sale_unit === 'KG') {
                return this.pendingKg >= 0.001;
            }
            return parseInt(this.pendingInput) >= 1;
        },

        get subtotal() {
            return this.items.reduce((s, i) => s + (parseFloat(i.line_total) || 0), 0);
        },
        get rawTotal() {
            return this.subtotal + (parseFloat(this.deliveryFee) || 0);
        },
        get total() {
            const raw = this.rawTotal;
            if (this.requiresFe) return raw;
            const mod = raw % 50;
            return mod === 0 ? raw : raw + (50 - mod);
        },
        get roundingAdjustment() {
            return this.total - this.rawTotal;
        },
        get paidAmount() {
            return this.payments.reduce((s, p) => s + (parseFloat(p.amount) || 0), 0);
        },
        get balance() {
            return Math.max(0, this.total - this.paidAmount);
        },
        get overpay() {
            return this.paidAmount > this.total + 0.001;
        },
        get canSubmit() {
            return this.items.length > 0 && !this.overpay && !this.feError && this.total > 0 && !this.isGenericSelected;
        },
        get isGenericSelected() {
            return !!(this.selectedCustomer && this.selectedCustomer.is_generic);
        },
        get todayStr() {
            return __todayStr;
        },

        // ── Init ────────────────────────────────────────────────────────────
        init() {
            // Customer input starts blank; hidden field falls back to genericId on submit.

            // Keyboard shortcuts
            window.addEventListener('keydown', (e) => {
                if (e.key === 'F2') { e.preventDefault(); this.activeTab = 'cliente'; this.$nextTick(() => this.$refs.customerInput?.focus()); }
                if (e.key === 'F3') { e.preventDefault(); this.activeTab = 'productos'; }
                if (e.key === 'F4') { e.preventDefault(); this.activeTab = 'pagos'; this.$nextTick(() => { document.querySelector('[data-payment-amount]')?.focus(); }); }
                if (e.key === 'Enter' && this.pendingProduct) { e.preventDefault(); this.confirmPending(); }
                if (e.key === 'Escape' && this.pendingProduct) { e.preventDefault(); this.cancelPending(); }
            });
        },

        // ── Category / product flow ─────────────────────────────────────────
        selectCategory(cat) {
            this.activeCategory = cat;
            this.categoryFilter = '';
            this.pendingProduct = null;
            this.pendingInput   = '';
            this.globalSearch   = '';
            this.$nextTick(() => this.$refs.catFilterInput?.focus());
        },

        clearCategory() {
            this.activeCategory = null;
            this.categoryFilter = '';
            this.pendingProduct = null;
            this.pendingInput   = '';
        },

        selectPending(p) {
            this.pendingProduct = p;
            this.pendingInput   = '';
            this.$nextTick(() => this.$refs.qtyInput?.focus());
        },

        cancelPending() {
            this.pendingProduct = null;
            this.pendingInput   = '';
        },

        confirmPending() {
            if (!this.pendingValid) return;
            const p = this.pendingProduct;
            let qty;
            if (p.sale_unit === 'KG') {
                qty = this.pendingKg;
            } else {
                qty = Math.max(1, parseInt(this.pendingInput) || 1);
            }
            this.addProductItem(p, qty);
            this.pendingProduct = null;
            this.pendingInput   = '';
        },

        onPendingGramsInput(event) {
            const raw = event.target.value.replace(/[^0-9]/g, '');
            event.target.value = raw;
            this.pendingInput = raw;
        },

        // ── Product / cart ──────────────────────────────────────────────────
        addProductItem(p, qty) {
            const basePrice      = parseFloat(p.base_price);
            const effectivePrice = this.customPrices[p.id] ?? basePrice;
            const quantity       = qty ?? (p.sale_unit === 'KG' ? 0 : 1);
            const lineTotal      = Math.round((quantity * effectivePrice) * 100) / 100;

            this.items.push({
                _key:         this._itemKey++,
                product_id:   p.id,
                product_name: p.name,
                sale_unit:    p.sale_unit,
                base_price:   basePrice,
                unit_price:   effectivePrice,
                quantity,
                line_total:   lineTotal,
                qtyError:     false,
            });
        },

        computeLineTotal(item) {
            const qty = parseFloat(item.quantity) || 0;
            if (item.sale_unit === 'UNIT' && qty !== Math.floor(qty)) {
                item.qtyError = true;
                item.quantity = Math.floor(qty) || 1;
            } else {
                item.qtyError = false;
            }
            item.line_total = Math.round((qty * item.unit_price) * 100) / 100;
        },

        formatGrams(qty) {
            const g = Math.round((parseFloat(qty) || 0) * 1000);
            return g > 0 ? g.toLocaleString('es-CO') : '';
        },

        onGramsInput(item, event) {
            const raw = event.target.value.replace(/[^0-9]/g, '');
            event.target.value = raw;
            item.quantity = (parseInt(raw) || 0) / 1000;
            this.computeLineTotal(item);
            item.qtyError = raw.length > 0 && item.quantity < 0.001;
        },

        removeItem(idx) { this.items.splice(idx, 1); },

        // ── Customer ────────────────────────────────────────────────────────
        async searchCustomers() {
            if (this.customerSearch.length < 1) { this.customerResults = []; return; }
            const res = await fetch('/customers/search?q=' + encodeURIComponent(this.customerSearch));
            this.customerResults = await res.json();
            this.showCustomerDropdown = true;
        },

        async selectCustomer(c) {
            this.selectedCustomer     = c;
            this.customerSearch       = c.name;
            this.showCustomerDropdown = false;
            this.requiresFe           = c.requires_fe || false;
            this.onFeToggle();
            const res  = await fetch(`/customers/${c.id}/prices`);
            const list = await res.json();
            this.customPrices = Object.fromEntries(list.map(cp => [cp.product_id, parseFloat(cp.price)]));
            this.items.forEach(item => {
                item.unit_price = this.customPrices[item.product_id] ?? item.base_price;
                this.computeLineTotal(item);
            });
        },

        onFeToggle() {
            this.feError = '';
            if (!this.requiresFe) return;
            if (!this.selectedCustomer || this.selectedCustomer.is_generic) {
                this.feError = 'FE requiere un cliente con documento. Usa el formulario o selecciona otro.';
            } else if (!this.selectedCustomer.doc_number) {
                this.feError = 'El cliente necesita número de documento para FE.';
            }
        },

        async createFeCustomer() {
            this.feCreateError = '';
            if (!this.feForm.name || !this.feForm.doc_type || !this.feForm.doc_number) {
                this.feCreateError = 'Nombre, tipo y número de documento son requeridos.';
                return;
            }
            if (this.feForm.doc_type === 'NIT' && !this.feForm.business_name) {
                this.feCreateError = 'Razón social requerida para NIT.';
                return;
            }
            this.feCreating = true;
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const res = await fetch('/customers', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ ...this.feForm, requires_fe: true }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                this.feCreateError = data.message ?? 'Error al crear cliente.';
                this.feCreating = false;
                return;
            }
            // Auto-select new customer and clear FE form
            this.selectedCustomer     = data.customer;
            this.customerSearch       = data.customer.name;
            this.showCustomerDropdown = false;
            this.requiresFe           = true;
            this.feError              = '';
            this.feCreating           = false;
            this.feForm               = { name: '', email: '', doc_type: '', doc_number: '', business_name: '' };
        },

        // ── Payments ────────────────────────────────────────────────────────
        addPaymentMethod(method) {
            this.payments.push({ _key: this._payKey++, method, amount: 0 });
            this.$nextTick(() => {
                const inputs = document.querySelectorAll('[data-payment-amount]');
                inputs[inputs.length - 1]?.focus();
            });
        },

        removePayment(idx) { this.payments.splice(idx, 1); },

        methodLabel(method) {
            return { CASH: 'Efectivo', CARD: 'Tarjeta', NEQUI: 'Nequi', DAVIPLATA: 'Daviplata', BREB: 'Bre-B' }[method] || method;
        },

        // ── Color helper ────────────────────────────────────────────────────
        catColor(colorIndex, variant) {
            const i = colorIndex % CAT_CHIP.length;
            return variant === 'active' ? CAT_ACTIVE[i] : CAT_CHIP[i];
        },

        // ── Utilities ───────────────────────────────────────────────────────
        formatNum(n) {
            return Math.round(parseFloat(n) || 0).toLocaleString('es-CO');
        },

        submitForm(e) {
            this.onFeToggle();
            if (!this.canSubmit || this.submitting) return;
            this.submitting = true;
            e.target.submit();
        },
    };
}
</script>
@endsection
