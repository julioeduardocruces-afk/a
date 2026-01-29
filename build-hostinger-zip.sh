#!/bin/bash
# ============================================================
#  Build Hostinger deployment zip
#  Creates: hostinger-deploy.zip with two folders:
#    ats-app/     → upload to /home/u.../ats-app/
#    public_html/ → upload contents INTO existing /home/u.../public_html/
# ============================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BUILD_DIR="/tmp/hostinger-deploy-$$"
ZIP_NAME="hostinger-deploy.zip"

echo "==> Limpiando build anterior..."
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR/ats-app" "$BUILD_DIR/public_html"

echo "==> Copiando archivos de la app a ats-app/..."
# Copy everything, then remove what shouldn't be deployed
cp -a "$SCRIPT_DIR/." "$BUILD_DIR/ats-app/"
rm -rf "$BUILD_DIR/ats-app/public"
rm -rf "$BUILD_DIR/ats-app/.git"
rm -rf "$BUILD_DIR/ats-app/node_modules"
rm -f  "$BUILD_DIR/ats-app/build-hostinger-zip.sh"
rm -f  "$BUILD_DIR/ats-app/.env"
rm -f  "$BUILD_DIR/ats-app/hostinger-deploy.zip"

echo "==> Copiando archivos publicos a public_html/..."
cp -a "$SCRIPT_DIR/public/." "$BUILD_DIR/public_html/"

echo "==> Instalando dependencias de composer (--no-dev)..."
cd "$BUILD_DIR/ats-app"
if command -v composer &>/dev/null; then
    composer install --no-dev --optimize-autoloader --no-interaction --quiet 2>/dev/null || {
        echo "    [WARN] composer install fallo. Ejecuta 'composer install --no-dev' por SSH en el servidor."
    }
else
    echo "    [WARN] composer no encontrado. Ejecuta 'composer install --no-dev' por SSH en el servidor."
fi

echo "==> Creando directorios de storage..."
mkdir -p "$BUILD_DIR/ats-app/storage/app/uploads"
mkdir -p "$BUILD_DIR/ats-app/storage/app/finals"
mkdir -p "$BUILD_DIR/ats-app/storage/app/public"
mkdir -p "$BUILD_DIR/ats-app/storage/framework/cache"
mkdir -p "$BUILD_DIR/ats-app/storage/framework/sessions"
mkdir -p "$BUILD_DIR/ats-app/storage/framework/views"
mkdir -p "$BUILD_DIR/ats-app/storage/logs"
mkdir -p "$BUILD_DIR/ats-app/bootstrap/cache"

# Ensure .gitkeep files so empty dirs are in the zip
for d in "$BUILD_DIR/ats-app/storage/app/uploads" \
         "$BUILD_DIR/ats-app/storage/app/finals" \
         "$BUILD_DIR/ats-app/storage/app/public" \
         "$BUILD_DIR/ats-app/storage/framework/cache" \
         "$BUILD_DIR/ats-app/storage/framework/sessions" \
         "$BUILD_DIR/ats-app/storage/framework/views" \
         "$BUILD_DIR/ats-app/storage/logs" \
         "$BUILD_DIR/ats-app/bootstrap/cache"; do
    touch "$d/.gitkeep"
done

echo "==> Generando ZIP..."
cd "$BUILD_DIR"
zip -r -q "$SCRIPT_DIR/$ZIP_NAME" ats-app/ public_html/

echo "==> Limpiando temporales..."
rm -rf "$BUILD_DIR"

echo ""
echo "======================================"
echo " ZIP listo: $SCRIPT_DIR/$ZIP_NAME"
echo "======================================"
echo ""
echo "Instrucciones:"
echo "  1. Descomprime el zip. Veras dos carpetas:"
echo "       ats-app/      → sube completa a /home/u.../ats-app/"
echo "       public_html/  → sube el CONTENIDO dentro de /home/u.../public_html/"
echo ""
echo "  2. Si no se instalo vendor/ (sin composer local),"
echo "     conecta por SSH y ejecuta:"
echo "       cd ~/ats-app && composer install --no-dev"
echo ""
echo "  3. Abre https://tu-dominio.com/install.php"
echo ""
