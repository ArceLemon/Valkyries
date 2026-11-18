# Couche 2 : passerelle d'échange

La passerelle est la seule porte vers les registres. Elle reçoit une question, vérifie les droits de celui qui la pose, interroge la source, applique les règles de la couche 3, écrit dans le journal de la couche 4 et ne renvoie qu'une réponse fermée.

Avant d'interroger un registre, elle vérifie quatre points : l'agent est actif, son organisme est actif, un droit existe pour cette question et cette finalité, et la source a validé ce droit sans qu'il soit expiré.

Elle accepte deux entrées avec les mêmes règles : le logiciel d'une administration (clé d'API, signature des demandes si l'organisme l'exige, quota, liste de questions autorisées) et le guichet d'un agent (jeton de connexion).

Côté résilience, un disjoncteur suspend les appels vingt secondes après trois échecs consécutifs, pour ne pas achever un registre déjà en difficulté. La demande reste en attente, et le planificateur la reprend quand le registre répond de nouveau. Aucune réponse n'est inventée pendant ce temps.

Décrire un registre se fait en configuration : une adresse, une authentification, la façon de retrouver une personne, la correspondance des champs. Le moteur n'a pas à changer.

## Contenu

- `openapi.yaml` : l'API pour le logiciel d'une administration, pour le guichet et pour l'état des registres
- `BRANCHER_UN_REGISTRE.md` : brancher le registre d'une autre équipe
- `tests/ContratPasserelleTest.php` : chaque opération décrite est vérifiée contre le comportement réel
- `tests/InteroperabiliteAutreEquipeTest.php` : trois registres au format différent, branchés par un profil, avec les mêmes réponses

Le code est dans `backend/app/Kuma/Registres/` (connecteur, gestionnaire, disjoncteur), `backend/app/Kuma/Api/` (clés d'API) et `backend/app/Kuma/Demandes/` (orchestration). La configuration est dans `backend/config/registres.php` et `backend/profils-registres/`.

## Les trois démarches du guichet

- Création d'entreprise : majorité, nationalité burkinabè, casier vierge (le casier attend l'accord du citoyen)
- Délivrance d'un document : majorité, vie du demandeur
- Inscription sur les listes électorales : majorité, nationalité burkinabè, vie du demandeur
