{{-- Quick Sale Modal — triggered by 'open-quick-sale' custom event from nav --}}
<div x-data="quickSaleModal()" x-cloak
     @open-quick-sale.window="open()"
     @keydown.escape.window="close()"
     x-show="show"
     class="fixed inset-0 z-50 flex items-center justify-center p-4">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/50" @click="close()"></div>

    {{-- Panel --}}
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-sm z-10"
         @click.stop>

        {{-- ── STAGE: form ── --}}
        <div x-show="stage === 'form'">
            <div class="px-5 pt-5 pb-4">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-gray-800">⚡ Venta Rápida</h2>
                    <button type="button" @click="close()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
                </div>

                {{-- Error banner --}}
                <div x-show="errorMsg" x-cloak
                     class="mb-3 p-2 bg-red-50 border border-red-200 text-red-700 text-sm rounded"
                     x-text="errorMsg"></div>

                {{-- Total --}}
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Total venta</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-semibold">$</span>
                        <input type="number" inputmode="numeric" min="1" step="1"
                               x-model.number="total"
                               x-ref="totalInput"
                               @keydown.enter="method ? $refs.finishBtn.click() : null"
                               class="form-input pl-7 text-xl font-semibold"
                               placeholder="0">
                    </div>
                </div>

                {{-- Payment method chips --}}
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-gray-600 mb-2">Método de pago</label>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="chip in methodChips" :key="chip.value">
                            <button type="button"
                                    @click="method = chip.value; if (method !== 'CASH') { cashReceived = ''; }"
                                    class="px-3 py-1.5 rounded-full text-xs font-semibold border transition-colors"
                                    :class="method === chip.value ? chip.active : chip.inactive">
                                <span x-text="chip.label"></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Cash received (only for CASH) --}}
                <div x-show="method === 'CASH'" x-cloak class="mb-4">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Efectivo recibido</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-semibold">$</span>
                        <input type="number" inputmode="numeric" min="0" step="1"
                               x-model.number="cashReceived"
                               class="form-input pl-7 text-lg font-semibold"
                               placeholder="0">
                    </div>
                    {{-- Change display --}}
                    <div x-show="cashReceived > 0" x-cloak class="mt-2 text-sm">
                        <span x-show="change >= 0"
                              class="font-semibold text-green-700"
                              x-text="'Cambio: ' + fmt(change)"></span>
                        <span x-show="change < 0"
                              class="font-semibold text-red-600"
                              x-text="'Faltan: ' + fmt(-change)"></span>
                    </div>
                </div>

                {{-- Notes --}}
                <div class="mb-5">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Nota (opcional)</label>
                    <input type="text" x-model="notes" maxlength="255"
                           class="form-input text-sm"
                           placeholder="Descripción breve…">
                </div>

                {{-- Finalize button --}}
                <button type="button"
                        x-ref="finishBtn"
                        @click="submit()"
                        :disabled="!canSubmit || submitting"
                        class="w-full pos-btn-primary justify-center text-base py-2.5 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-show="!submitting">Finalizar</span>
                    <span x-show="submitting" x-cloak>Procesando…</span>
                </button>
            </div>
        </div>

        {{-- ── STAGE: confirm ── --}}
        <div x-show="stage === 'confirm'">
            <div class="px-5 pt-5 pb-4 text-center">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-gray-800">Venta registrada</h2>
                    <button type="button" @click="close()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
                </div>

                {{-- Receipt number --}}
                <p class="text-4xl font-mono font-bold text-gray-900 my-4"
                   x-text="result?.receipt_number ?? ''"></p>

                {{-- Details --}}
                <div class="bg-gray-50 rounded-lg p-3 mb-4 text-sm text-left space-y-1">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Total</span>
                        <span class="font-semibold" x-text="fmt(result?.total ?? 0)"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Método</span>
                        <span class="font-semibold" x-text="result?.method_label ?? ''"></span>
                    </div>
                    <div x-show="result?.is_cash" x-cloak class="flex justify-between text-green-700">
                        <span>Cambio</span>
                        <span class="font-bold" x-text="fmt(result?.change ?? 0)"></span>
                    </div>
                </div>

                {{-- Print question --}}
                <p class="text-sm text-gray-600 mb-3">¿Desea imprimir el recibo?</p>

                <div class="flex gap-3 justify-center">
                    {{-- Default: NO print — auto-focused --}}
                    <button type="button"
                            x-ref="noPrintBtn"
                            @click="close()"
                            class="pos-btn-secondary px-6">
                        NO imprimir
                    </button>
                    {{-- SÍ print --}}
                    <button type="button"
                            @click="printReceipt()"
                            :disabled="printLoading"
                            class="pos-btn-primary px-6 disabled:opacity-50">
                        <span x-show="!printLoading">SÍ imprimir</span>
                        <span x-show="printLoading" x-cloak>Enviando…</span>
                    </button>
                </div>
            </div>
        </div>

    </div>{{-- /panel --}}
