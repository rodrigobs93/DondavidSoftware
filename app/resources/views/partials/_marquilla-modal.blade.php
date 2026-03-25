{{-- Marquilla Modal — triggered by 'open-marquillas' custom event from nav --}}
<div x-data="marquillaModal()" x-cloak
     @open-marquillas.window="open()"
     @keydown.escape.window="close()"
     x-show="show"
     class="fixed inset-0 z-50 flex items-center justify-center p-4">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/50" @click="close()"></div>

    {{-- Panel --}}
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md z-10" @click.stop>

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 pt-5 pb-3 border-b border-gray-100">
            <h2 class="text-lg font-bold text-gray-800">🏷 Imprimir Marquillas</h2>
            <button type="button" @click="close()"
                    class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <div class="px-5 py-4 space-y-4">

            {{-- Product search + add custom --}}
            <div class="flex gap-2">
                <div class="relative flex-1">
                    <input type="text"
                           x-model="productSearch"
                           @input="searchProducts()"
                           @keydown.escape="productResults = []"
                           placeholder="Buscar producto..."
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                    {{-- Dropdown --}}
                    <div x-show="productResults.length > 0"
                         @click.away="productResults = []"
                         class="absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-20 max-h-48 overflow-y-auto">
                        <template x-for="p in productResults" :key="p.id">
                            <button type="button"
                                    @click="selectProduct(p)"
                                    class="w-full text-left px-3 py-2 text-sm hover:bg-indigo-50 border-b border-gray-100 last:border-0"
                                    x-text="p.name"></button>
                        </template>
                    </div>
                    <div x-show="searching" class="absolute right-3 top-2.5 text-gray-400 text-xs">…</div>
                </div>
                <button type="button"
                        @click="addCustomLine()"
                        class="shrink-0 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium px-3 py-2 rounded-lg transition">
                    + Personalizada
                </button>
            </div>

            {{-- Lines list --}}
            <div class="space-y-2">
                {{-- Empty state --}}
                <div x-show="lines.length === 0"
                     class="text-center text-gray-400 text-sm py-4 border-2 border-dashed border-gray-200 rounded-lg">
                    Agrega marquillas usando la búsqueda o el botón "Personalizada"
                </div>

                {{-- Column headers --}}
                <div x-show="lines.length > 0"
                     class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase px-1">
                    <span class="flex-1">Texto de la marquilla</span>
                    <span class="w-20 text-center">Copias</span>
                    <span class="w-6"></span>
                </div>

                <template x-for="(line, idx) in lines" :key="idx">
                    <div class="flex items-center gap-2">
                        <input type="text"
                               x-model="line.text"
                               maxlength="100"
                               placeholder="Texto..."
                               class="flex-1 border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        <input type="number"
                               x-model.number="line.copies"
                               min="1" max="20"
                               class="w-20 border border-gray-300 rounded-lg px-2 py-1.5 text-sm text-center focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        <button type="button"
                                @click="removeLine(idx)"
                                class="w-6 text-gray-400 hover:text-red-500 transition text-lg leading-none">&times;</button>
                    </div>
                </template>
            </div>

            {{-- Total copies summary --}}
            <div x-show="lines.length > 0"
                 class="text-right text-xs text-gray-500"
                 x-text="'Total: ' + totalCopies() + ' marquilla(s)'"></div>

            {{-- Feedback messages --}}
            <div x-show="errorMsg" x-cloak
                 class="p-2 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg"
                 x-text="errorMsg"></div>
            <div x-show="successMsg" x-cloak
                 class="p-2 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg"
                 x-text="successMsg"></div>

            {{-- Actions --}}
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" @click="close()"
                        class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">
                    Cerrar
                </button>
                <button type="button"
                        @click="printLabels()"
                        :disabled="submitting || lines.length === 0 || !hasValidLines()"
                        class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold rounded-lg transition"
                        x-text="submitting ? 'Imprimiendo…' : 'Imprimir (' + totalCopies() + ')'">
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function marquillaModal() {
    return {
        show:           false,
        lines:          [],
        productSearch:  '',
        productResults: [],
        searching:      false,
        submitting:     false,
        successMsg:     '',
        errorMsg:       '',
        _searchTimer:   null,

        open() {
            this.show = true;
            this.reset();
        },

        close() {
            this.show = false;
        },

        reset() {
            this.lines          = [];
            this.productSearch  = '';
            this.productResults = [];
            this.successMsg     = '';
            this.errorMsg       = '';
            this.submitting     = false;
            clearTimeout(this._searchTimer);
        },

        searchProducts() {
            clearTimeout(this._searchTimer);
            const q = this.productSearch.trim();
            if (!q) { this.productResults = []; return; }
            this._searchTimer = setTimeout(async () => {
                this.searching = true;
                try {
                    const res = await fetch('/products/search?q=' + encodeURIComponent(q));
                    this.productResults = await res.json();
                } catch (e) {
                    this.productResults = [];
                } finally {
                    this.searching = false;
                }
            }, 250);
        },

        selectProduct(product) {
            this.lines.push({ text: product.name, copies: 1 });
            this.productSearch  = '';
            this.productResults = [];
            this.errorMsg       = '';
            this.successMsg     = '';
        },

        addCustomLine() {
            this.lines.push({ text: '', copies: 1 });
            this.errorMsg   = '';
            this.successMsg = '';
        },

        removeLine(idx) {
            this.lines.splice(idx, 1);
        },

        totalCopies() {
            return this.lines.reduce((sum, l) => sum + (parseInt(l.copies) || 0), 0);
        },

        hasValidLines() {
            return this.lines.some(l => l.text.trim() && l.copies >= 1);
        },

        async printLabels() {
            this.errorMsg   = '';
            this.successMsg = '';

            const valid = this.lines
                .filter(l => l.text.trim() && l.copies >= 1)
                .map(l => ({ text: l.text.trim(), copies: parseInt(l.copies) }));

            if (!valid.length) {
                this.errorMsg = 'Agrega al menos una marquilla con texto.';
                return;
            }

            this.submitting = true;
            try {
                const res = await fetch('/marquillas/print', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ lines: valid }),
                });
                const data = await res.json();
                if (data.ok) {
                    this.close();
                } else {
                    this.errorMsg = data.error || 'Error al imprimir.';
                }
            } catch (e) {
                this.errorMsg = 'Error de conexión. Verifica la impresora.';
            } finally {
                this.submitting = false;
            }
        },
    };
}
</script>
