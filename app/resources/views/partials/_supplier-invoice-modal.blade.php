{{-- Supplier Invoice Modal — triggered by the 'open-supplier-invoice' window event.
     Detail payload (optional): { supplierId, supplierName, locked }
       - locked=true  -> preselect & lock the supplier (opened from supplier detail)
       - no payload    -> user picks the supplier from a dropdown (opened from list)

     Reuses the shared window.KgGrams helpers + partials/_kg-unit-qty for KG grams input. --}}
@php $supMethods = \App\Models\SupplierPayment::$methods; @endphp
<div x-data="supplierInvoiceModal()" x-cloak
     @open-supplier-invoice.window="open($event.detail)"
     @keydown.escape.window="close()"
     x-show="show"
     class="fixed inset-x-0 top-0 flex items-start justify-center p-4 overflow-y-auto"
     :style="{ zIndex: 1000, bottom: $store.keyboard.open ? $store.keyboard.height + 'px' : '0' }">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/50" @click="close()"></div>

    {{-- Panel --}}
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl z-10 my-4 overflow-y-auto max-h-[92vh]"
         @click.stop>

        {{-- ── STAGE: form ───────────────────────────────────────────────── --}}
        <div x-show="stage === 'form'" class="px-5 pt-5 pb-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-gray-800">+ Nueva factura de proveedor</h2>
                <button type="button" @click="close()" class="pos-btn-icon">&times;</button>
            </div>

            {{-- General error banner --}}
            <div x-show="errorMsg" x-cloak
                 class="mb-3 p-2 bg-red-50 border border-red-200 text-red-700 text-sm rounded"
                 x-text="errorMsg"></div>

            {{-- Supplier --}}
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Proveedor *</label>
                <template x-if="supplierLocked">
                    <div class="form-input bg-gray-100 font-semibold" x-text="supplierName"></div>
                </template>
                <template x-if="!supplierLocked">
                    <select x-model.number="supplierId" class="form-input">
                        <option value="">Seleccione un proveedor…</option>
                        <template x-for="s in suppliers" :key="s.id">
                            <option :value="s.id" x-text="s.name"></option>
                        </template>
                    </select>
                </template>
                <p x-show="fieldErrors.supplier" x-cloak class="text-red-500 text-xs mt-1" x-text="fieldErrors.supplier"></p>
            </div>

            {{-- Invoice meta --}}
            <div class="grid sm:grid-cols-3 gap-3 mb-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">N.º factura</label>
                    <input type="text" x-model="invoiceNumber" data-keyboard="text"
                           class="form-input" placeholder="Opcional">
                    <p x-show="fieldErrors.invoice_number" x-cloak class="text-red-500 text-xs mt-1" x-text="fieldErrors.invoice_number"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha factura *</label>
                    <input type="date" x-model="invoiceDate" class="form-input">
                    <p x-show="fieldErrors.invoice_date" x-cloak class="text-red-500 text-xs mt-1" x-text="fieldErrors.invoice_date"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Vencimiento</label>
                    <input type="date" x-model="dueDate" class="form-input">
                    <p x-show="fieldErrors.due_date" x-cloak class="text-red-500 text-xs mt-1" x-text="fieldErrors.due_date"></p>
                </div>
            </div>

            {{-- Items --}}
            <div class="border rounded-lg p-3 mb-3">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-700">Ítems (opcional)</span>
                    <button type="button" @click="addRow()" class="pos-btn pos-btn-secondary text-xs py-1">+ Ítem</button>
                </div>
                <div class="hidden sm:grid grid-cols-12 gap-2 mb-1 text-xs text-gray-400 font-medium">
                    <span class="col-span-3">Descripción</span>
                    <span class="col-span-2">Unidad</span>
                    <span class="col-span-2">Cantidad</span>
                    <span class="col-span-2">P. unit.</span>
                    <span class="col-span-2 text-right">Total</span>
                    <span class="col-span-1"></span>
                </div>
                <template x-for="(row, idx) in items" :key="idx">
                    <div class="mb-2">
                        {{-- DESKTOP: dense grid row (aligned columns) --}}
                        <div class="hidden sm:grid grid-cols-12 gap-2 items-start">
                            <input type="text" x-model="row.description"
                                   placeholder="Descripción" data-keyboard="text"
                                   class="col-span-3 border rounded px-2 py-1 text-sm">
                            <select x-model="row.sale_unit"
                                    @change="row.quantity = ''; row.gramsDisplay = ''"
                                    class="col-span-2 border rounded px-2 py-1 text-sm">
                                <option value="UNIT">und</option>
                                <option value="KG">kg</option>
                            </select>
                            @include('partials._kg-unit-qty', ['wrapperClass' => 'col-span-2'])
                            <input type="number" step="1" min="0" x-model="row.unit_price"
                                   placeholder="P. unit." data-keyboard="numeric"
                                   class="col-span-2 border rounded px-2 py-1 text-sm">
                            <span class="col-span-2 text-right text-sm text-gray-600 pt-1.5" x-text="fmt(lineTotal(row))"></span>
                            <button type="button" @click="removeRow(idx)" class="col-span-1 text-red-500 text-sm pt-1">✕</button>
                        </div>

                        {{-- MOBILE: stacked, labeled card --}}
                        <div class="sm:hidden border border-gray-200 rounded-lg bg-gray-50 p-3 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-gray-500" x-text="'Ítem ' + (idx + 1)"></span>
                                <button type="button" @click="removeRow(idx)"
                                        class="text-red-600 text-sm font-medium px-2 py-1 -mr-1">Quitar ✕</button>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Descripción</label>
                                <input type="text" x-model="row.description"
                                       placeholder="Descripción" data-keyboard="text" class="form-input">
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Unidad</label>
                                    <select x-model="row.sale_unit"
                                            @change="row.quantity = ''; row.gramsDisplay = ''"
                                            class="form-input">
                                        <option value="UNIT">und</option>
                                        <option value="KG">kg</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Cantidad</label>
                                    @include('partials._kg-unit-qty', ['wrapperClass' => '', 'inputClass' => 'form-input'])
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2 items-end">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">P. unit.</label>
                                    <input type="number" step="1" min="0" x-model="row.unit_price"
                                           placeholder="0" data-keyboard="numeric" class="form-input">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Total</label>
                                    <div class="form-input bg-gray-100 text-right font-semibold" x-text="fmt(lineTotal(row))"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
                <p x-show="items.some(r => r.sale_unit === 'KG')" x-cloak class="text-xs text-gray-400 mt-1">
                    Para ítems por KG, ingrese gramos según la báscula. Ej: 60000 = 60 kg.
                </p>
                <p x-show="items.length === 0" class="text-xs text-gray-400">Sin ítems: ingrese el total directamente abajo.</p>
            </div>

            {{-- Total + notes --}}
            <div class="grid sm:grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Total *</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-semibold">$</span>
                        <input type="number" step="1" min="1"
                               :value="items.length > 0 ? totalFromItems : totalInput"
                               @input="totalInput = $event.target.value"
                               :readonly="items.length > 0" data-keyboard="numeric"
                               class="form-input pl-7 font-semibold" :class="items.length > 0 ? 'bg-gray-100' : ''">
                    </div>
                    <p class="text-xs text-gray-400 mt-1" x-show="items.length > 0">Calculado a partir de los ítems.</p>
                    <p x-show="fieldErrors.total_amount" x-cloak class="text-red-500 text-xs mt-1" x-text="fieldErrors.total_amount"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                    <input type="text" x-model="notes" data-keyboard="text" class="form-input">
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex flex-wrap gap-2 justify-end pt-2 border-t">
                <button type="button" @click="close()" class="pos-btn pos-btn-secondary">Cancelar</button>
                <button type="button" @click="save(false)" :disabled="submitting"
                        class="pos-btn pos-btn-success disabled:opacity-50">
                    <span x-show="!submitting">Guardar</span>
                    <span x-show="submitting" x-cloak>Guardando…</span>
                </button>
                <button type="button" @click="save(true)" :disabled="submitting"
                        class="pos-btn-primary disabled:opacity-50">
                    Guardar y registrar pago
                </button>
            </div>
        </div>

        {{-- ── STAGE: payment ────────────────────────────────────────────── --}}
        <div x-show="stage === 'payment'" x-cloak class="px-5 pt-5 pb-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-gray-800">Registrar pago</h2>
                <button type="button" @click="close()" class="pos-btn-icon">&times;</button>
            </div>

            <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded px-3 py-2 mb-4">
                Factura registrada por <span class="font-semibold" x-text="fmt(createdInvoice?.total_amount ?? 0)"></span>.
            </div>

            <div x-show="errorMsg" x-cloak
                 class="mb-3 p-2 bg-red-50 border border-red-200 text-red-700 text-sm rounded"
                 x-text="errorMsg"></div>

            {{-- Payment mode --}}
            <div class="flex gap-2 mb-4">
                <button type="button" @click="payMode = 'invoice'"
                        :class="payMode === 'invoice' ? 'pos-btn-primary' : 'pos-btn pos-btn-secondary'" class="text-sm">
                    Pagar esta factura
                </button>
                <button type="button" @click="payMode = 'supplier'"
                        :class="payMode === 'supplier' ? 'pos-btn-primary' : 'pos-btn pos-btn-secondary'" class="text-sm">
                    Pago al proveedor (FIFO)
                </button>
            </div>
            <p class="text-xs text-gray-400 mb-3"
               x-text="payMode === 'invoice'
                   ? 'Se aplica únicamente a esta factura.'
                   : 'Se distribuye automáticamente entre las facturas más antiguas del proveedor.'"></p>

            {{-- Method chips --}}
            <div class="mb-4">
                <label class="block text-xs font-semibold text-gray-600 mb-2">Método de pago</label>
                <div class="flex flex-wrap gap-2">
                    @foreach($supMethods as $key => $label)
                        <button type="button" @click="payMethod = '{{ $key }}'"
                                class="px-3 py-1.5 rounded-full text-sm font-semibold border transition-colors"
                                :class="payMethod === '{{ $key }}' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-300'">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Amount + notes --}}
            <div class="grid sm:grid-cols-2 gap-3 mb-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Monto *</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-semibold">$</span>
                        <input type="number" step="1" min="1" x-model.number="payAmount" data-keyboard="numeric"
                               class="form-input pl-7 text-lg font-semibold" placeholder="0">
                    </div>
                    <p x-show="payMode === 'invoice' && createdInvoice"
                       class="text-xs text-gray-400 mt-1"
                       x-text="'Saldo de la factura: ' + fmt(createdInvoice?.balance ?? 0)"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                    <input type="text" x-model="payNotes" data-keyboard="text" class="form-input">
                </div>
            </div>

            <div class="flex flex-wrap gap-2 justify-end pt-2 border-t">
                <button type="button" @click="finishWithoutPayment()" class="pos-btn pos-btn-secondary">Omitir pago</button>
                <button type="button" @click="submitPayment()" :disabled="submitting || !payValid"
                        class="pos-btn pos-btn-success disabled:opacity-50">
                    <span x-show="!submitting">Registrar pago</span>
                    <span x-show="submitting" x-cloak>Procesando…</span>
                </button>
            </div>
        </div>

    </div>{{-- /panel --}}
