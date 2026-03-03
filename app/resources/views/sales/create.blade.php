@extends('layouts.app')
@section('title', 'Nueva Venta')

@section('content')
<div x-data="saleForm()" x-init="init()" x-cloak>
<h1 class="text-xl font-bold text-gray-800 mb-4">Nueva Venta</h1>

<form method="POST" action="{{ route('sales.store') }}" @submit.prevent="submitForm($event)">
    @csrf

    <div class="grid md:grid-cols-2 gap-6">
        {{-- LEFT COLUMN --}}
        <div class="space-y-4">

            {{-- Customer picker --}}
            <div class="bg-white rounded-lg shadow p-4">
                <h2 class="font-semibold text-gray-700 mb-3">Cliente</h2>

                <div class="relative">
                    <input type="text" x-model="customerSearch" @input.debounce.300ms="searchCustomers()"
                        @focus="showCustomerDropdown=true" @keydown.escape="showCustomerDropdown=false"
                        placeholder="Buscar cliente..." autocomplete="off"
                        class="form-input w-full border rounded px-3 py-2 text-sm">
                    <input type="hidden" name="customer_id" :value="selectedCustomer?.id">

                    <div x-show="showCustomerDropdown && customerResults.length > 0"
                         class="absolute z-20 w-full bg-white border rounded shadow-lg mt-1 max-h-48 overflow-auto">
                        <template x-for="c in customerResults" :key="c.id">
                            <button type="button"
                                @click="selectCustomer(c)"
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

                <div x-show="selectedCustomer" class="mt-2 text-sm text-gray-600">
                    Cliente: <strong x-text="selectedCustomer?.name"></strong>
                    <span x-show="selectedCustomer?.is_generic" class="text-xs text-gray-400">(GENÉRICO)</span>
                </div>

                {{-- FE Toggle --}}
                <div class="mt-3 pt-3 border-t">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" x-model="requiresFe"
                            @change="onFeToggle()" class="rounded">
                        <input type="hidden" name="requires_fe" :value="requiresFe ? 1 : 0">
                        <span class="text-sm font-medium text-gray-700">Requiere Factura Electrónica (FE)</span>
                    </label>
                    <div x-show="feError" class="text-red-500 text-xs mt-1" x-text="feError"></div>
                    <div x-show="requiresFe && selectedCustomer && !selectedCustomer.is_generic && selectedCustomer.doc_number"
                         class="text-xs text-green-600 mt-1">
                        OK — cliente con documento válido
                    </div>
                </div>
            </div>

            {{-- Product search --}}
            <div class="bg-white rounded-lg shadow p-4">
                <h2 class="font-semibold text-gray-700 mb-3">Agregar Producto</h2>
                <div class="relative">
                    <input type="text" x-model="productSearch" @input.debounce.200ms="searchProducts()"
                        @focus="showProductDropdown=true" @keydown.escape="showProductDropdown=false"
                        @keydown.enter.prevent="selectFirstProduct()"
                        placeholder="Buscar producto... (Enter para seleccionar primero)"
                        autocomplete="off" id="product-search-input"
                        class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">

                    <div x-show="showProductDropdown && productResults.length > 0"
                         class="absolute z-20 w-full bg-white border rounded shadow-lg mt-1 max-h-48 overflow-auto">
                        <template x-for="p in productResults" :key="p.id">
                            <button type="button" @click="addProductItem(p)"
                                class="w-full text-left px-3 py-2 hover:bg-blue-50 text-sm">
                                <span x-text="p.name"></span>
                                <span class="text-gray-400 text-xs ml-1"
                                      x-text="'$' + formatNum(p.base_price) + ' / ' + (p.sale_unit === 'KG' ? 'kg' : 'und')"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Delivery fee --}}
            <div class="bg-white rounded-lg shadow p-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Domicilio (opcional)</label>
                <div class="flex items-center gap-2">
                    <span class="text-gray-500 text-sm">$</span>
                    <input type="number" name="delivery_fee" x-model.number="deliveryFee"
                        min="0" step="500" placeholder="0"
                        class="border rounded px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            {{-- Notes --}}
            <div class="bg-white rounded-lg shadow p-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Notas (opcional)</label>
                <textarea name="notes" rows="2" placeholder="Instrucciones especiales, referencias..."
                    class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
        </div>

        {{-- RIGHT COLUMN: Items + Checkout --}}
        <div class="space-y-4">

            {{-- Items list --}}
            <div class="bg-white rounded-lg shadow p-4">
                <h2 class="font-semibold text-gray-700 mb-3">
                    Productos <span class="text-gray-400 text-sm" x-text="'(' + items.length + ')'"></span>
                </h2>

                <div x-show="items.length === 0" class="text-gray-400 text-sm text-center py-4">
                    Busca y agrega productos a la izquierda.
                </div>

                <template x-for="(item, idx) in items" :key="item._key">
                    <div class="flex items-center gap-2 py-2 border-b last:border-0">
                        <input type="hidden" :name="'items['+idx+'][product_id]'" :value="item.product_id">
                        <input type="hidden" :name="'items['+idx+'][product_name]'" :value="item.product_name">
                        <input type="hidden" :name="'items['+idx+'][sale_unit]'" :value="item.sale_unit">
                        <input type="hidden" :name="'items['+idx+'][unit_price]'" :value="item.unit_price">
                        <input type="hidden" :name="'items['+idx+'][quantity]'" :value="item.quantity">

                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-sm truncate" x-text="item.product_name"></div>
                            <div class="text-xs text-gray-500">
                                $<span x-text="formatNum(item.unit_price)"></span>
                                / <span x-text="item.sale_unit === 'KG' ? 'kg' : 'und'"></span>
                                <span x-show="customPrices[item.product_id] !== undefined"
                                      class="ml-1 text-purple-600 font-semibold">precio especial</span>
                            </div>
                        </div>

                        <div class="w-24">
                            {{-- KG: user types grams (whole number), stored as kg --}}
                            <input x-show="item.sale_unit === 'KG'"
                                type="text" inputmode="numeric"
                                x-init="$el.value = formatGrams(item.quantity)"
                                @focus="$el.value = String(Math.round(item.quantity * 1000) || '')"
                                @input="onGramsInput(item, $event)"
                                @blur="$el.value = formatGrams(item.quantity)"
                                class="w-full border rounded px-2 py-1 text-sm text-center"
                                :class="item.qtyError ? 'border-red-400' : ''">
                            {{-- UNIT: unchanged integer input --}}
                            <input x-show="item.sale_unit !== 'KG'"
                                type="number"
                                x-model.number="item.quantity"
                                @input="computeLineTotal(item)"
                                step="1" min="1"
                                class="w-full border rounded px-2 py-1 text-sm text-center"
                                :class="item.qtyError ? 'border-red-400' : ''">
                            <div class="text-xs text-center text-gray-400"
                                 x-text="item.sale_unit === 'KG' ? 'g' : 'und'"></div>
                        </div>

                        <div class="text-right w-20">
                            <div class="text-sm font-semibold text-gray-700">
                                $<span x-text="formatNum(item.line_total)"></span>
                            </div>
                        </div>

                        <button type="button" @click="removeItem(idx)"
                            class="text-red-400 hover:text-red-600 text-lg leading-none">&times;</button>
                    </div>
                </template>
            </div>

            {{-- Totals --}}
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
                    <div class="flex justify-between text-base font-bold border-t pt-2 mt-2">
                        <span>TOTAL</span>
                        <span class="font-mono text-green-700">$<span x-text="formatNum(total)"></span></span>
                    </div>
                </div>
            </div>

            {{-- Payments --}}
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-semibold text-gray-700">Pagos</h2>
                    <button type="button" @click="addPayment()"
                        class="text-blue-600 text-sm hover:text-blue-800">+ Agregar</button>
                </div>

                <template x-for="(pay, pidx) in payments" :key="pay._key">
                    <div class="flex items-center gap-2 mb-2">
                        <input type="hidden" :name="'payments['+pidx+'][method]'" :value="pay.method">
                        <input type="hidden" :name="'payments['+pidx+'][amount]'" :value="pay.amount">

                        <select x-model="pay.method"
                            @change="pay.method = $event.target.value; updatePaymentInput(pay, pidx)"
                            class="border rounded px-2 py-1 text-sm flex-shrink-0 w-28">
                            <option value="CASH">Efectivo</option>
                            <option value="CARD">Tarjeta</option>
                            <option value="NEQUI">Nequi</option>
                            <option value="DAVIPLATA">Daviplata</option>
                            <option value="BREB">Bre-B</option>
                        </select>

                        <input type="number" x-model.number="pay.amount" min="0" step="1"
                            @input="pay.amount = parseFloat($event.target.value)||0; updatePaymentInput(pay, pidx)"
                            placeholder="0"
                            class="border rounded px-2 py-1 text-sm flex-1 text-right">

                        <button type="button" @click="removePayment(pidx)"
                            class="text-red-400 hover:text-red-600 text-lg leading-none flex-shrink-0"
                            x-show="payments.length > 1">&times;</button>
                    </div>
                </template>

                <div class="border-t pt-2 mt-2 space-y-1 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Total pagado</span>
                        <span class="font-mono" :class="overpay ? 'text-red-600 font-bold' : ''">
                            $<span x-text="formatNum(paidAmount)"></span>
                        </span>
                    </div>
                    <div class="flex justify-between font-semibold"
                         :class="balance > 0 ? 'text-yellow-700' : 'text-green-700'">
                        <span>Saldo</span>
                        <span class="font-mono">$<span x-text="formatNum(balance)"></span></span>
                    </div>
                    <div x-show="overpay" class="text-red-500 text-xs font-semibold">
                        El pago supera el total. Ajusta los montos.
                    </div>
                </div>
            </div>

            {{-- Submit — color and label reflect payment status --}}
            <button type="submit"
                :disabled="!canSubmit"
                :class="canSubmit
                    ? (balance > 0 ? 'bg-yellow-600 hover:bg-yellow-700 text-white' : 'bg-green-600 hover:bg-green-700 text-white')
                    : 'bg-gray-300 text-gray-500 cursor-not-allowed'"
                class="w-full py-3 rounded-lg font-bold text-lg transition-colors shadow">
                <span x-show="!canSubmit && items.length === 0">Agrega al menos un producto</span>
                <span x-show="!canSubmit && items.length > 0 && overpay">Pago inválido — ajusta los montos</span>
                <span x-show="!canSubmit && items.length > 0 && !!feError">Error en FE</span>
                <span x-show="canSubmit && balance === 0">
                    Finalizar Venta PAGADA — $<span x-text="formatNum(total)"></span>
                </span>
                <span x-show="canSubmit && paidAmount > 0 && balance > 0">
                    Finalizar Venta PARCIAL — abona $<span x-text="formatNum(paidAmount)"></span>
                </span>
                <span x-show="canSubmit && paidAmount === 0">
                    Finalizar Venta PENDIENTE — $<span x-text="formatNum(total)"></span> por cobrar
                </span>
            </button>
        </div>
    </div>
