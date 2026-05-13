<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar contraseña — {{ \App\Models\Setting::get('shop_name', 'Mi Negocio') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 min-h-screen flex items-center justify-center">
<div class="w-full max-w-sm">
    <div class="text-center mb-8">
        <div class="text-5xl mb-3">🔑</div>
        <h1 class="text-white text-2xl font-bold">Recuperar contraseña</h1>
        <p class="text-gray-400 text-sm mt-1">Solo cuenta de administrador</p>
    </div>

    <div class="bg-white rounded-lg shadow-xl p-8">
        @if ($errors->any())
            <div class="mb-4 bg-red-50 border border-red-300 text-red-800 text-sm rounded p-3">
                Revisa los campos marcados.
            </div>
        @endif

        <form method="POST" action="{{ route('password.forgot') }}">
            @csrf

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña maestra</label>
                <input
                    type="password"
                    name="master_password"
                    autocomplete="off"
                    autofocus
                    class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('master_password') border-red-500 @enderror"
                    required
                >
                @error('master_password')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Correo electrónico</label>
                <input
                    type="email"
                    name="email"
                    value="{{ old('email', 'admin@minegocio.local') }}"
                    autocomplete="email"
                    class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('email') border-red-500 @enderror"
                    required
                >
                @error('email')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nueva contraseña</label>
                <input
                    type="password"
                    name="password"
                    autocomplete="new-password"
                    minlength="6"
                    class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('password') border-red-500 @enderror"
                    required
                >
                @error('password')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar nueva contraseña</label>
                <input
                    type="password"
                    name="password_confirmation"
                    autocomplete="new-password"
                    minlength="6"
                    class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    required
                >
            </div>

            <button type="submit"
                class="w-full bg-blue-600 text-white py-2 px-4 rounded font-semibold hover:bg-blue-700 transition-colors">
                Restablecer contraseña
            </button>
        </form>

        <p class="text-center text-sm text-gray-500 mt-4">
            <a href="{{ route('login') }}" class="text-blue-600 hover:underline">Volver al inicio de sesión</a>
        </p>
    </div>
</div>
</body>
</html>
