<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PasswordRecoveryController extends Controller
{
    public function showForm()
    {
        if (!$this->masterConfigured()) {
            return response()->view('auth.forgot-password-disabled', [], 503);
        }
        return view('auth.forgot-password');
    }

    public function reset(Request $request)
    {
        if (!$this->masterConfigured()) {
            return response()->view('auth.forgot-password-disabled', [], 503);
        }

        $data = $request->validate([
            'master_password' => ['required', 'string'],
            'email'           => ['required', 'email'],
            'password'        => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $configured = (string) config('auth_recovery.master_password');
        if (!hash_equals($configured, $data['master_password'])) {
            return back()
                ->withErrors(['master_password' => 'Contraseña maestra incorrecta.'])
                ->onlyInput('email');
        }

        $user = User::where('email', $data['email'])
            ->where('role', 'admin')
            ->first();

        if (!$user) {
            return back()
                ->withErrors(['email' => 'Cuenta no encontrada o no es administrador.'])
                ->onlyInput('email');
        }

        $user->update([
            'password' => Hash::make($data['password']),
            'active'   => true,
        ]);

        return redirect()->route('login')->with(
            'status',
            'Contraseña actualizada. Inicia sesión con la nueva contraseña.'
        );
    }

    private function masterConfigured(): bool
    {
        $value = config('auth_recovery.master_password');
        return is_string($value) && $value !== '';
    }
}
