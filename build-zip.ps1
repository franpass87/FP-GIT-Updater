# Script per creare ZIP corretto per upload WordPress
# Evita cartelle annidate che causano 3 plugin duplicati

$PluginDir = $PSScriptRoot
$OutputName = "fp-git-updater-install.zip"
$OutputPath = [Environment]::GetFolderPath('Desktop') + "\" + $OutputName

# Cartella temporanea: nome = root nel ZIP (fp-git-updater = UNA sola cartella)
$TempDir = "$env:TEMP\fp-git-updater"
if (Test-Path $TempDir) { Remove-Item $TempDir -Recurse -Force }
New-Item -ItemType Directory -Path $TempDir -Force | Out-Null

# Copia file (escludi solo sviluppo)
Copy-Item "$PluginDir\*" -Destination $TempDir -Recurse -Force -Exclude ".git", "node_modules", ".gitignore", "build-zip.ps1"
# Rimuovi .git se copiato
if (Test-Path "$TempDir\.git") { Remove-Item "$TempDir\.git" -Recurse -Force }

# Crea ZIP - la cartella fp-git-updater diventa la root
if (Test-Path $OutputPath) { Remove-Item $OutputPath -Force }
Compress-Archive -Path $TempDir -DestinationPath $OutputPath -Force
Remove-Item $TempDir -Recurse -Force -ErrorAction SilentlyContinue

Write-Host "ZIP creato: $OutputPath" -ForegroundColor Green
Write-Host "Struttura: fp-git-updater/fp-git-updater.php (una sola cartella)" -ForegroundColor Cyan
