<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::updateOrCreate(
            ['key' => 'module_suppliers_enabled'],
            ['value' => '0', 'description' => 'Habilita el módulo de Proveedores / Cuentas por Pagar'],
        );
    }

    public function down(): void
    {
        Setting::where('key', 'module_suppliers_enabled')->delete();
    }
};
