@extends('layouts.app')
@section('title', 'Configuración y Backups')

@section('content')
<h1 class="text-xl font-bold text-gray-800 mb-4">Configuración y Backups</h1>

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
                        <label class="block text-sm font-medium text-gray-700 mb-1">Puerto impresora térmica</label>
                        <input type="text" name="thermal_printer_port" value="{{ $settings['thermal_printer_port'] ?? 'COM3' }}"
                            class="w-full border rounded px-3 py-2 text-sm font-mono" placeholder="COM3">
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
