<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;

class EnsureSuppliersEnabled
{
    public function handle(Request $request, Closure $next)
    {
        if (Setting::get('module_suppliers_enabled', '0') !== '1') {
            abort(403, 'El módulo de Proveedores no está habilitado.');
        }

        return $next($request);
    }
}
