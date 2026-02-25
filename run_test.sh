#!/bin/bash
PHP_BIN="/c/Users/rodri/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe"
export PATH="$PHP_BIN:$PATH"
cd "C:/Users/rodri/OneDrive/COLDEVS/donDavidSoftware/app"

echo "=== Route list ==="
php artisan route:list 2>&1 | head -40

echo ""
echo "=== App key check ==="
php artisan key:generate --show 2>&1

echo ""
echo "=== PHP extensions ==="
php -m 2>&1 | grep -E "pdo_pgsql|mbstring|openssl|bcmath|zip"
