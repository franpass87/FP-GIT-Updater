#!/bin/bash
#
# Script per generare il file .pot per le traduzioni
# 
# Requisiti: wp-cli installato
#

# Directory del plugin
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
POT_FILE="$PLUGIN_DIR/languages/fp-git-updater.pot"

echo "Generazione file .pot per traduzioni..."
echo "Directory plugin: $PLUGIN_DIR"
echo "File output: $POT_FILE"

# Controlla se wp-cli è installato
if ! command -v wp &> /dev/null; then
    echo "ERRORE: wp-cli non trovato. Installalo da https://wp-cli.org/"
    echo ""
    echo "In alternativa, usa questo comando manualmente:"
    echo "wp i18n make-pot $PLUGIN_DIR $POT_FILE --domain=fp-git-updater"
    exit 1
fi

# Crea directory languages se non esiste
mkdir -p "$PLUGIN_DIR/languages"

# Genera il file .pot
wp i18n make-pot "$PLUGIN_DIR" "$POT_FILE" \
    --domain=fp-git-updater \
    --package-name="FP Git Updater" \
    --headers='{"Report-Msgid-Bugs-To":"info@francescopasseri.com"}' \
    --skip-js

if [ $? -eq 0 ]; then
    echo "✓ File .pot generato con successo!"
    echo "✓ Numero di stringhe: $(grep -c "msgid" "$POT_FILE")"
    echo ""
    echo "Prossimi passi:"
    echo "1. Usa Poedit o simili per creare traduzioni (.po)"
    echo "2. Compila i file .mo da .po"
    echo "3. Carica i file in: $PLUGIN_DIR/languages/"
else
    echo "✗ Errore nella generazione del file .pot"
    exit 1
fi
