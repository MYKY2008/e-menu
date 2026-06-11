#!/bin/bash

# GastroLink QR — Deployment Diagnostic Tool
# Usage: bash docs/diagnose_deploy.sh

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}--- GastroLink QR Diagnostic Tool ---${NC}"

# 1. Configuration
ROOT_DIR="/home/ubuntu/emenu"
NGINX_CONF="/etc/nginx/sites-enabled/emenu.myky.cz"

# Check if script is run on Linux
if [[ "$OSTYPE" == "msys" || "$OSTYPE" == "win32" ]]; then
    echo -e "${RED}[!] Tento skript je určený pre Linux (Ubuntu).${NC}"
    exit 1
fi

# 2. Directory Existence
echo -n "[1] Kontrola priečinka projektu: "
if [ -d "$ROOT_DIR" ]; then
    echo -e "${GREEN}OK${NC} ($ROOT_DIR)"
else
    echo -e "${RED}CHÝBA${NC} ($ROOT_DIR neexistuje)"
    echo "    Tip: Skontroluj ci sa priecinok nevola EMENU (velkymi)."
fi

# 3. Style.css Existence
echo -n "[2] Kontrola style.css: "
CSS_PATH="$ROOT_DIR/assets/css/style.css"
if [ -f "$CSS_PATH" ]; then
    echo -e "${GREEN}OK${NC}"
else
    echo -e "${RED}CHÝBA${NC} ($CSS_PATH)"
    echo "    Tip: Skontroluj male/velke pismena v nazvoch priecinkov (assets/css/style.css)."
fi

# 4. Permissions (www-data access)
echo -n "[3] Prístup pre www-data (Nginx): "
if sudo -u www-data test -r "$CSS_PATH" 2>/dev/null; then
    echo -e "${GREEN}OK${NC}"
else
    echo -e "${RED}ZAKÁZANÝ PRÍSTUP${NC}"
    echo "    Oprava: Spusti 'sudo chmod +x /home/ubuntu' a 'sudo chown -R www-data:www-data $ROOT_DIR'"
fi

# 5. Home Directory Permissions
echo -n "[4] Práva /home/ubuntu: "
PERM=$(stat -c '%a' /home/ubuntu 2>/dev/null)
if [ "$PERM" -ge "711" ]; then
    echo -e "${GREEN}OK${NC} ($PERM)"
else
    echo -e "${RED}PRÍLIŠ PRÍSNE${NC} ($PERM)"
    echo "    Oprava: Spusti 'sudo chmod +x /home/ubuntu' (Nginx potrebuje vstupne pravo)."
fi

# 6. Nginx Syntax
echo -n "[5] Nginx Syntax: "
if sudo nginx -t &>/dev/null; then
    echo -e "${GREEN}OK${NC}"
else
    echo -e "${RED}CHYBA${NC}"
    echo "    Tip: Spusti 'sudo nginx -t' pre vypis chyb."
fi

# 7. PHP-FPM Socket
echo -n "[6] PHP-FPM Socket: "
if [ -f "$NGINX_CONF" ]; then
    PHP_SOCK=$(grep -oP 'fastcgi_pass unix:\K[^;]+' "$NGINX_CONF" | head -1)
    if [ -S "$PHP_SOCK" ]; then
        echo -e "${GREEN}OK${NC} ($PHP_SOCK)"
    else
        echo -e "${RED}CHYBA${NC} (Socket $PHP_SOCK neexistuje)"
        echo "    Tip: Skontroluj nainstalovanu verziu PHP: 'ls /run/php/php*-fpm.sock'"
    fi
else
    echo -e "${YELLOW}KONFIG NENAŠIEL${NC} ($NGINX_CONF)"
fi

# 8. MIME Types
echo -n "[7] MIME Types (CSS support): "
if grep -q "include.*/etc/nginx/mime.types" /etc/nginx/nginx.conf; then
    echo -e "${GREEN}OK${NC}"
else
    echo -e "${YELLOW}VAROVANIE${NC} (Chyba v /etc/nginx/nginx.conf)"
fi

echo -e "${YELLOW}--- KONIEC DIAGNOSTIKY ---${NC}"
echo "Ak vsetko svieti zeleno a web stale nejde, skus Ctrl+F5 v prehliadaci."
