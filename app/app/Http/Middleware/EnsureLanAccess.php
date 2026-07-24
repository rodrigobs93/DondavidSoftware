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

    private function isPrivateIp(?string $ip): bool
    {
        // Fail closed: anything that isn't a valid IP is not the local network.
        // Without this, filter_var below returns false for malformed input and
        // the address would be treated as private.
        if ($ip === null || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        // Allow loopback
        if (in_array($ip, ['127.0.0.1', '::1'])) {
            return true;
        }

        // Tailscale's CGNAT range — devices here are already authenticated
        // into the tailnet, so treat it as a trusted local network.
        if ($this->isInCidr($ip, '100.64.0.0/10')) {
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

    private function isInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $mask = -1 << (32 - (int) $bits);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
