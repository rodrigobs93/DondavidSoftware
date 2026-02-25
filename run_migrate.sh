#!/bin/bash
PHP_BIN="/c/Users/rodri/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe"
export PATH="$PHP_BIN:$PATH"
APP="C:/Users/rodri/OneDrive/COLDEVS/donDavidSoftware/app"
cd "$APP"
echo "=== Running migrations ==="
php artisan migrate --force 2>&1
echo "Exit: $?"
echo ""
echo "=== Running seeders ==="
php artisan db:seed --force 2>&1
echo "Exit: $?"
