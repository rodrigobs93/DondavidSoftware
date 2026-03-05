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
        .pos-btn { @apply inline-flex items-center px-4 py-2 rounded font-semibold text-sm transition-colors; }
        .pos-btn-primary { @apply pos-btn bg-blue-600 text-white hover:bg-blue-700; }
        .pos-btn-success { @apply pos-btn bg-green-600 text-white hover:bg-green-700; }
        .pos-btn-danger  { @apply pos-btn bg-red-600 text-white hover:bg-red-700; }
        .pos-btn-secondary { @apply pos-btn bg-gray-200 text-gray-700 hover:bg-gray-300; }
        .form-input { @apply block w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm; }
        .badge-paid    { @apply px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800; }
        .badge-partial { @apply px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800; }
        .badge-pending { @apply px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">

{{-- Top nav --}}
<nav class="bg-gray-900 text-white shadow" x-data>
    <div class="max-w-7xl mx-auto px-4 flex items-center justify-between h-14">
        <a href="{{ route('dashboard') }}" class="font-bold text-lg tracking-wide">🥩 Don David POS</a>
        <div class="flex items-center gap-4 text-sm">
            @auth
                <a href="{{ route('sales.create') }}" class="text-green-400 hover:text-green-300 font-semibold">+ Venta</a>
                <a href="#" @click.prevent="$dispatch('open-quick-sale')"
                   class="text-yellow-400 hover:text-yellow-300 font-semibold">⚡ Rápida</a>
                <a href="{{ route('invoices.index') }}" class="hover:text-gray-300">Facturas</a>
                <a href="{{ route('cartera.index') }}" class="hover:text-gray-300">Cartera</a>
                <a href="{{ route('fe-pending.index') }}" class="hover:text-gray-300">FE</a>
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('products.index') }}" class="hover:text-gray-300">Productos</a>
                    <a href="{{ route('customers.index') }}" class="hover:text-gray-300">Clientes</a>
                    <a href="{{ route('reports.payments') }}" class="hover:text-gray-300">Validación</a>
                    <a href="{{ route('backups.index') }}" class="hover:text-gray-300">Config</a>
                @endif
                <span class="text-gray-400">|</span>
                <span class="text-gray-300">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button class="text-gray-400 hover:text-white text-xs">Salir</button>
                </form>
            @endauth
        </div>
    </div>
</nav>

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
</body>
</html>
