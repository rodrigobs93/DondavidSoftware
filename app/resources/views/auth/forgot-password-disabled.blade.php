<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperación deshabilitada</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 min-h-screen flex items-center justify-center">
<div class="w-full max-w-sm text-center">
    <div class="text-5xl mb-3">🚫</div>
    <h1 class="text-white text-2xl font-bold mb-2">Recuperación deshabilitada</h1>
    <p class="text-gray-400 text-sm mb-6">
        El administrador no ha configurado <code class="bg-gray-800 px-1 rounded">MASTER_RESET_PASSWORD</code> en el archivo <code class="bg-gray-800 px-1 rounded">.env</code>.
    </p>
    <a href="{{ route('login') }}" class="text-blue-400 hover:underline text-sm">Volver al inicio de sesión</a>
</div>
</body>
</html>
