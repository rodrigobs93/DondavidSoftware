<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureLanAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->isCashier()) {
            $ip = $request->ip();
            if (!$this->isPrivateIp($ip)) {
                abort(403, 'Acceso restringido: el cajero solo puede acceder desde la red local.');
            }
        }

        return $next($request);
    }

    private function isPrivateIp(string $ip): bool
    {
        // Allow loopback
        if (in_array($ip, ['127.0.0.1', '::1'])) {
            return true;
        }

        // filter_var returns false for private/reserved ranges — that means it IS private
        $isPublic = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        return $isPublic === false;
    }
}
