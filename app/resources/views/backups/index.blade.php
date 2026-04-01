@extends('layouts.app')
@section('title', 'Configuración y Backups')

@php
$headerColors = [
    '#111827' => 'Gris oscuro',
    '#0f172a' => 'Negro azulado',
    '#1e3a5f' => 'Azul marino',
    '#1e1b4b' => 'Índigo',
    '#4c1d95' => 'Morado',
    '#7f1d1d' => 'Vinotinto',
    '#7c2d12' => 'Naranja oscuro',
    '#134e4a' => 'Verde teal',
    '#14532d' => 'Verde selva',
    '#1c1917' => 'Café oscuro',
];
$currentHeaderColor = $settings['header_color'] ?? '#111827';
@endphp

@section('content')
<h1 class="text-xl font-bold text-gray-800 mb-4">Configuración y Backups</h1>

{{-- Logo upload --}}
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h2 class="font-semibold text-gray-700 mb-4">Logo del negocio</h2>
    @if($settings['business_logo_path'] ?? '')
        <div class="flex items-center gap-4 mb-4">
            <img src="{{ \Illuminate\Support\Facades\Storage::url($settings['business_logo_path']) }}"
                 class="h-16 w-auto rounded border border-gray-200" alt="Logo actual">
            <form method="POST" action="{{ route('backups.logo.delete') }}">
                @csrf
                @method('DELETE')
                <button type="button"
                        onclick="if(confirm('¿Eliminar el logo?')) this.closest('form').submit()"
                        class="pos-btn pos-btn-danger text-xs">
                    Eliminar logo
                </button>
            </form>
        </div>
    @else
        <p class="text-sm text-gray-400 mb-3">No hay logo configurado.</p>
    @endif
    <form method="POST" action="{{ route('backups.logo.upload') }}" enctype="multipart/form-data">
        @csrf
        <div class="flex gap-3 items-end flex-wrap">
            <div>
                <label class="block text-xs text-gray-600 mb-1">
                    Subir {{ ($settings['business_logo_path'] ?? '') ? 'nuevo' : '' }} logo
                    <span class="text-gray-400">(JPG/PNG/WEBP/SVG, máx. 2 MB)</span>
                </label>
                <input type="file" name="logo" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml,.svg"
                       class="text-sm border rounded px-2 py-1.5 w-full">
            </div>
            <button type="submit" class="pos-btn pos-btn-primary text-sm">Subir logo</button>
        </div>
        @error('logo')
            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </form>
</div>