</div>{{-- /modal --}}

<script>
const __siCsrf = '{{ csrf_token() }}';

function supplierInvoiceModal() {
    return {
        show: false,
        stage: 'form',
        submitting: false,
        errorMsg: '',
        fieldErrors: {},

        // context
        supplierId: '',
        supplierName: '',
        supplierLocked: false,
        suppliers: [],

        // invoice form
        invoiceNumber: '',
        invoiceDate: '',
        dueDate: '',
        notes: '',
        items: [],
        totalInput: '',

        // payment stage
        createdInvoice: null,
        payMode: 'invoice',
        payMethod: 'CASH',
        payAmount: '',
        payNotes: '',
        paySubmissionKey: '',

        // ── computed ──────────────────────────────────────────────────────
        get totalFromItems() {
            return this.items.reduce((sum, r) => sum + this.lineTotal(r), 0);
        },
        get payValid() {
            return (parseInt(this.payAmount) || 0) >= 1;
        },

        // ── helpers ───────────────────────────────────────────────────────
        fmt(v) { return '$' + Math.round(parseFloat(v) || 0).toLocaleString('es-CO'); },
        // row.quantity holds kg (for KG) or units (for UNIT) — see partials/_kg-unit-qty
        lineTotal(row) {
            let qty = parseFloat(row.quantity) || 0;
            if (row.sale_unit !== 'KG') qty = Math.round(qty);
            return Math.round(qty * (parseFloat(row.unit_price) || 0));
        },
        addRow() {
            this.items.push({ description: '', sale_unit: 'UNIT', quantity: '', gramsDisplay: '', unit_price: '' });
        },
        removeRow(idx) { this.items.splice(idx, 1); },

        // ── lifecycle ─────────────────────────────────────────────────────
        open(detail) {
            this.reset();
            if (detail && detail.supplierId) {
                this.supplierId     = detail.supplierId;
                this.supplierName   = detail.supplierName || '';
                this.supplierLocked = !!detail.locked;
            }
            if (!this.supplierLocked) this.loadSuppliers();
            this.show = true;
        },
        close() {
            this.show = false;
            this.$nextTick(() => this.reset());
        },
        reset() {
            this.stage = 'form';
            this.submitting = false;
            this.errorMsg = '';
            this.fieldErrors = {};
            this.supplierId = '';
            this.supplierName = '';
            this.supplierLocked = false;
            this.invoiceNumber = '';
            this.invoiceDate = new Date().toLocaleDateString('en-CA'); // YYYY-MM-DD (local)
            this.dueDate = '';
            this.notes = '';
            this.items = [];
            this.totalInput = '';
            this.createdInvoice = null;
            this.payMode = 'invoice';
            this.payMethod = 'CASH';
            this.payAmount = '';
            this.payNotes = '';
            this.paySubmissionKey = this.uuid();
            this.addRow();
        },
        uuid() {
            return (typeof crypto !== 'undefined' && crypto.randomUUID)
                ? crypto.randomUUID()
                : Math.random().toString(36).slice(2);
        },

        async loadSuppliers() {
            try {
                const res = await fetch('/suppliers', { headers: { 'Accept': 'application/json' } });
                if (res.ok) this.suppliers = await res.json();
            } catch (_) { /* leave empty; user sees no options */ }
        },

        // ── build payload ─────────────────────────────────────────────────
        itemsPayload() {
            return this.items
                .filter(it => (it.description || '').trim() !== '')
                .map(it => ({
                    description: it.description,
                    sale_unit:  it.sale_unit,
                    quantity:   it.sale_unit === 'KG'
                                  ? (parseFloat(it.quantity) || 0)
                                  : Math.round(parseFloat(it.quantity) || 0),
                    unit_price: Math.round(parseFloat(it.unit_price) || 0),
                }));
        },

        // ── save invoice ──────────────────────────────────────────────────
        async save(thenPay) {
            if (this.submitting) return;
            this.errorMsg = '';
            this.fieldErrors = {};

            if (!this.supplierId) {
                this.fieldErrors.supplier = 'Seleccione un proveedor.';
                return;
            }

            this.submitting = true;
            const items = this.itemsPayload();
            const body = {
                invoice_number: this.invoiceNumber || null,
                invoice_date:   this.invoiceDate || null,
                due_date:       this.dueDate || null,
                notes:          this.notes || null,
                items:          items.length ? items : null,
                total_amount:   items.length ? null : (parseInt(this.totalInput) || null),
            };

            try {
                const res = await fetch(`/suppliers/${this.supplierId}/invoices`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': __siCsrf,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(body),
                });
                const data = await res.json().catch(() => ({}));

                if (res.status === 422) {
                    this.applyErrors(data);
                    this.submitting = false;
                    return;
                }
                if (!res.ok) {
                    this.errorMsg = data.message || 'Error al guardar la factura.';
                    this.submitting = false;
                    return;
                }

                this.createdInvoice = data.invoice;
                this.submitting = false;

                if (thenPay) {
                    this.stage = 'payment';
                    this.payAmount = '';
                } else {
                    this.redirectToSupplier();
                }
            } catch (_) {
                this.errorMsg = 'Error de red. Intente de nuevo.';
                this.submitting = false;
            }
        },

        applyErrors(data) {
            this.errorMsg = data.message || 'Revise los campos marcados.';
            const errs = data.errors || {};
            const map = {};
            for (const key in errs) {
                const msg = Array.isArray(errs[key]) ? errs[key][0] : errs[key];
                if (key.startsWith('items')) { this.errorMsg = msg; }
                map[key] = msg;
            }
            this.fieldErrors = map;
        },

        // ── payment ───────────────────────────────────────────────────────
        async submitPayment() {
            if (this.submitting || !this.payValid || !this.createdInvoice) return;
            this.submitting = true;
            this.errorMsg = '';

            const url = this.payMode === 'invoice'
                ? `/supplier-invoices/${this.createdInvoice.id}/payments`
                : `/suppliers/${this.createdInvoice.supplier_id}/payments`;

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': __siCsrf,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        method: this.payMethod,
                        amount: parseInt(this.payAmount) || 0,
                        notes: this.payNotes || null,
                        submission_key: this.paySubmissionKey,
                    }),
                });
                const data = await res.json().catch(() => ({}));

                if (!res.ok) {
                    this.errorMsg = data.message || 'Error al registrar el pago.';
                    this.submitting = false;
                    return;
                }
                this.redirectToSupplier();
            } catch (_) {
                this.errorMsg = 'Error de red. Intente de nuevo.';
                this.submitting = false;
            }
        },

        finishWithoutPayment() {
            this.redirectToSupplier();
        },

        redirectToSupplier() {
            const id = this.createdInvoice?.supplier_id || this.supplierId;
            window.location = '/suppliers/' + id;
        },
    };
}
</script>
