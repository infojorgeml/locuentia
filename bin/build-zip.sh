#!/usr/bin/env bash
#
# Genera releases/locuentia-<version>.zip totalmente limpio: contenido de
# git archive en HEAD, sin archivos de desarrollo (export-ignore de
# .gitattributes), carpeta raíz interna = locuentia/.
#
# La versión se lee de la cabecera de locuentia.php, así cada release
# deja su propio ZIP para probar en producción y pasar Plugin Check.
set -euo pipefail

cd "$(dirname "$0")/.."

VERSION=$(sed -n 's/^ \* Version:[[:space:]]*//p' locuentia.php | head -1 | tr -d '[:space:]')
if [ -z "$VERSION" ]; then
	echo "ERROR: no se pudo leer la versión de locuentia.php" >&2
	exit 1
fi

if [ -n "$(git status --porcelain)" ]; then
	echo "AVISO: hay cambios sin commitear; el ZIP se genera desde el último commit (HEAD)."
fi

mkdir -p releases
OUT="releases/locuentia-${VERSION}.zip"
rm -f "$OUT"

git archive --format=zip --prefix=locuentia/ -o "$OUT" HEAD

echo "Generado: $OUT"
unzip -l "$OUT"
echo
echo "Recuerda: para el envío inicial a WordPress.org el archivo debe llamarse exactamente locuentia.zip (cópialo renombrado)."