<div class="grid md:grid-cols-2 gap-6">
    {{-- Shop settings --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="font-semibold text-gray-700 mb-4">Datos del Negocio</h2>
        <form method="POST" action="{{ route('backups.settings') }}">
            @csrf
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del negocio</label>
                    <input type="text" name="shop_name" value="{{ $settings['shop_name'] ?? '' }}"
                        class="w-full border rounded px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
                    <input type="text" name="shop_address" value="{{ $settings['shop_address'] ?? '' }}"
                        class="w-full border rounded px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                    <input type="text" name="shop_phone" value="{{ $settings['shop_phone'] ?? '' }}"
                        class="w-full border rounded px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">NIT</label>
                    <input type="text" name="shop_nit" value="{{ $settings['shop_nit'] ?? '' }}"
                        class="w-full border rounded px-3 py-2 text-sm" placeholder="900.XXX.XXX-X">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pie de página del tiquete</label>
                    <input type="text" name="invoice_footer" value="{{ $settings['invoice_footer'] ?? '' }}"
                        class="w-full border rounded px-3 py-2 text-sm">
                </div>
            </div>

            {{-- Header color --}}
            <div class="mt-4" x-data="{ picked: '{{ $currentHeaderColor }}' }">
                <label class="block text-sm font-medium text-gray-700 mb-2">Color del header</label>
                <div class="flex flex-wrap gap-2">
                    @foreach($headerColors as $hex => $label)
                    <label title="{{ $label }}" class="cursor-pointer">
                        <input type="radio" name="header_color" value="{{ $hex }}" class="sr-only"
                               {{ $currentHeaderColor === $hex ? 'checked' : '' }}
                               @change="picked = '{{ $hex }}'; document.querySelector('nav').style.backgroundColor = '{{ $hex }}'">
                        <span class="block w-8 h-8 rounded-full border-2 transition-all duration-150"
                              style="background-color: {{ $hex }}"
                              :class="picked === '{{ $hex }}' ? 'border-blue-500 ring-2 ring-blue-300 scale-110' : 'border-gray-300'">
                        </span>
                    </label>
                    @endforeach
                </div>
                <p class="text-xs text-gray-400 mt-1">
                    {{ $headerColors[$currentHeaderColor] ?? $currentHeaderColor }}
                    <span x-show="picked !== '{{ $currentHeaderColor }}'" x-cloak
                          class="text-blue-500"> → seleccionado: <span x-text="picked"></span></span>
                </p>
                @error('header_color')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
            <button class="mt-4 pos-btn-primary w-full">Guardar configuración</button>
        </form>
    </div>

    {{-- System settings --}}
    <div class="space-y-4">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="font-semibold text-gray-700 mb-4">Configuración del Sistema</h2>
            <form method="POST" action="{{ route('backups.settings') }}">
                @csrf
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">IP LAN del PC POS</label>
                        <input type="text" name="lan_ip" value="{{ $settings['lan_ip'] ?? '' }}"
                            class="w-full border rounded px-3 py-2 text-sm font-mono" placeholder="192.168.1.100">
                        <p class="text-xs text-gray-400 mt-1">Se muestra en el Dashboard para acceso desde celular.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre de impresora Windows</label>
                        <input type="text" name="thermal_printer_name"
                               value="{{ $settings['thermal_printer_name'] ?? 'XP-80C' }}"
                               class="w-full border rounded px-3 py-2 text-sm font-mono"
                               placeholder="XP-80C">
                        <p class="text-xs text-gray-400 mt-1">
                            Nombre exacto como aparece en "Dispositivos e impresoras" de Windows.
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ruta backup (OneDrive)</label>
                        <input type="text" name="backup_path" value="{{ $settings['backup_path'] ?? '' }}"
                            class="w-full border rounded px-3 py-2 text-sm font-mono"
                            placeholder="C:\Users\...\OneDrive\DonDavidBackups">
                        <p class="text-xs text-gray-400 mt-1">Carpeta local donde se copian los backups automáticamente.</p>
                    </div>
                </div>
                <button class="mt-4 pos-btn-secondary w-full">Guardar sistema</button>
            </form>

            {{-- Test Print --}}
            <div x-data="{ loading: false, msg: '', ok: null }" class="mt-3 px-1">
                <button type="button"
                        @click="loading=true; msg='';
                            fetch('{{ route('backups.test-print') }}', {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Accept': 'application/json'
                                }
                            })
                            .then(r => r.json())
                            .then(d => { ok = d.ok; msg = d.ok ? d.message : d.error; })
                            .catch(() => { ok = false; msg = 'Error de red.'; })
                            .finally(() => loading = false)"
                        :disabled="loading"
                        class="pos-btn-secondary w-full disabled:opacity-50">
                    <span x-show="!loading">🖨 Imprimir ticket de prueba</span>
                    <span x-show="loading" x-cloak>Enviando…</span>
                </button>
                <p x-show="msg" x-cloak
                   class="mt-2 text-xs text-center"
                   :class="ok ? 'text-green-600' : 'text-red-600'"
                   x-text="msg"></p>
            </div>
        </div>

        {{-- Touch mode --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="font-semibold text-gray-700 mb-4">Modo Táctil</h2>
            <form method="POST" action="{{ route('backups.settings') }}">
                @csrf
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="hidden" name="touch_mode" value="0">
                    <input type="checkbox" name="touch_mode" value="1"
                           class="w-5 h-5 rounded"
                           {{ $touchMode ? 'checked' : '' }}>
                    <span class="text-sm text-gray-600">
                        Mostrar teclado en pantalla al tocar un campo
                    </span>
                </label>
                <p class="text-xs text-gray-400 mt-1 ml-8">
                    Útil en terminales táctiles sin teclado físico (Windows/macOS/Linux).
                </p>
                <button type="submit" class="mt-3 pos-btn pos-btn-secondary text-sm">
                    Guardar
                </button>
            </form>
        </div>

        {{-- Backup export --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="font-semibold text-gray-700 mb-2">Exportar Backup</h2>
            <p class="text-sm text-gray-500 mb-4">
                Genera un archivo SQL con todos los datos. Si hay ruta configurada, también lo copia allí.
            </p>
            <form method="POST" action="{{ route('backups.export') }}">
                @csrf
                <button class="pos-btn-primary w-full">⬇ Descargar Backup SQL</button>
                @error('backup') <p class="text-red-500 text-xs mt-2">{{ $message }}</p> @enderror
            </form>
        </div>
    </div>
</div>
@endsection
