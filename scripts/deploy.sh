#!/bin/bash

# Script per deployare il plugin su più siti WordPress
# Uso: ./scripts/deploy.sh

echo "🚀 Deploy FP Git Updater"

# Configurazione - modifica questi valori
SITES=(
    # Format: "ssh_user@host:/path/to/wordpress/wp-content/plugins/"
    # Esempio:
    # "user@sito1.com:/var/www/html/wp-content/plugins/"
    # "user@sito2.com:/var/www/html/wp-content/plugins/"
)

# Se l'array è vuoto, mostra l'errore
if [ ${#SITES[@]} -eq 0 ]; then
    echo "❌ Errore: Nessun sito configurato"
    echo "Modifica il file scripts/deploy.sh e aggiungi i tuoi siti"
    exit 1
fi

# Nome del plugin
PLUGIN_NAME="fp-git-updater"

# Crea il pacchetto
echo "📦 Creazione pacchetto..."
./scripts/build.sh

if [ ! -f "${PLUGIN_NAME}.zip" ]; then
    echo "❌ Errore: pacchetto non trovato"
    exit 1
fi

# Deploy su ogni sito
for site in "${SITES[@]}"; do
    echo ""
    echo "📤 Deploy su: ${site}"
    
    # Estrai host e path
    HOST=$(echo "${site}" | cut -d: -f1)
    PATH=$(echo "${site}" | cut -d: -f2)
    
    # Backup della versione attuale
    echo "  💾 Backup versione corrente..."
    ssh "${HOST}" "cd ${PATH} && [ -d ${PLUGIN_NAME} ] && tar -czf ${PLUGIN_NAME}-backup-\$(date +%Y%m%d-%H%M%S).tar.gz ${PLUGIN_NAME} || echo 'Nessun backup necessario'"
    
    # Upload del nuovo pacchetto
    echo "  📤 Upload nuovo pacchetto..."
    scp "${PLUGIN_NAME}.zip" "${HOST}:/tmp/"
    
    # Installa la nuova versione
    echo "  🔧 Installazione..."
    ssh "${HOST}" "cd ${PATH} && rm -rf ${PLUGIN_NAME} && unzip -q /tmp/${PLUGIN_NAME}.zip && rm /tmp/${PLUGIN_NAME}.zip"
    
    # Verifica
    if [ $? -eq 0 ]; then
        echo "  ✅ Deploy completato su ${site}"
    else
        echo "  ❌ Errore durante il deploy su ${site}"
    fi
done

# Pulizia
echo ""
echo "🧹 Pulizia file temporanei..."
rm -f "${PLUGIN_NAME}.zip"

echo ""
echo "🎉 Deploy completato!"
