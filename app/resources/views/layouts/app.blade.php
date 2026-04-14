<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Don David POS')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        [x-cloak] { display: none !important; }
        .pos-btn { @apply inline-flex items-center px-4 py-2.5 rounded font-semibold text-sm transition-colors; }
        .pos-btn-primary { @apply pos-btn bg-blue-600 text-white hover:bg-blue-700; }
        .pos-btn-success { @apply pos-btn bg-green-600 text-white hover:bg-green-700; }
        .pos-btn-danger  { @apply pos-btn bg-red-600 text-white hover:bg-red-700; }
        .pos-btn-secondary { @apply pos-btn bg-gray-200 text-gray-700 hover:bg-gray-300; }
        .form-input { @apply block w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-base py-2.5; }
        .badge-paid    { @apply px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800; }
        .badge-partial { @apply px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800; }
        .badge-pending { @apply px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800; }
        .badge-fe      { @apply px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800; }
        /* Responsive list tables */
        .pos-table { width: 100%; font-size: 0.875rem; }
        .pos-table thead th { padding: 0.5rem 0.75rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; background: #f9fafb; }
        .pos-table tbody tr { border-top: 1px solid #f3f4f6; }
        .pos-table tbody tr:hover { background: #eff6ff; }
        .pos-table tbody td { padding: 0.5rem 0.75rem; color: #1f2937; }
        /* Mobile cards */
        .pos-card { background: white; border-radius: 0.5rem; border: 1px solid #e5e7eb; padding: 0.75rem; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); }
        .pos-card-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.875rem; padding: 0.25rem 0; }
        .pos-card-label { color: #6b7280; font-size: 0.875rem; }
        .pos-card-value { color: #1f2937; font-weight: 500; }
        /* Sales screen larger font */
        .sales-screen { font-size: 1rem; }
        .sales-screen input, .sales-screen select, .sales-screen textarea { font-size: 1rem; min-height: 44px; padding-top: 0.625rem; padding-bottom: 0.625rem; }
        /* FE highlight */
        .fe-active-box { border: 1px solid #60a5fa; background: #eff6ff; border-radius: 0.5rem; padding: 0.75rem; }
    </style>
    <script>
        window.formatCOP = (val) => '$' + Math.round(parseFloat(val) || 0).toLocaleString('es-CO');
        window.formatGrams = (qty) => { const g = Math.round((parseFloat(qty) || 0) * 1000); return g > 0 ? g.toLocaleString('es-CO') + ' g' : ''; };
        window.__touchMode = {{ \App\Models\Setting::get('touch_mode', '0') === '1' ? 'true' : 'false' }};
        // Keyboard store — always registered so modals can bind to it even when touch_mode is off
        document.addEventListener('alpine:init', () => {
            Alpine.store('keyboard', { open: false, height: 0 });
        });
    </script>
    @if(\App\Models\Setting::get('touch_mode', '0') === '1')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-keyboard@latest/build/css/index.css">
    <script src="https://cdn.jsdelivr.net/npm/simple-keyboard@latest/build/index.js"></script>
    <style>
    /* ── POS Touch Keyboard — large-target theme ─────────────────────── */
    /* Keyboard wrapper: z-400 — above page content, below modals (z-1000) */
    .hg-theme-default {
        background: #1e293b;
        padding: 6px 8px 8px;
        border-radius: 0;
        font-family: inherit;
    }
    .hg-theme-default .hg-row {
        display: flex;
        gap: 5px;
        margin-bottom: 5px;
    }
    .hg-theme-default .hg-row:last-child { margin-bottom: 0; }

    /* ── Normal keys ── */
    .hg-theme-default .hg-button {
        height: 52px;
        min-width: 0;
        flex: 1;
        font-size: 1.05rem;
        font-weight: 700;
        color: #0f172a;
        background: #f1f5f9;
        border: none;
        border-radius: 7px;
        box-shadow: 0 3px 0 #94a3b8;
        transition: background 60ms, transform 60ms, box-shadow 60ms;
        touch-action: manipulation;
        user-select: none;
        -webkit-user-select: none;
    }
    .hg-theme-default .hg-button:active,
    .hg-theme-default .hg-button.hg-activeButton {
        background: #cbd5e1;
        box-shadow: 0 1px 0 #94a3b8;
        transform: translateY(2px);
    }

    /* ── Modifier keys (darker slate) ── */
    .hg-theme-default .hg-button[data-skbtn="{bksp}"],
    .hg-theme-default .hg-button[data-skbtn="{shift}"],
    .hg-theme-default .hg-button[data-skbtn="{numbers}"],
    .hg-theme-default .hg-button[data-skbtn="{abc}"] {
        background: #475569;
        color: #f1f5f9;
        box-shadow: 0 3px 0 #1e293b;
        font-size: 1.1rem;
    }
    .hg-theme-default .hg-button[data-skbtn="{bksp}"]:active,
    .hg-theme-default .hg-button[data-skbtn="{shift}"]:active,
    .hg-theme-default .hg-button[data-skbtn="{numbers}"]:active,
    .hg-theme-default .hg-button[data-skbtn="{abc}"]:active {
        background: #334155;
        box-shadow: 0 1px 0 #1e293b;
        transform: translateY(2px);
    }

    /* ── Space bar ── */
    .hg-theme-default .hg-button[data-skbtn="{space}"] {
        flex: 5;
        background: #e2e8f0;
        color: #475569;
        box-shadow: 0 3px 0 #94a3b8;
        letter-spacing: 0.05em;
    }
    .hg-theme-default .hg-button[data-skbtn="{space}"]:active {
        background: #cbd5e1;
        box-shadow: 0 1px 0 #94a3b8;
        transform: translateY(2px);
    }

    /* ── Done / Listo — green primary action ── */
    .hg-theme-default .hg-button[data-skbtn="{done}"] {
        background: #16a34a;
        color: #fff;
        font-size: 0.95rem;
        box-shadow: 0 3px 0 #15803d;
    }
    .hg-theme-default .hg-button[data-skbtn="{done}"]:active {
        background: #15803d;
        box-shadow: 0 1px 0 #166534;
        transform: translateY(2px);
    }

    /* ── Numeric numpad — taller keys ── */
    .pos-kb-numeric .hg-button {
        height: 68px;
        font-size: 1.5rem;
        font-weight: 800;
        border-radius: 9px;
    }
    .pos-kb-numeric .hg-button[data-skbtn="{bksp}"] { font-size: 1.3rem; }
    .pos-kb-numeric .hg-button[data-skbtn="{done}"] { font-size: 1rem; font-weight: 700; }

    /* ── "Listo ✓" close button at top of drawer ── */
    #kb-close-btn {
        font-size: 0.875rem;
        font-weight: 600;
        color: #94a3b8;
        padding: 0.25rem 1rem;
        border: 1px solid #334155;
        border-radius: 6px;
        background: transparent;
        line-height: 1.75rem;
    }
    #kb-close-btn:active { background: #334155; color: #f1f5f9; }
    </style>
    @endif
</head>
<body class="bg-gray-100 min-h-screen">

{{-- Top nav --}}
@php
    $__logoPath    = \App\Models\Setting::get('business_logo_path');
    $__headerColor = \App\Models\Setting::get('header_color', '#111827');
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $__headerColor)) { $__headerColor = '#111827'; }
    $__navActive = fn(string $route) => request()->routeIs($route)
        ? 'bg-white/20 font-semibold rounded px-2 py-1'
        : 'hover:bg-white/10 rounded px-2 py-1 transition';
@endphp
<div x-data="{ menuOpen: false }" class="sticky top-0 z-50">

    {{-- Backdrop (mobile/tablet only) --}}
    <div x-show="menuOpen" x-cloak
         class="fixed inset-0 bg-black/40 z-40 lg:hidden"
         @click="menuOpen = false"></div>

    <nav class="relative z-50 text-white shadow" style="background-color: {{ $__headerColor }}">
        <div class="max-w-7xl mx-auto px-4 flex items-center justify-between h-14">

            {{-- Logo / Brand --}}
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 shrink-0">
                @if($__logoPath)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($__logoPath) }}" class="h-10 w-auto rounded" alt="Logo">
                @else
                    <span class="font-bold text-lg tracking-wide">🥩 Don David POS</span>
                @endif
            </a>

            {{-- Desktop nav links (hidden below lg) --}}
            <div class="hidden lg:flex items-center gap-1 text-sm">
                @auth
                    <a href="{{ route('sales.create') }}" class="text-green-400 hover:text-green-300 font-semibold px-2 py-1 rounded hover:bg-white/10 transition">+ Venta</a>
                    <a href="#" @click.prevent="$dispatch('open-quick-sale')"
                       class="text-yellow-400 hover:text-yellow-300 font-semibold px-2 py-1 rounded hover:bg-white/10 transition">⚡ Rápida</a>
                    <a href="#" @click.prevent="$dispatch('open-marquillas')"
                       class="{{ $__navActive('') }} text-purple-300 hover:text-purple-200 font-semibold px-2 py-1 rounded hover:bg-white/10 transition">🏷 Marquillas</a>
                    <a href="{{ route('invoices.index') }}" class="{{ $__navActive('invoices.*') }}">Facturas</a>
                    <a href="{{ route('cartera.index') }}" class="{{ $__navActive('cartera.*') }}">Cartera</a>
                    <a href="{{ route('fe-pending.index') }}" class="{{ $__navActive('fe-pending.*') }}">FE</a>
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('products.index') }}" class="{{ $__navActive('products.*') }}">Productos</a>
                        <a href="{{ route('customers.index') }}" class="{{ $__navActive('customers.*') }}">Clientes</a>
                        <a href="{{ route('reports.payments') }}" class="{{ $__navActive('reports.*') }}">Validación</a>
                        <a href="{{ route('backups.index') }}" class="{{ $__navActive('backups.*') }}">Config</a>
                    @endif
                    <span class="text-white/30 mx-1">|</span>
                    <span class="text-white/70 text-xs">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}" class="inline ml-1">
                        @csrf
                        <button class="text-white/50 hover:text-white text-xs px-2 py-1 rounded hover:bg-white/10 transition">Salir</button>
                    </form>
                @endauth
            </div>

            {{-- Hamburger / Close button (visible below lg) --}}
            @auth
            <button @click="menuOpen = !menuOpen"
                    class="lg:hidden p-2 rounded hover:bg-white/10 transition"
                    aria-label="Menú">
                <svg x-show="!menuOpen" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                     stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                </svg>
                <svg x-show="menuOpen" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                     stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>
            @endauth
        </div>

        {{-- Mobile dropdown menu --}}
        <div x-show="menuOpen" x-cloak
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-1"
             @keydown.escape.window="menuOpen = false"
             class="lg:hidden absolute top-full left-0 right-0 shadow-lg pb-2"
             style="background-color: {{ $__headerColor }}">
            @auth
                <a href="{{ route('sales.create') }}" @click="menuOpen = false"
                   class="flex items-center gap-2 py-3 px-5 text-sm text-green-400 font-semibold hover:bg-white/10 transition {{ request()->routeIs('sales.create') ? 'bg-white/20' : '' }}">
                    + Venta
                </a>
                <button @click="menuOpen = false; $dispatch('open-quick-sale')"
                        class="flex items-center gap-2 py-3 px-5 text-sm text-yellow-400 font-semibold hover:bg-white/10 transition w-full text-left">
                    ⚡ Rápida
                </button>
                <button @click="menuOpen = false; $dispatch('open-marquillas')"
                        class="flex items-center gap-2 py-3 px-5 text-sm text-purple-300 font-semibold hover:bg-white/10 transition w-full text-left">
                    🏷 Marquillas
                </button>
                <a href="{{ route('invoices.index') }}" @click="menuOpen = false"
                   class="block py-3 px-5 text-sm hover:bg-white/10 transition {{ request()->routeIs('invoices.*') ? 'bg-white/20 font-semibold' : '' }}">
                    Facturas
                </a>
                <a href="{{ route('cartera.index') }}" @click="menuOpen = false"
                   class="block py-3 px-5 text-sm hover:bg-white/10 transition {{ request()->routeIs('cartera.*') ? 'bg-white/20 font-semibold' : '' }}">
                    Cartera
                </a>
                <a href="{{ route('fe-pending.index') }}" @click="menuOpen = false"
                   class="block py-3 px-5 text-sm hover:bg-white/10 transition {{ request()->routeIs('fe-pending.*') ? 'bg-white/20 font-semibold' : '' }}">
                    FE Pendiente
                </a>
                @if(auth()->user()->isAdmin())
                    <hr class="border-white/20 my-1 mx-4">
                    <a href="{{ route('products.index') }}" @click="menuOpen = false"
                       class="block py-3 px-5 text-sm hover:bg-white/10 transition {{ request()->routeIs('products.*') ? 'bg-white/20 font-semibold' : '' }}">
                        Productos
                    </a>
                    <a href="{{ route('customers.index') }}" @click="menuOpen = false"
                       class="block py-3 px-5 text-sm hover:bg-white/10 transition {{ request()->routeIs('customers.*') ? 'bg-white/20 font-semibold' : '' }}">
                        Clientes
                    </a>
                    <a href="{{ route('reports.payments') }}" @click="menuOpen = false"
                       class="block py-3 px-5 text-sm hover:bg-white/10 transition {{ request()->routeIs('reports.*') ? 'bg-white/20 font-semibold' : '' }}">
                        Validación de Pagos
                    </a>
                    <a href="{{ route('backups.index') }}" @click="menuOpen = false"
                       class="block py-3 px-5 text-sm hover:bg-white/10 transition {{ request()->routeIs('backups.*') ? 'bg-white/20 font-semibold' : '' }}">
                        Configuración
                    </a>
                @endif
                <hr class="border-white/20 my-1 mx-4">
                <div class="flex items-center justify-between px-5 py-2">
                    <span class="text-white/70 text-sm">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="text-white/60 hover:text-white text-sm px-3 py-1 rounded hover:bg-white/10 transition">
                            Salir
                        </button>
                    </form>
                </div>
            @endauth
        </div>
    </nav>
