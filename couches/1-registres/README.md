# Couche 1 : registres

Trois sources simulées : l'état civil (identité, naissance, statut), la nationalité (pays et validité du certificat) et le casier (inscriptions). Chacune garde ses fiches et n'expose que sa part. Le casier ne renvoie par exemple ni identité ni date de naissance, ce que vérifie un test.

Un identifiant unique identifie le citoyen dans les trois registres (BF-0001). Chaque registre est protégé par une clé d'API et expose une route de santé publique. Les citoyens sont fictifs.

## Contenu

- `openapi.yaml` : le contrat qu'un registre doit suivre (santé, fiche par identifiant, recherche par attributs)
- `tests/ContratRegistresTest.php` : vérifie que nos registres le respectent

Le code est dans `registres-demo/` (`lib.php` pour la logique, `router.php` pour le serveur) et les fiches dans `registres-demo/citoyens.json`.

## Simuler une panne

```powershell
curl "http://127.0.0.1:8081/admin/panne?registre=etat-civil&mode=down"   # arrêt
curl "http://127.0.0.1:8081/admin/panne?registre=etat-civil&mode=lent"   # deux secondes de délai
curl "http://127.0.0.1:8081/admin/panne?registre=etat-civil&mode=ok"     # rétabli
```

## Contrat du jury

Le jury publie un contrat commun des registres et une liste de citoyens fictifs. Pour les utiliser, on remplace `citoyens.json` et on décrit leur format dans un profil de la passerelle, comme expliqué dans `../2-passerelle/BRANCHER_UN_REGISTRE.md`.