</div>{{-- /modal --}}

<script>
const __qsCsrf = '{{ csrf_token() }}';

function quickSaleModal() {
    return {
        show:        false,
        stage:       'form',   // 'form' | 'confirm'
        submitting:  false,
        printLoading: false,
        // form state
        total:        '',
        method:       '',
        cashReceived: '',
        notes:        '',
        submissionKey: '',
        errorMsg:     '',
        // result
        result: null,

        methodChips: [
            { value: 'CASH',      label: 'Efectivo',  active: 'bg-green-600 text-white border-green-600',   inactive: 'bg-white text-gray-600 border-gray-300 hover:border-green-400' },
            { value: 'CARD',      label: 'Tarjeta',   active: 'bg-blue-600 text-white border-blue-600',     inactive: 'bg-white text-gray-600 border-gray-300 hover:border-blue-400' },
            { value: 'NEQUI',     label: 'Nequi',     active: 'bg-pink-600 text-white border-pink-600',     inactive: 'bg-white text-gray-600 border-gray-300 hover:border-pink-400' },
            { value: 'DAVIPLATA', label: 'Daviplata', active: 'bg-red-600 text-white border-red-600',       inactive: 'bg-white text-gray-600 border-gray-300 hover:border-red-400' },
            { value: 'BREB',      label: 'Bre-B',     active: 'bg-purple-600 text-white border-purple-600', inactive: 'bg-white text-gray-600 border-gray-300 hover:border-purple-400' },
        ],

        get change() {
            return parseFloat(this.cashReceived || 0) - parseFloat(this.total || 0);
        },

        get cashValid() {
            if (this.method !== 'CASH') return true;
            return parseFloat(this.cashReceived || 0) >= parseFloat(this.total || 0);
        },

        get canSubmit() {
            return parseFloat(this.total) > 0 && this.method !== '' && this.cashValid;
        },

        open() {
            this.reset();
            this.show  = true;
            this.stage = 'form';
            this.$nextTick(() => this.$refs.totalInput?.focus());
        },

        close() {
            this.show = false;
            this.$nextTick(() => this.reset());
        },

        reset() {
            this.stage        = 'form';
            this.submitting   = false;
            this.printLoading = false;
            this.total        = '';
            this.method       = '';
            this.cashReceived = '';
            this.notes        = '';
            this.errorMsg     = '';
            this.result       = null;
            this.submissionKey = (typeof crypto !== 'undefined' && crypto.randomUUID)
                ? crypto.randomUUID()
                : Math.random().toString(36).slice(2);
        },

        async submit() {
            if (!this.canSubmit || this.submitting) return;
            this.submitting = true;
            this.errorMsg   = '';

            try {
                const body = {
                    total_amount:   parseFloat(this.total),
                    payment_method: this.method,
                    notes:          this.notes || null,
                    submission_key: this.submissionKey,
                };
                if (this.method === 'CASH') {
                    body.cash_received = parseFloat(this.cashReceived);
                }

                const res = await fetch('/quick-sales', {
                    method:  'POST',
                    headers: {
                        'X-CSRF-TOKEN': __qsCsrf,
                        'Content-Type': 'application/json',
                        'Accept':       'application/json',
                    },
                    body: JSON.stringify(body),
                });

                const data = await res.json();

                if (!res.ok) {
                    this.errorMsg   = data.message ?? 'Error al registrar la venta.';
                    this.submitting = false;
                    return;
                }

                this.result = data;
                this.stage  = 'confirm';
                this.$nextTick(() => this.$refs.noPrintBtn?.focus());

            } catch (e) {
                this.errorMsg   = 'Error de red. Intente de nuevo.';
                this.submitting = false;
            }
        },

        async printReceipt() {
            if (!this.result || this.printLoading) return;
            this.printLoading = true;
            try {
                await fetch(`/quick-sales/${this.result.id}/print`, {
                    method:  'POST',
                    headers: { 'X-CSRF-TOKEN': __qsCsrf, 'Accept': 'application/json' },
                });
            } catch (_) { /* fire-and-forget */ }
            this.close();
        },

        fmt(val) {
            return '$' + parseFloat(val || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });
        },
    };
}
</script>