</div>

<main class="max-w-7xl mx-auto px-4 py-6">
    {{-- Flash messages --}}
    @if(session('success'))
        <div class="mb-4 p-3 bg-green-100 border border-green-300 text-green-800 rounded text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 bg-red-100 border border-red-300 text-red-800 rounded text-sm">
            {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="mb-4 p-3 bg-red-100 border border-red-300 text-red-800 rounded text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>

@include('partials._quick-sale-modal')
@include('partials._marquilla-modal')

@if(\App\Models\Setting::get('touch_mode', '0') === '1')
<div x-data="touchKeyboard()" x-show="open" x-cloak
     @mousedown.prevent.stop
     @touchstart.stop
     @pointerdown.stop
     @click.stop
     class="fixed bottom-0 left-0 right-0 bg-white shadow-2xl border-t border-gray-200 p-2"
     style="z-index:400">
    <div class="flex justify-end mb-1">
        <button id="kb-close-btn" @click.stop="close()">Listo ✓</button>
    </div>
    <div id="touch-keyboard-container" class="touch-keyboard-container"></div>
</div>

<script>
function touchKeyboard() {
    return {
        open: false,
        kb: null,
        targetEl: null,

        isEligible(el) {
            if (!el) return false;
            if (el.dataset.keyboard === 'off') return false;
            if (el.readOnly || el.disabled) return false;

            const tag = el.tagName;
            if (tag === 'TEXTAREA') return true;
            if (tag !== 'INPUT') return false;

            // Types that should NOT open a virtual keyboard
            const blocked = ['checkbox', 'radio', 'file', 'button', 'submit',
                             'reset', 'image', 'color', 'range', 'hidden',
                             'date', 'datetime-local', 'time', 'month', 'week'];
            const type = (el.type || 'text').toLowerCase();
            return !blocked.includes(type);
        },

        isNumericInput(el) {
            if (el.dataset.keyboard === 'numeric') return true;
            if ((el.getAttribute('inputmode') || '').toLowerCase() === 'numeric') return true;
            if ((el.type || '').toLowerCase() === 'number') return true;
            return false;
        },

        init() {
            document.addEventListener('focusin', (e) => {
                const el = e.target;
                if (!this.isEligible(el)) return;
                this.show(el);
            });
            document.addEventListener('focusout', () => {
                setTimeout(() => {
                    const active = document.activeElement;
                    // Keep open if focus returned to the target input or is inside the keyboard
                    if (active === this.targetEl) return;
                    if (this.$el.contains(active)) return;
                    this.close();
                }, 150);
            });
        },

        show(el) {
            // Same element already active — no re-init needed
            if (this.open && this.targetEl === el && this.kb) return;

            this.targetEl = el;
            this.open = true;
            const isNumeric = this.isNumericInput(el);
            // Heights account for: close-bar (36px) + container padding (14px) + rows
            // Numeric: 4 rows × 68px + 3 gaps × 5px = 287 + 50 = ~370
            // Text: 4 rows × 52px + 3 gaps × 5px = 223 + 50 = ~290
            const kbHeight = isNumeric ? 370 : 300;

            this.$nextTick(() => {
                // Add body padding so sticky bars aren't buried under keyboard
                document.body.style.paddingBottom = kbHeight + 'px';
                Alpine.store('keyboard').open   = true;
                Alpine.store('keyboard').height = kbHeight;

                // Scroll focused input into view.
                // Inside a modal (.fixed): use scrollIntoView — modal panel's own scroll handles it.
                // Page-level input: also nudge window so input clears the keyboard.
                el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                if (!el.closest('.fixed')) {
                    const rect = el.getBoundingClientRect();
                    const visibleBottom = window.innerHeight - kbHeight - 16;
                    if (rect.bottom > visibleBottom) {
                        window.scrollBy({ top: rect.bottom - visibleBottom, behavior: 'smooth' });
                    }
                }

                this.destroyKb();

                const opts = {
                    onChange: val => {
                        if (!this.targetEl) return;
                        this.targetEl.value = val;
                        this.targetEl.dispatchEvent(new Event('input', { bubbles: true }));
                        // Restore focus to input in case a touch tap blurred it
                        if (document.activeElement !== this.targetEl) {
                            this.targetEl.focus();
                        }
                        // Re-sync keyboard state after the browser / Alpine may have
                        // normalised the value (e.g. type="number" strips leading zeros).
                        // setInput() does NOT fire onChange, so there is no recursion.
                        requestAnimationFrame(() => {
                            if (!this.kb || !this.targetEl) return;
                            const actual = this.targetEl.value;
                            if (actual !== val) this.kb.setInput(actual);
                        });
                    },
                };

                if (isNumeric) {
                    opts.theme   = 'hg-theme-default pos-kb-numeric';
                    opts.layout  = { default: ['1 2 3', '4 5 6', '7 8 9', '{bksp} 0 {done}'] };
                    opts.display = { '{bksp}': '⌫', '{done}': 'Listo' };
                    opts.onKeyPress = key => { if (key === '{done}') setTimeout(() => this.close(), 0); };
                } else {
                    opts.theme  = 'hg-theme-default';
                    opts.layout = {
                        default: [
                            'q w e r t y u i o p {bksp}',
                            'a s d f g h j k l',
                            '{shift} z x c v b n m {shift}',
                            '{numbers} {space} {done}',
                        ],
                        shift: [
                            'Q W E R T Y U I O P {bksp}',
                            'A S D F G H J K L',
                            '{shift} Z X C V B N M {shift}',
                            '{numbers} {space} {done}',
                        ],
                        numbers: [
                            '1 2 3 4 5 6 7 8 9 0 {bksp}',
                            '- / : ; ( ) $ & @ "',
                            ". , ? ! ' {done}",
                            '{abc} {space} {done}',
                        ],
                    };
                    opts.display = {
                        '{bksp}': '⌫', '{done}': 'Listo ✓',
                        '{shift}': '⇧', '{space}': '␣',
                        '{numbers}': '123', '{abc}': 'ABC',
                    };
                    opts.onKeyPress = key => {
                        if (!this.kb) return;
                        if (key === '{done}') { setTimeout(() => this.close(), 0); return; }
                        if (key === '{shift}') {
                            const next = this.kb.options.layoutName === 'default' ? 'shift' : 'default';
                            this.kb.setOptions({ layoutName: next });
                        } else if (key === '{numbers}') {
                            this.kb.setOptions({ layoutName: 'numbers' });
                        } else if (key === '{abc}') {
                            this.kb.setOptions({ layoutName: 'default' });
                        }
                    };
                }

                try {
                    this.kb = new window.SimpleKeyboard.default('.touch-keyboard-container', opts);
                    // Numeric inputs: always start from empty so browser normalisation
                    // (e.g. stripping leading zeros on type="number") can't cause drift.
                    // Text inputs: seed with the current value so the user can continue editing.
                    this.kb.setInput(isNumeric ? '' : (el.value ?? ''));
                } catch (err) {
                    console.warn('touchKeyboard: failed to init SimpleKeyboard', err);
                    this.kb = null;
                }
            });
        },

        destroyKb() {
            if (!this.kb) return;
            try { this.kb.destroy(); } catch (e) { /* ignore */ }
            this.kb = null;
        },

        close() {
            this.open = false;
            document.body.style.paddingBottom = '';
            Alpine.store('keyboard').open   = false;
            Alpine.store('keyboard').height = 0;
            this.destroyKb();
            this.targetEl = null;
        }
    };
}
</script>
@endif
</body>
</html>
