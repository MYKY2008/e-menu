#!/bin/bash

echo ""
echo " === GastroLink QR - spustanie ==="
echo ""

# Najdi PHP
PHP=""
for candidate in php8.3 php8.2 php8.1 php8 php; do
    if command -v "$candidate" &>/dev/null; then
        PHP="$candidate"
        break
    fi
done

if [ -z "$PHP" ]; then
    echo "[CHYBA] PHP nenajdene. Nainštaluj PHP: sudo apt install php"
    exit 1
fi
echo "[OK] PHP: $(command -v $PHP) ($($PHP -r 'echo PHP_VERSION;'))"

# Skontroluj rozsirenia
EXT=""
if ! $PHP -r "exit(extension_loaded('pdo_sqlite')?0:1);" 2>/dev/null; then
    echo "[CHYBA] pdo_sqlite chyba. Nainštaluj: sudo apt install php-sqlite3"
    exit 1
else
    echo "[OK] pdo_sqlite: OK"
fi

if ! $PHP -r "exit(extension_loaded('mbstring')?0:1);" 2>/dev/null; then
    echo "[WARN] mbstring chyba. Nainštaluj: sudo apt install php-mbstring"
else
    echo "[OK] mbstring: OK"
fi

# Skontroluj index.php
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [ ! -f "$SCRIPT_DIR/index.php" ]; then
    echo "[CHYBA] index.php nenajdeny v $SCRIPT_DIR"
    exit 1
fi
echo "[OK] index.php: OK"

# CSS build (Tailwind CLI)
if command -v npx &>/dev/null; then
    echo ""
    echo "[CSS] Buildujem Tailwind CSS..."
    if [ ! -d "$SCRIPT_DIR/node_modules" ]; then
        echo "[CSS] npm install..."
        npm install --silent
    fi
    npx tailwindcss -i ./assets/css/input.css -o ./assets/css/style.css --minify
    echo "[OK] CSS: assets/css/style.css"
    echo "[CSS] Spustam watcher na pozadi..."
    npx tailwindcss -i ./assets/css/input.css -o ./assets/css/style.css --watch &
else
    echo "[WARN] Node.js / npx nenajdene - CSS build preskoceny."
    echo "       Nainštaluj Node.js: https://nodejs.org"
fi

echo ""
echo " URL : http://localhost:8080"
echo " Stop: Ctrl+C"
echo ""

cd "$SCRIPT_DIR"
$PHP -S localhost:8080 index.php
