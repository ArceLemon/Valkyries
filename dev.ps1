
param(
    [switch]$Reinitialiser,
    [switch]$SansPortail
)

$racine = $PSScriptRoot
$backend = Join-Path $racine 'backend'
$portail = Join-Path $racine 'portail'

if (-not (Test-Path (Join-Path $backend 'vendor'))) {
    Write-Host 'Installation des dépendances du backend...'
    Push-Location $backend; composer install --no-interaction; Pop-Location
}
if (-not (Test-Path (Join-Path $backend '.env'))) {
    Copy-Item (Join-Path $backend '.env.example') (Join-Path $backend '.env')
    Push-Location $backend; php artisan key:generate --no-interaction; Pop-Location
}
if (-not (Test-Path (Join-Path $backend 'database\database.sqlite'))) {
    New-Item -ItemType File (Join-Path $backend 'database\database.sqlite') | Out-Null
    $Reinitialiser = $true
}
if ($Reinitialiser) {
    Push-Location $backend; php artisan migrate:fresh --seed --force --no-interaction; Pop-Location
} else {
    Push-Location $backend; php artisan migrate --force --no-interaction; Pop-Location
}

function Lancer([string]$titre, [string]$dossier, [string]$commande) {
    $script = "`$Host.UI.RawUI.WindowTitle = '$titre'; Set-Location '$dossier'; $commande"
    Start-Process powershell -ArgumentList '-NoExit', '-Command', $script
}

Lancer 'Faux registres (8081)' $racine "`$env:PHP_CLI_SERVER_WORKERS = '4'; php -S 127.0.0.1:8081 registres-demo/router.php"
Lancer 'API KUMA BANA (8000)' $backend "`$env:PHP_CLI_SERVER_WORKERS = '4'; php artisan serve --host=127.0.0.1 --port=8000"
Lancer 'Planificateur : sonde et reprise' $backend 'php artisan schedule:work'

if (-not $SansPortail) {
    if (-not (Test-Path (Join-Path $portail 'node_modules'))) {
        Push-Location $portail; npm install; Pop-Location
    }
    Lancer 'Portail (5173)' $portail 'npm run dev'
}

Write-Host ''
Write-Host 'KUMA BANA démarre en local.'
Write-Host '  API     http://127.0.0.1:8000/api'
Write-Host '  Portail http://localhost:5173'
Write-Host '  Registres de démonstration http://127.0.0.1:8081'
