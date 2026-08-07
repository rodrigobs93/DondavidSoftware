@extends('layouts.app')
@section('title', 'Factura #' . $invoice->consecutive)

@section('content')
<div @if($canEdit) x-data="invoiceEditor()" x-cloak @endif>

<div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="text-xl font-bold text-gray-800">Factura #{{ $invoice->consecutive }}</h1>
        <p class="text-sm text-gray-500">{{ $invoice->invoice_date->format('d/m/Y') }} —
            {{ $invoice->createdBy->name }}</p>
    </div>
    <div class="flex gap-2">
        @if($canEdit)
        <button type="button" x-show="!editing" @click="startEdit()" class="pos-btn-secondary">
            <x-icon.pencil class="w-4 h-4" /> Editar
        </button>
        @endif
        <form method="POST" action="{{ route('invoices.reprint', $invoice) }}"
              @if($canEdit) x-show="!editing" @endif>
            @csrf
            <button class="pos-btn-secondary">🖨 Reimprimir</button>
        </form>
        <a href="{{ route('invoices.index') }}" class="pos-btn-secondary"
           @if($canEdit) x-show="!editing" @endif>← Facturas</a>
    </div>
</div>

<div class="grid md:grid-cols-2 gap-6">
    {{-- Invoice details --}}
    <div class="space-y-4">
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="font-semibold text-gray-700 mb-3">Detalle</h2>

            {{-- ============================ READ-ONLY VIEW ============================ --}}
            <div @if($canEdit) x-show="!editing" @endif>
                {{-- Status badge --}}
                <div class="flex items-center gap-3 mb-3">
                    <span @class([
                        'badge-paid'    => $invoice->isPaid(),
                        'badge-partial' => $invoice->isPartial(),
                        'badge-pending' => $invoice->isPending(),
                    ])>
                        {{ match($invoice->status) {
                            'PAID' => 'PAGADO',
                            'PARTIAL' => 'PARCIAL',
                            default => 'PENDIENTE'
                        } }}
                    </span>
                    <span class="text-sm px-2 py-0.5 rounded-full text-xs font-semibold
                        {{ $invoice->fe_status === 'ISSUED' ? 'bg-green-100 text-green-800' :
                           ($invoice->fe_status === 'PENDING' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600') }}">
                        {{ $invoice->fe_label }}
                    </span>
                </div>

                {{-- Customer --}}
                <div class="text-sm space-y-1">
                    <div><span class="text-gray-500">Cliente:</span>
                        <strong>{{ $invoice->customer->name }}</strong>
                        @if($invoice->customer->doc_label)
                            <span class="text-gray-500">({{ $invoice->customer->doc_label }})</span>
                        @endif
                    </div>
                    @if($invoice->notes)
                        <div><span class="text-gray-500">Notas:</span> {{ $invoice->notes }}</div>
                    @endif
                </div>

                {{-- Items table --}}
                <table class="w-full text-sm mt-4">
                    <thead>
                        <tr class="border-b text-gray-500 text-xs uppercase">
                            <th class="text-left pb-2">Producto</th>
                            <th class="text-right pb-2">Cant.</th>
                            <th class="text-right pb-2">P.Unit</th>
                            <th class="text-right pb-2">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->items as $item)
                        <tr class="border-b last:border-0">
                            <td class="py-2">{{ $item->product_name_snapshot }}</td>
                            <td class="py-2 text-right text-gray-600">{{ $item->formatted_quantity }}</td>
                            <td class="py-2 text-right">${{ number_format($item->unit_price, 0, ',', '.') }}</td>
                            <td class="py-2 text-right font-semibold">${{ number_format($item->line_total, 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="text-sm">
                            <td colspan="3" class="pt-2 text-right text-gray-600">Subtotal</td>
                            <td class="pt-2 text-right">${{ number_format($invoice->subtotal, 0, ',', '.') }}</td>
                        </tr>
                        @if($invoice->delivery_fee > 0)
                        <tr class="text-sm">
                            <td colspan="3" class="text-right text-gray-600">Domicilio</td>
                            <td class="text-right">${{ number_format($invoice->delivery_fee, 0, ',', '.') }}</td>
                        </tr>
                        @endif
                        @php $__adj = $invoice->total - ($invoice->subtotal + $invoice->delivery_fee); @endphp
                        @if($__adj > 0)
                        <tr class="text-xs text-gray-500">
                            <td colspan="3" class="text-right">Ajuste redondeo</td>
                            <td class="text-right">${{ number_format($__adj, 0, ',', '.') }}</td>
                        </tr>
                        @endif
                        <tr class="font-bold text-base border-t">
                            <td colspan="3" class="pt-2 text-right">TOTAL</td>
                            <td class="pt-2 text-right text-green-700">${{ number_format($invoice->total, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if($canEdit)
            {{-- ============================== EDIT MODE ============================== --}}
            @include('invoices._editor')
            @endif
        </div>

        {{-- Payments --}}
        <div class="bg-white rounded-lg shadow p-4" @if($canEdit) x-show="!editing" @endif>
            <h2 class="font-semibold text-gray-700 mb-3">Pagos</h2>
            @foreach($invoice->payments as $payment)
            <div class="flex justify-between text-sm py-1 border-b last:border-0">
                <span class="text-gray-600">{{ \App\Models\Payment::$methods[$payment->method] ?? $payment->method }}</span>
                <span class="font-semibold">${{ number_format($payment->amount, 0, ',', '.') }}</span>
            </div>
            @endforeach
            <div class="flex justify-between text-sm mt-2 pt-2 border-t">
                <span class="text-gray-600">Total pagado</span>
                <span>${{ number_format($invoice->paid_amount, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between font-bold mt-1"
                 style="{{ $invoice->balance > 0 ? 'color:#b45309' : 'color:#15803d' }}">
                <span>Saldo</span>
                <span>${{ number_format($invoice->balance, 0, ',', '.') }}</span>
            </div>
        </div>
    </div>

    {{-- Right column: FE + Cartera + Print --}}
    <div class="space-y-4" @if($canEdit) x-show="!editing" @endif>

        {{-- FE Mark issued --}}
        @if($invoice->fe_status === 'PENDING' && auth()->user()->isAdmin())
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h2 class="font-semibold text-blue-800 mb-2">Marcar FE como Emitida</h2>
            <form method="POST" action="{{ route('invoices.fe-mark-issued', $invoice) }}">
                @csrf
                <div class="flex gap-2">
                    <input type="text" name="fe_reference" placeholder="CUFE o referencia DIAN..."
                        class="flex-1 border rounded px-3 py-2 text-sm" required>
                    <button class="pos-btn-primary">Marcar Emitida</button>
                </div>
                @error('fe_reference')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </form>
        </div>
        @elseif($invoice->fe_status === 'ISSUED')
        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <h2 class="font-semibold text-green-800 mb-1">Factura Electrónica Emitida</h2>
            <p class="text-sm text-green-700">Ref: <strong>{{ $invoice->fe_reference }}</strong></p>
            @if($invoice->fe_issued_at)
                <p class="text-xs text-green-600 mt-1">{{ $invoice->fe_issued_at->setTimezone('America/Bogota')->format('d/m/Y H:i') }}</p>
            @endif
        </div>
        @endif

        {{-- Add payment (cartera) --}}
        @if($invoice->balance > 0)
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <h2 class="font-semibold text-yellow-800 mb-3">
                Registrar Abono
                <span class="text-sm text-yellow-600 font-normal">
                    (Saldo: ${{ number_format($invoice->balance, 0, ',', '.') }})
                </span>
            </h2>
            <form method="POST" action="{{ route('cartera.payments', $invoice) }}">
                @csrf
                <div class="grid grid-cols-2 gap-2 mb-2">
                    <select name="method" class="border rounded px-2 py-2 text-sm">
                        @foreach(\App\Models\Payment::$methods as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="amount" placeholder="Monto"
                        min="0.01" step="0.01" max="{{ $invoice->balance }}"
                        class="border rounded px-2 py-2 text-sm" required>
                </div>
                <input type="text" name="notes" placeholder="Notas (opcional)"
                    class="w-full border rounded px-2 py-2 text-sm mb-2">
                <button class="w-full pos-btn-success">Registrar Abono</button>
                @error('amount')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </form>
        </div>
        @endif

        {{-- Apply credit (saldo a favor) --}}
        @if($invoice->balance > 0 && $invoice->customer->credit_balance > 0)
        @php
            $maxCredit = (int) min($invoice->balance, $invoice->customer->credit_balance);
        @endphp
        <div class="bg-green-50 border border-green-200 rounded-lg p-4"
             x-data="{
                 open: false,
                 amount: {{ $maxCredit }},
                 saving: false,
                 error: '',
                 creditBalance: {{ (int) $invoice->customer->credit_balance }},
                 invoiceBalance: {{ (int) $invoice->balance }},
                 async apply() {
                     this.error = '';
                     this.saving = true;
                     try {
                         const r = await fetch('{{ route('cartera.invoice.apply-credit', $invoice) }}', {
                             method: 'POST',
                             headers: {
                                 'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                 'Content-Type': 'application/json',
                                 'Accept': 'application/json',
                             },
                             body: JSON.stringify({ amount: this.amount }),
                         });
                         const d = await r.json();
                         if (!r.ok) { this.error = d.error ?? 'Error.'; return; }
                         this.invoiceBalance = d.balance;
                         this.creditBalance  = d.credit_balance;
                         this.open = false;
                         if (d.balance <= 0) { window.location.reload(); }
                     } catch { this.error = 'Error de red.'; }
                     finally { this.saving = false; }
                 }
             }">
            <div class="flex items-center justify-between mb-2">
                <h2 class="font-semibold text-green-800">Pagar con saldo a favor</h2>
                <span class="text-sm text-green-700 font-mono"
                      x-text="'$' + creditBalance.toLocaleString('es-CO')"></span>
            </div>
            <div x-show="!open">
                <p class="text-xs text-green-600 mb-2">
                    El cliente tiene saldo a favor disponible para aplicar a esta factura.
                </p>
                <button @click="open = true"
                        class="w-full pos-btn pos-btn-success justify-center">
                    💚 Aplicar saldo a favor
                </button>
            </div>
            <div x-show="open" x-cloak class="space-y-2">
                <div class="text-sm text-green-700">
                    Máximo aplicable:
                    <strong x-text="'$' + Math.min(creditBalance, invoiceBalance).toLocaleString('es-CO')"></strong>
                </div>
                <input type="number" x-model.number="amount"
                       :max="Math.min(creditBalance, invoiceBalance)" min="1" step="1"
                       class="w-full border rounded px-3 py-2 text-sm">
                <div class="flex gap-2">
                    <button @click="apply()" :disabled="saving"
                            class="flex-1 pos-btn pos-btn-success">
                        <span x-show="!saving">Confirmar</span>
                        <span x-show="saving">Aplicando…</span>
                    </button>
                    <button @click="open = false"
                            class="pos-btn pos-btn-secondary">Cancelar</button>
                </div>
                <p x-show="error" x-cloak class="text-red-500 text-xs" x-text="error"></p>
            </div>
        </div>
        @endif

        {{-- Print job status --}}
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="font-semibold text-gray-700 mb-2">Cola de Impresión</h2>
            @foreach($invoice->printJobs as $job)
            <div class="flex justify-between text-xs py-1">
                <span class="text-gray-500">Job #{{ $job->id }}</span>
                <span class="px-2 py-0.5 rounded-full font-semibold
                    {{ match($job->status) {
                        'PRINTED' => 'bg-green-100 text-green-700',
                        'FAILED' => 'bg-red-100 text-red-700',
                        'PRINTING' => 'bg-yellow-100 text-yellow-700',
                        default => 'bg-gray-100 text-gray-600'
                    } }}">
                    {{ $job->status }}
                </span>
            </div>
            @if($job->error_message)
                <p class="text-red-500 text-xs mt-0.5">{{ $job->error_message }}</p>
            @endif
            @endforeach
            @if($invoice->printJobs->isEmpty())
                <p class="text-gray-400 text-xs">Sin trabajos de impresión.</p>
            @endif
        </div>
    </div>
</div>

@if($canEdit)
<script>
const __editData = {!! json_encode([
    'id'           => $invoice->id,
    'requires_fe'  => (bool) $invoice->requires_fe,
    'paid_amount'  => (float) $invoice->paid_amount,
    'delivery_fee' => (float) $invoice->delivery_fee,
    'invoice_date' => $invoice->invoice_date->format('Y-m-d'),
    'notes'        => $invoice->notes,
    'customer'     => [
        'id'         => $invoice->customer->id,
        'name'       => $invoice->customer->name,
        'is_generic' => (bool) $invoice->customer->is_generic,
        'doc_type'   => $invoice->customer->doc_type,
        'doc_number' => $invoice->customer->doc_number,
    ],
    'items'        => $invoice->items->map(fn($it) => [
        'product_id'   => $it->product_id,
        'product_name' => $it->product_name_snapshot,
        'sale_unit'    => $it->sale_unit_snapshot,
        'quantity'     => (float) $it->quantity,
        'unit_price'   => (float) $it->unit_price,
        'line_total'   => (float) $it->line_total,
    ])->values(),
], JSON_HEX_TAG) !!};
const __editCats      = {!! json_encode($cats, JSON_HEX_TAG) !!};
const __editGenericId = {{ $generic?->id ?? 'null' }};
const __editTodayStr  = new Date().toISOString().slice(0, 10);
const __editHasErrors = {{ $errors->any() ? 'true' : 'false' }};
const __editUpdateUrl = '{{ route('invoices.update', $invoice) }}';

const EDIT_CAT_CHIP = [
    'bg-red-50    text-red-700    border-red-300    hover:bg-red-100',
    'bg-blue-50   text-blue-700   border-blue-300   hover:bg-blue-100',
    'bg-green-50  text-green-700  border-green-300  hover:bg-green-100',
    'bg-purple-50 text-purple-700 border-purple-300 hover:bg-purple-100',
    'bg-orange-50 text-orange-700 border-orange-300 hover:bg-orange-100',
    'bg-teal-50   text-teal-700   border-teal-300   hover:bg-teal-100',
];
const EDIT_CAT_ACTIVE = [
    'bg-red-500    text-white border-red-500',
    'bg-blue-500   text-white border-blue-500',
    'bg-green-500  text-white border-green-500',
    'bg-purple-500 text-white border-purple-500',
    'bg-orange-500 text-white border-orange-500',
    'bg-teal-500   text-white border-teal-500',
];

function invoiceEditor() {
    return {
        editing:        __editHasErrors,   // reopen the editor after a validation error
        requiresFe:     __editData.requires_fe,
        paidAmount:     __editData.paid_amount,

        // Editable header fields
        invoiceDate:    __editData.invoice_date,
        deliveryFee:    __editData.delivery_fee,
        notes:          __editData.notes || '',

        // Customer
        customerId:      __editData.customer.id,
        customerName:    __editData.customer.name,
        custIsGeneric:   __editData.customer.is_generic,
        custDocType:     __editData.customer.doc_type,
        custDocNumber:   __editData.customer.doc_number,
        customerSearch:  '',
        customerResults: [],
        showCustomerDropdown: false,

        // Items (cloned so Cancel can restore the original snapshot)
        items:  [],
        _itemKey: 1,

        // Catalog picker
        categories:     __editCats,
        showPicker:     false,
        swapIndex:      null,          // row index being replaced (null = add new)
        activeCategory: null,
        categoryFilter: '',
        globalSearch:   '',
        pendingProduct: null,
        pendingInput:   '',

        // Temporary (off-catalog) line
        showTempForm: false,
        tempForm:     { name: '', sale_unit: 'UNIT', unit_price: '', qty: '' },
        tempError:    '',

        submitting: false,

        // ── Lifecycle ─────────────────────────────────────────────────────
        seedItems() {
            this.items = __editData.items.map(it => ({
                _key:         this._itemKey++,
                product_id:   it.product_id,
                product_name: it.product_name,
                sale_unit:    it.sale_unit,
                unit_price:   it.unit_price,
                quantity:     it.quantity,
                line_total:   it.line_total,
            }));
        },
        startEdit() {
            this.seedItems();
            this.editing = true;
        },
        cancelEdit() {
            this.editing = false;
            this.closePicker();
            this.cancelTempForm();
        },
        init() {
            // Seed on load too, so an editor reopened after a validation error
            // shows the last-saved (DB) state.
            this.seedItems();
        },

        // ── Computed money ────────────────────────────────────────────────
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
        get newBalance() {
            return this.total - this.paidAmount;
        },
        get displayBalance() {
            return Math.max(0, this.newBalance);
        },
        get refundAmount() {
            return this.newBalance < 0 ? (this.paidAmount - this.total) : 0;
        },
        get feError() {
            if (!this.requiresFe) return '';
            if (this.custIsGeneric) return 'FE no permitida para el cliente GENÉRICO. Elige otro cliente.';
            if (!this.custDocNumber) return 'El cliente necesita documento para FE.';
            return '';
        },
        get canSave() {
            return this.items.length > 0 && this.total > 0 && !!this.customerId
                   && !this.feError && !this.submitting;
        },
        get todayStr() { return __editTodayStr; },

        // ── Items ─────────────────────────────────────────────────────────
        computeLineTotal(item) {
            let qty = parseFloat(item.quantity) || 0;
            if (item.sale_unit === 'UNIT' && qty !== Math.floor(qty)) {
                qty = Math.floor(qty) || 1;
                item.quantity = qty;
            }
            item.line_total = Math.round((qty * (parseFloat(item.unit_price) || 0)) * 100) / 100;
        },
        fmtGrams(kg) { return window.KgGrams.formatGrams(kg); },
        onRowGrams(item, event) {
            const raw = window.KgGrams.rawGrams(event.target.value);
            event.target.value = raw;
            item.quantity = (parseInt(raw) || 0) / 1000;
            this.computeLineTotal(item);
        },
        removeItem(idx) { this.items.splice(idx, 1); },

        // ── Catalog picker (add or swap a line) ───────────────────────────
        openPicker() {
            this.swapIndex   = null;
            this.showPicker  = true;
            this.showTempForm = false;
            this.pendingProduct = null;
            this.pendingInput = '';
        },
        openSwap(idx) {
            this.swapIndex   = idx;
            this.showPicker  = true;
            this.showTempForm = false;
            this.pendingProduct = null;
            this.pendingInput = '';
            this.$nextTick(() => this.$refs.pickerBox?.scrollIntoView({ block: 'nearest' }));
        },
        closePicker() {
            this.showPicker   = false;
            this.swapIndex    = null;
            this.activeCategory = null;
            this.categoryFilter = '';
            this.globalSearch = '';
            this.pendingProduct = null;
            this.pendingInput = '';
        },
        get filteredProducts() {
            if (!this.activeCategory) return [];
            const f = this.categoryFilter.toLowerCase();
            if (!f) return this.activeCategory.products;
            return this.activeCategory.products.filter(p => p.name.toLowerCase().includes(f));
        },
        get globalResults() {
            const f = this.globalSearch.toLowerCase();
            if (!f) return [];
            return this.categories.flatMap(c => c.products)
                .filter(p => p.name.toLowerCase().includes(f)).slice(0, 12);
        },
        selectCategory(cat) {
            this.activeCategory = cat;
            this.categoryFilter = '';
            this.pendingProduct = null;
            this.pendingInput   = '';
            this.globalSearch   = '';
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
        get pendingKg() { return window.KgGrams.toKg(this.pendingInput); },
        get pendingValid() {
            if (!this.pendingProduct) return false;
            return this.pendingProduct.sale_unit === 'KG'
                ? this.pendingKg >= 0.001
                : parseInt(this.pendingInput) >= 1;
        },
        onPendingGramsInput(event) {
            const raw = window.KgGrams.rawGrams(event.target.value);
            event.target.value = raw;
            this.pendingInput = raw;
        },
        confirmPending() {
            if (!this.pendingValid) return;
            const p   = this.pendingProduct;
            const qty = p.sale_unit === 'KG' ? this.pendingKg : Math.max(1, parseInt(this.pendingInput) || 1);
            const price = parseFloat(p.base_price) || 0;
            const row = {
                _key:         this._itemKey++,
                product_id:   p.id ?? null,
                product_name: p.name,
                sale_unit:    p.sale_unit,
                unit_price:   price,
                quantity:     qty,
                line_total:   Math.round((qty * price) * 100) / 100,
            };
            if (this.swapIndex !== null) {
                this.items.splice(this.swapIndex, 1, row);
            } else {
                this.items.push(row);
            }
            this.closePicker();
        },

        // ── Temporary (off-catalog) line ──────────────────────────────────
        openTempForm() {
            this.showTempForm = true;
            this.tempError    = '';
            this.pendingProduct = null;
            this.pendingInput = '';
            this.$nextTick(() => this.$refs.tempNameInput?.focus());
        },
        cancelTempForm() {
            this.showTempForm = false;
            this.tempError    = '';
            this.tempForm     = { name: '', sale_unit: 'UNIT', unit_price: '', qty: '' };
        },
        setTempUnit(unit) {
            if (this.tempForm.sale_unit === unit) return;
            this.tempForm.sale_unit = unit;
            this.tempForm.qty       = '';
        },
        onTempGramsInput(event) {
            const raw = window.KgGrams.rawGrams(event.target.value);
            event.target.value = raw;
            this.tempForm.qty  = raw;
        },
        get tempKg() { return window.KgGrams.toKg(this.tempForm.qty); },
        get tempQuantity() {
            return this.tempForm.sale_unit === 'KG' ? this.tempKg : (parseInt(this.tempForm.qty) || 0);
        },
        get tempLineTotal() {
            const price = parseFloat(this.tempForm.unit_price) || 0;
            return Math.round((this.tempQuantity * price) * 100) / 100;
        },
        get tempValid() {
            if (!this.tempForm.name.trim()) return false;
            const price = parseFloat(this.tempForm.unit_price);
            if (!(price >= 0) || this.tempForm.unit_price === '') return false;
            return this.tempForm.sale_unit === 'KG' ? this.tempQuantity >= 0.001 : this.tempQuantity >= 1;
        },
        addTempItem() {
            this.tempError = '';
            if (!this.tempValid) { this.tempError = 'Completa descripción, precio y cantidad.'; return; }
            const price = parseFloat(this.tempForm.unit_price) || 0;
            const qty   = this.tempQuantity;
            const row = {
                _key:         this._itemKey++,
                product_id:   null,
                product_name: this.tempForm.name.trim(),
                sale_unit:    this.tempForm.sale_unit,
                unit_price:   price,
                quantity:     qty,
                line_total:   Math.round((qty * price) * 100) / 100,
            };
            if (this.swapIndex !== null) {
                this.items.splice(this.swapIndex, 1, row);
            } else {
                this.items.push(row);
            }
            this.cancelTempForm();
            this.closePicker();
        },

        // ── Customer ──────────────────────────────────────────────────────
        async searchCustomers() {
            if (this.customerSearch.length < 1) { this.customerResults = []; return; }
            const res = await fetch('/customers/search?q=' + encodeURIComponent(this.customerSearch));
            this.customerResults = await res.json();
            this.showCustomerDropdown = true;
        },
        selectCustomer(c) {
            this.customerId    = c.id;
            this.customerName  = c.name;
            this.custIsGeneric = c.is_generic;
            this.custDocType   = c.doc_type;
            this.custDocNumber = c.doc_number;
            this.customerSearch = '';
            this.customerResults = [];
            this.showCustomerDropdown = false;
        },

        // ── Helpers ───────────────────────────────────────────────────────
        formatNum(n) { return Math.round(parseFloat(n) || 0).toLocaleString('es-CO'); },
        catColor(colorIndex, variant) {
            const i = colorIndex % EDIT_CAT_CHIP.length;
            return variant === 'active' ? EDIT_CAT_ACTIVE[i] : EDIT_CAT_CHIP[i];
        },

        submitForm(e) {
            const kb = this.$store.keyboard;
            if (kb && kb.open) return;
            if (!this.canSave) return;
            this.submitting = true;
            e.target.submit();
        },
    };
}
</script>
@endif

</div>{{-- end wrapper --}}
@endsection