</form>
</div>

<script>
function saleForm() {
    return {
        items: [],
        payments: [{ _key: 1, method: 'CASH', amount: 0 }],
        deliveryFee: 0,
        requiresFe: false,
        feError: '',
        customerSearch: '',
        customerResults: [],
        showCustomerDropdown: false,
        selectedCustomer: null,
        customPrices: {},
        productSearch: '',
        productResults: [],
        showProductDropdown: false,
        _itemKey: 1,
        _payKey: 2,

        init() {
            // Pre-select generic customer
            @if($generic)
            this.selectedCustomer = {
                id: {{ $generic->id }},
                name: '{{ $generic->name }}',
                is_generic: true,
                doc_type: null,
                doc_number: null,
                requires_fe: false,
            };
            this.customerSearch = this.selectedCustomer.name;
            @endif
            this.$watch('deliveryFee', () => this.computeTotals());
        },

        get subtotal() {
            return this.items.reduce((s, i) => s + (parseFloat(i.line_total) || 0), 0);
        },
        get total() {
            return this.subtotal + (parseFloat(this.deliveryFee) || 0);
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
            return this.items.length > 0 && !this.overpay && !this.feError && this.total > 0;
        },

        async searchCustomers() {
            if (this.customerSearch.length < 1) { this.customerResults = []; return; }
            const res = await fetch('/customers/search?q=' + encodeURIComponent(this.customerSearch));
            this.customerResults = await res.json();
            this.showCustomerDropdown = true;
        },

        async selectCustomer(c) {
            this.selectedCustomer = c;
            this.customerSearch = c.name;
            this.showCustomerDropdown = false;
            this.requiresFe = c.requires_fe || false;
            this.onFeToggle();
            // Fetch special prices for this customer
            const res = await fetch(`/customers/${c.id}/prices`);
            const list = await res.json();
            this.customPrices = Object.fromEntries(list.map(cp => [cp.product_id, parseFloat(cp.price)]));
            // Re-price any items already in the cart
            this.items.forEach(item => {
                item.unit_price = this.customPrices[item.product_id] ?? item.base_price;
                this.computeLineTotal(item);
            });
        },

        onFeToggle() {
            this.feError = '';
            if (!this.requiresFe) return;
            if (!this.selectedCustomer || this.selectedCustomer.is_generic) {
                this.feError = 'FE no aplica para cliente GENÉRICO. Selecciona otro cliente.';
            } else if (!this.selectedCustomer.doc_number) {
                this.feError = 'El cliente necesita número de documento para FE.';
            }
        },

        async searchProducts() {
            if (this.productSearch.length < 1) { this.productResults = []; return; }
            const res = await fetch('/products/search?q=' + encodeURIComponent(this.productSearch));
            this.productResults = await res.json();
            this.showProductDropdown = true;
        },

        selectFirstProduct() {
            if (this.productResults.length > 0) this.addProductItem(this.productResults[0]);
        },

        addProductItem(p) {
            const basePrice = parseFloat(p.base_price);
            const effectivePrice = this.customPrices[p.id] ?? basePrice;
            this.items.push({
                _key: this._itemKey++,
                product_id: p.id,
                product_name: p.name,
                sale_unit: p.sale_unit,
                base_price: basePrice,
                unit_price: effectivePrice,
                quantity: p.sale_unit === 'KG' ? 1.000 : 1,
                line_total: effectivePrice,
                qtyError: false,
            });
            this.computeLineTotal(this.items[this.items.length - 1]);
            this.productSearch = '';
            this.productResults = [];
            this.showProductDropdown = false;
            this.$nextTick(() => document.getElementById('product-search-input')?.focus());
        },

        computeLineTotal(item) {
            const qty = parseFloat(item.quantity) || 0;
            if (item.sale_unit === 'UNIT' && qty !== Math.floor(qty)) {
                item.qtyError = true;
                item.quantity = Math.floor(qty) || 1;
            } else {
                item.qtyError = item.sale_unit === 'KG' && qty < 0.001;
            }
            item.line_total = Math.round((qty * item.unit_price) * 100) / 100;
        },

        // KG input helpers —— user enters grams, we store kg
        formatGrams(qty) {
            const g = Math.round((parseFloat(qty) || 0) * 1000);
            return g > 0 ? g.toLocaleString('es-CO') : '';
        },

        onGramsInput(item, event) {
            const raw = event.target.value.replace(/[^0-9]/g, '');
            event.target.value = raw;                   // strip non-digits in place (no cursor issue: only digits allowed)
            item.quantity = (parseInt(raw) || 0) / 1000;
            this.computeLineTotal(item);
        },

        computeTotals() {},

        removeItem(idx) { this.items.splice(idx, 1); },

        addPayment() {
            this.payments.push({ _key: this._payKey++, method: 'CASH', amount: 0 });
        },

        removePayment(idx) { this.payments.splice(idx, 1); },

        updatePaymentInput(pay, idx) { /* handled by x-model */ },

        formatNum(n) {
            return Math.round(parseFloat(n) || 0).toLocaleString('es-CO');
        },

        submitForm(e) {
            this.onFeToggle();
            if (!this.canSubmit) return;
            e.target.submit();
        },
    };
}
</script>
@endsection
