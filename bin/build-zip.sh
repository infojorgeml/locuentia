#!/usr/bin/env bash
#
# Genera dist/locuentia.zip listo para enviar a WordPress.org:
# nombre exacto = slug, carpeta raíz interna = slug, y sin archivos
# de desarrollo (usa los export-ignore de .gitattributes).
set -euo pipefail

cd "$(dirname "$0")/.."

mkdir -p dist
rm -f dist/locuentia.zip

git archive --format=zip --prefix=locuentia/ -o dist/locuentia.zip HEAD

echo "Generado: dist/locuentia.zip"
unzip -l dist/locuentia.zip
