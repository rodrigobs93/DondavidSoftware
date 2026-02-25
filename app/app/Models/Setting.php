<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public $timestamps = false;

    protected $fillable = ['key', 'value', 'description'];

    const UPDATED_AT = 'updated_at';
    const CREATED_AT = null;

    public static function get(string $key, string $default = ''): string
    {
        $setting = static::where('key', $key)->first();
        return $setting ? ($setting->value ?? $default) : $default;
    }

    public static function set(string $key, string $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_at' => now()]
        );
    }

    public static function shopInfo(): array
    {
        return [
            'name'    => static::get('shop_name', 'Carnicería Don David'),
            'address' => static::get('shop_address', 'Paloquemao, Bogotá'),
            'phone'   => static::get('shop_phone', ''),
            'nit'     => static::get('shop_nit', ''),
            'footer'  => static::get('invoice_footer', '¡Gracias por su compra!'),
        ];
    }
}
