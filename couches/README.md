# Les cinq couches

Le défi impose cinq couches. Chacune a ici son dossier, avec sa spécification OpenAPI (`openapi.yaml`), ses tests (`tests/`) et un README.

1. `1-registres/` : les sources, qui gardent leurs fiches
2. `2-passerelle/` : la seule porte vers les registres
3. `3-consentement-minimisation/` : ce qui peut sortir, à quelles conditions
4. `4-journal-tracabilite/` : tout est enregistré, chaîné et signé
5. `5-espace-usager/` : ce que le citoyen voit et décide

Le guichet de l'agent et la résilience (disjoncteur, reprise automatique), les deux bonus du défi, sont décrits avec la couche 2.

## Une demande à travers les couches

Une administration demande si une personne est majeure.

La passerelle (2) vérifie que l'agent et son organisme sont actifs, qu'un droit existe pour cette question et cette finalité, et que la source l'a validé. Elle lit le régime de la question (3) : s'il exige le consentement, la demande attend l'accord du citoyen. Sinon elle interroge le registre (1), avec disjoncteur et reprise. Une règle fermée calcule la réponse (3), la fiche est détruite, la consultation est écrite dans le journal et l'attestation est signée (4). Le citoyen la retrouve ensuite dans son espace (5).

## Lancer les tests

```powershell
cd backend
php artisan test                        # toutes les couches
php artisan test --testsuite=couche-3   # une seule (couche-1 à couche-5)
```

Le test de contrat de chaque couche vérifie que chaque opération décrite dans `openapi.yaml` existe dans l'API et que les réponses réelles respectent les schémas. Une spécification inexacte fait échouer les tests.

## Brancher le registre d'une autre équipe

Voir `2-passerelle/BRANCHER_UN_REGISTRE.md`. Il suffit d'un profil de configuration et d'une variable d'environnement. `registres-demo/autre-equipe.php` simule des registres au format différent du nôtre, pour s'entraîner.
