# KUMA BANA : API

API Laravel de la plateforme : demandes de vérification, consentements du citoyen, droits d'accès
validés par la source, attestations signées (Ed25519) et journal chaîné par empreintes.

```powershell
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed     # données de démonstration
php artisan serve                    # http://127.0.0.1:8000
php artisan schedule:work            # reprise automatique des demandes en attente
php artisan test
```

Les routes sont dans `routes/api.php`, la logique métier dans `app/Kuma/`.
