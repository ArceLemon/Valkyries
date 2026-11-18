# Couche 4 : journal de traçabilité

Tout ce qui se passe est enregistré : connexions et échecs de connexion, demandes, consultations, consentements, révocations, modifications du catalogue.

Le journal est en ajout seul. Chaque ligne contient l'empreinte SHA-256 de la précédente, donc modifier une ligne casse toute la suite. La vérification indique la ligne exacte et la raison, et un test réécrit une ligne directement en base pour le montrer.

Une chaîne d'empreintes ne suffit pas contre quelqu'un qui peut tout recalculer. Nous y ajoutons des points de contrôle : l'empreinte de tête est signée avec la clé privée de la plateforme (Ed25519). Sans cette clé, on ne peut pas refaire les signatures. Ces points sont publics, et le planificateur en signe régulièrement.

Chaque réponse est accompagnée d'une attestation signée que n'importe qui peut vérifier sans compte. Si une lettre du jeton est modifiée, la vérification répond « altérée » et n'affiche rien. Cette route est limitée à 60 appels par minute et par adresse, pour empêcher d'essayer des jetons en masse.

Le citoyen n'apparaît dans le journal que sous la forme d'une empreinte pseudonymisée, tronquée à 12 caractères. Un test vérifie qu'aucun identifiant ni aucune date de naissance n'y figure. L'administrateur et l'auditeur lisent le journal, et seul l'administrateur peut signer un point de contrôle.

## Contenu

- `openapi.yaml` : journal, points de contrôle, vérification publique des attestations, clés publiques
- `tests/ContratJournalTest.php` : contrat, attestation altérée, ligne réécrite dans la base, citoyen sous forme d'empreinte

Le code est dans `backend/app/Kuma/Journal/Journal.php`, `backend/app/Kuma/Preuves/Signataire.php` et les contrôleurs `JournalController` et `PreuveController`. Les tests `JournalEtPreuvesTest` et `PointsDeControleTest` s'y rattachent.

## Vérifier à la main

```powershell
cd backend
php artisan kuma:verifier-journal      # recalcule la chaîne et signale la première rupture
php artisan kuma:point-de-controle     # signe un point de contrôle
```

Dans le portail, c'est la page « Journal et intégrité » de l'administrateur ou de l'auditeur.
