#!/bin/bash
PHP_BIN="/c/Users/rodri/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe"
PATH="$PHP_BIN:$PATH"
COMPOSER="$PHP_BIN/composer"
TARGET="C:/Users/rodri/OneDrive/COLDEVS/donDavidSoftware/app"

echo "Creating Laravel project..."
php "$COMPOSER" create-project laravel/laravel "$TARGET" --prefer-dist --no-interaction
echo "Done: $?"
