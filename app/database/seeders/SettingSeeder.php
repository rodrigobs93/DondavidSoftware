<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'shop_name'            => ['value' => 'Carnicería Don David',        'description' => 'Nombre del negocio'],
            'shop_address'         => ['value' => 'Paloquemao, Bogotá',           'description' => 'Dirección del negocio'],
            'shop_phone'           => ['value' => '3001234567',                   'description' => 'Teléfono de contacto'],
            'shop_nit'             => ['value' => '900.XXX.XXX-X',               'description' => 'NIT del negocio'],
            'invoice_footer'       => ['value' => '¡Gracias por su compra!',      'description' => 'Pie de página del tiquete'],
            'lan_ip'               => ['value' => '192.168.1.100',               'description' => 'IP LAN del PC POS'],
            'backup_path'          => ['value' => '',                             'description' => 'Ruta local para copias de backup (ej. OneDrive)'],
            'thermal_printer_port' => ['value' => 'COM3',                         'description' => 'Puerto COM de la impresora térmica'],
        ];

        foreach ($defaults as $key => $data) {
            Setting::firstOrCreate(
                ['key' => $key],
                ['value' => $data['value'], 'description' => $data['description']]
            );
        }
    }
}
