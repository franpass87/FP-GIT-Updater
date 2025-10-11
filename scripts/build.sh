#!/bin/bash

# Script per creare un pacchetto ZIP del plugin
# Uso: ./scripts/build.sh

echo "🚀 Creazione pacchetto FP Git Updater..."

# Nome del plugin
PLUGIN_NAME="fp-git-updater"

# Directory di output
BUILD_DIR="build"
ZIP_NAME="${PLUGIN_NAME}.zip"

# Pulisci build precedenti
echo "🧹 Pulizia build precedenti..."
rm -rf "${BUILD_DIR}"
rm -f "${ZIP_NAME}"

# Crea directory build
mkdir -p "${BUILD_DIR}/${PLUGIN_NAME}"

# Copia i file necessari
echo "📦 Copia file..."
cp -r includes "${BUILD_DIR}/${PLUGIN_NAME}/"
cp -r assets "${BUILD_DIR}/${PLUGIN_NAME}/"
cp fp-git-updater.php "${BUILD_DIR}/${PLUGIN_NAME}/"
cp uninstall.php "${BUILD_DIR}/${PLUGIN_NAME}/"
cp README.md "${BUILD_DIR}/${PLUGIN_NAME}/"
cp LICENSE "${BUILD_DIR}/${PLUGIN_NAME}/"

# Opzionali (commenta se non vuoi includerli nel pacchetto)
cp INSTALL.md "${BUILD_DIR}/${PLUGIN_NAME}/" 2>/dev/null || true
cp CHANGELOG.md "${BUILD_DIR}/${PLUGIN_NAME}/" 2>/dev/null || true

# Crea lo ZIP
echo "🗜️  Creazione archivio ZIP..."
cd "${BUILD_DIR}"
zip -r "../${ZIP_NAME}" "${PLUGIN_NAME}" -x "*.DS_Store" -x "__MACOSX" -x "*.git*"
cd ..

# Pulisci directory temporanea
rm -rf "${BUILD_DIR}"

# Verifica
if [ -f "${ZIP_NAME}" ]; then
    SIZE=$(du -h "${ZIP_NAME}" | cut -f1)
    echo "✅ Pacchetto creato con successo!"
    echo "📁 File: ${ZIP_NAME}"
    echo "💾 Dimensione: ${SIZE}"
    echo ""
    echo "🎉 Pronto per l'upload su WordPress!"
else
    echo "❌ Errore nella creazione del pacchetto"
    exit 1
fi
