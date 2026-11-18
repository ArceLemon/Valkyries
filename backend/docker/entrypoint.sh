#!/bin/sh
# Prépare l'environnement de l'API, puis lance la commande demandée.
set -e

mkdir -p /data/cles

# La clé de l'application chiffre les données temporaires et pseudonymise les identifiants :
# elle est créée une fois et conservée dans le volume, sinon les données deviendraient illisibles.
if [ -z "$APP_KEY" ]; then
    if [ ! -f /data/app.key ]; then
        php -r 'echo "base64:" . base64_encode(random_bytes(32));' > /data/app.key
    fi
    APP_KEY="$(cat /data/app.key)"
    export APP_KEY
fi

php artisan package:discover --ansi >/dev/null

# Seul le service « api » prépare la base PostgreSQL (KUMA_INIT=1) : migrations, puis données
# de démonstration si la base est encore vide (aucun organisme).
if [ "$KUMA_INIT" = "1" ]; then
    php artisan migrate --force --no-interaction
    organismes="$(php artisan tinker --execute='echo \App\Models\Organization::count();' | tail -n 1)"
    if [ "$organismes" = "0" ]; then
        php artisan db:seed --force --no-interaction
    fi
fi

exec "$@"
