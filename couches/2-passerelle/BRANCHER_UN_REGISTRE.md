# Brancher le registre d'une autre équipe

L'épreuve en direct demande de brancher le registre d'une autre équipe sur notre passerelle. Le moteur ne
change pas : on décrit le registre dans un **profil**, un fichier de configuration.

## Ce qu'il faut savoir de leur registre

1. Son adresse de base (par exemple `http://leur-machine:9000/api`).
2. Comment on s'authentifie : une clé dans un en-tête, ou un jeton `Bearer`.
3. Comment on retrouve une personne : par un chemin (`/citoyens/{identifiant}`) ou par un paramètre d'URL
   (`/personnes?nin=...`), et si la réponse est une fiche ou une liste.
4. Le nom de leurs champs, pour les faire correspondre aux nôtres.

## Ce que sait faire le connecteur

- `auth` : `api_key` (en-tête au choix) ou `bearer`
- `lookup.chemin` : avec `{identifiant}` dans le chemin
- `lookup.requete` : paramètres d'URL, par exemple `['nin' => '{identifiant}']`
- `lookup.liste` : chemin de la liste quand la réponse est une liste (vide : introuvable, plusieurs : ambigu, un : la fiche)
- `lookup.mode = attributs` : recherche par nom, date et lieu de naissance, avec refus de deviner
- `champs` : correspondance « champ de la plateforme » vers « chemin dans leur réponse » (`born.on`, `data.0.nom`)
- `valeurs` : traduction de leurs valeurs vers les nôtres (`ALIVE` devient `vivant`, `BFA` devient `BF`)
- `sante.chemin` : leur route de santé

Les champs que la plateforme attend, par registre :

- état civil : `nom`, `date_naissance`, `lieu_naissance`, `statut` (valeurs `vivant` ou `decede`)
- nationalité : `nationalite` (code sur deux lettres, `BF`), `certificat_expire` (date)
- casier : `condamnations` (liste, vide pour un casier vierge)

## Pas à pas

1. Copier `backend/profils-registres/autre-equipe.php` en `backend/profils-registres/<leur-nom>.php`.
2. Y remplacer l'adresse, l'authentification, les chemins, les champs et les valeurs.
3. Activer le profil :
   - avec Docker : `$env:REGISTRES_PROFIL='<leur-nom>'` puis `docker compose up -d`
   - en local : `REGISTRES_PROFIL=<leur-nom>` dans `backend/.env`, puis `php artisan config:clear`
4. Vérifier : l'écran « État des registres » du portail doit passer à « En service », puis lancer une
   démarche. La passerelle interroge leur registre et rend les mêmes réponses.

Chaque entrée du profil **remplace** le registre du même nom (`etat_civil`, `nationalite`, `casier`). On peut
donc ne brancher qu'un seul de leurs registres et garder les nôtres pour les deux autres.

## Ce qu'on a déjà répété

Le dossier `registres-demo/` contient `autre-equipe.php`, un faux registre au format volontairement différent
(jeton `Bearer`, `?nin=`, réponse en liste, champs en anglais, nationalité `BFA`, statut `ALIVE`).

```powershell
$env:REGISTRES_PROFIL='autre-equipe'
docker compose --profile autre-equipe up -d     # démarre aussi leur registre, sur le port 8082
```

Le test `tests/InteroperabiliteAutreEquipeTest.php` rejoue majeur, nationalité, vivant, casier avec accord,
citoyen introuvable, jeton refusé et santé du registre : mêmes réponses qu'avec nos propres registres.

## Si le format est trop éloigné

Le profil couvre les registres HTTP et JSON. Pour un autre type de source, on écrit une classe qui implémente
`ConnecteurRegistre` (deux méthodes : `rechercher` et `sante`) et on la déclare avec un nouveau `type`.
