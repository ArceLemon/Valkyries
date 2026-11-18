# KUMA BANA : rapport du sprint final

Défi « Dites-le-nous une fois ».

## 1. Le problème et notre parti pris

Quand une administration instruit un dossier, elle réclame au citoyen des pièces qu'une autre administration détient déjà : l'acte de naissance, le certificat de nationalité, l'extrait de casier. Le citoyen fait la navette, chaque guichet recopie, et personne ne sait ensuite où les copies sont passées.

Nous avons retenu une règle simple : la donnée ne bouge pas. Ce qui circule, c'est une question (« cette personne est-elle majeure ? ») et sa réponse, qui ne peut être que oui, non ou impossible. La source garde sa fiche, l'administration reçoit la seule information dont elle a besoin, et le citoyen peut retrouver chaque consultation dans son espace.

Le citoyen se présente toujours une fois, avec sa carte d'identité, pour s'identifier. Ce qu'il n'a plus à fournir, ce sont les justificatifs. Une démarche entièrement à distance reste possible, nous y revenons en section 6.

## 2. Architecture

Le défi impose cinq couches. Chacune a son dossier dans `couches/`, avec sa spécification OpenAPI, ses tests et un court README.

Les **registres** sont les sources : état civil, nationalité, casier. Chacun n'expose que sa part de la fiche, derrière une clé d'API, avec une route de santé. Ils sont simulés et peuplés de citoyens fictifs.

La **passerelle d'échange** est la seule porte vers eux. Avant toute interrogation, elle vérifie que l'agent et son organisme sont actifs, qu'un droit existe pour cette question et cette finalité, et que ce droit a été validé par la source. Elle protège ensuite le registre : après trois échecs consécutifs, un disjoncteur suspend les appels pendant vingt secondes, et un planificateur reprend les demandes restées en attente quand la source répond de nouveau. Elle accepte deux entrées, avec les mêmes règles : le logiciel d'une administration, par clé d'API, et le guichet d'un agent, par jeton de connexion.

La couche **consentement et minimisation** décide de ce qui peut sortir. Chaque question du catalogue a un régime : « base légale » quand un texte autorise la vérification (le citoyen est informé et la voit dans son historique), « consentement » quand la donnée est sensible (le registre n'est pas interrogé tant que le citoyen n'a pas accepté, et jamais s'il refuse). La réponse est calculée par une règle écrite dans une liste fermée de fonctions (`age_gte`, `equals`, `is_empty`, `in_list`, `date_gte`), sans code libre. La fiche est ensuite détruite.

Le **journal de traçabilité** est en ajout seul. Chaque ligne contient l'empreinte SHA-256 de la précédente, et des points de contrôle signés en Ed25519 figent l'empreinte de tête : même quelqu'un qui accède à la base ne peut pas recalculer la chaîne sans refaire les signatures. Les attestations remises avec chaque réponse sont signées de la même façon, et n'importe qui peut les vérifier sans compte.

L'**espace usager** est un espace citoyen du portail web et une application mobile Flutter, qui appellent la même API. Le citoyen y crée lui-même son compte (identifiant, nom, prénoms, numéro de téléphone, mot de passe, vérifiés auprès de l'état civil), voit qui a consulté ses données, accepte, refuse ou retire un accord, et présente ses attestations avec leur code QR. Il est prévenu sur son téléphone par une notification push dès qu'une administration attend son accord ou a vérifié une information le concernant.

Les deux bonus sont livrés : le guichet de l'agent (dans le portail, avec tableau de bord et démarches) et la résilience décrite plus haut.

**Administration de la plateforme.** Un organisme est créé « en préparation », sans aucun accès. Sa fiche guide l'administrateur jusqu'à la mise en service : créer son responsable (organisme demandeur) ou le gestionnaire de son registre (organisme fournisseur), lui accorder des droits, que la source devra valider, puis l'activer. L'administrateur crée les comptes et leur attribue un rôle ; seuls les rôles compatibles avec l'organisme sont acceptés, et le serveur refuse les autres (pas de gestionnaire dans un organisme sans registre, pas d'auditeur rattaché à un organisme). Un changement de rôle ferme immédiatement les sessions ouvertes, car le jeton porte l'ancien rôle. Création, changement de rôle, révocation et réactivation sont journalisés. Les rôles eux-mêmes restent fixés dans le code : nous n'avons pas fait d'éditeur de rôles et de permissions.

### Choix techniques

L'API est en PHP 8.4 avec Laravel 13, le portail en React 19 et TypeScript, l'application en Flutter, la base en PostgreSQL 17, les signatures par libsodium. La base n'est joignable que par l'API et le planificateur (aucun port publié) ; un verrou PostgreSQL sérialise les écritures du journal pour que des requêtes simultanées ne fassent pas bifurquer la chaîne. Les notifications push passent par ntfy (Apache 2.0 / GPLv2), auto-hébergé : ni Firebase ni service d'Apple. L'ensemble démarre avec `docker compose up`, nginx servant le portail et relayant `/api` et, en lecture seule, `/notifications`. L'application mobile n'utilise que ce port.

Nous avons préféré un monolithe modulaire à cinq services séparés. Les couches 2 à 4 vivent dans la même application Laravel, séparées par leurs dossiers, leurs routes, leur contrat OpenAPI et leurs tests. Dans le temps d'un sprint, cela nous a permis de tester chaque couche sérieusement plutôt que de passer le temps à câbler des services. Le prix à payer est connu : ces trois couches se déploient ensemble. Les registres, eux, sont déjà un service à part, ce qui est le découpage qui compte pour l'interopérabilité.

## 3. Les trois démarches

Les trois sont jouées de bout en bout par le guichet, sans qu'aucune pièce ne soit demandée au citoyen.

**Création d'entreprise** (Registre du commerce) : majorité, nationalité burkinabè, casier vierge. Les deux premières réponses arrivent immédiatement. Le casier est sensible : la demande attend que la citoyenne l'accepte depuis son téléphone, puis le registre est interrogé. Elle retrouve ensuite trois consultations dans son espace.

**Délivrance d'un document** (mairie) : majorité et vie du demandeur, toutes deux au régime de la base légale. Le citoyen n'a rien à faire et voit deux consultations.

**Inscription sur les listes électorales** (mairie) : majorité, nationalité et vie du demandeur, ce qui mobilise deux registres. Aucun consentement n'est requis.

Un test par démarche (`couches/5-espace-usager/tests/TroisDemarchesTest.php`) vérifie les réponses, les consultations visibles côté citoyen et l'absence de toute donnée personnelle dans la base, le journal et les attestations.

## 4. Interopérabilité

**Contrats.** Chaque couche a un fichier `openapi.yaml`. Un test par couche vérifie que chaque opération décrite existe dans l'API et que les réponses réelles respectent les schémas. Ces tests ont déjà servi : ils ont relevé des écarts entre notre spécification et l'API (un identifiant d'acteur qui n'est pas toujours un entier, une réponse 403 non documentée), que nous avons corrigés. Les cinq fichiers passent aussi le validateur Redocly sans erreur.

**Le registre d'une autre équipe.** Pour répéter l'épreuve en direct, nous avons simulé trois registres dont le format diffère volontairement du nôtre : jeton `Bearer` à la place d'une clé d'API, recherche par `?nin=`, réponses sous forme de liste, champs en anglais, nationalité sur trois lettres, statut `ALIVE`. Les brancher demande un fichier de profil et une variable d'environnement. Le moteur n'a pas été modifié ; nous avons seulement ajouté trois options génériques au connecteur HTTP (paramètres d'URL, réponse en liste, traduction de valeurs).

Le test `InteroperabiliteAutreEquipeTest` rejoue la majorité, la nationalité, le statut de vie, le casier avec accord, un citoyen introuvable, un jeton refusé et la surveillance de santé : les réponses sont identiques à celles de nos registres. Nous l'avons aussi vérifié en HTTP réel dans Docker, en lançant les trois démarches avant et après la bascule du profil, avec des réponses identiques. La marche à suivre est dans `couches/2-passerelle/BRANCHER_UN_REGISTRE.md`.

**Panne.** Registre coupé en cours de démarche : la demande reste en attente, le disjoncteur s'ouvre, et la demande se termine seule au retour du registre, quelques secondes plus tard. Ce parcours est rejoué par un test automatisé (`PanneEtRepriseTest`).

**Ensemble.** L'API compte 173 tests (1 121 assertions), dont l'inscription vérifiée, les notifications push et la mise en service d'un organisme, Le portail a 8 parcours de bout en bout, joués dans Chrome contre le système lancé par Docker : connexion (mauvais mot de passe, agent par e-mail, citoyen par son seul identifiant), démarche sans pièce suivie de la consultation retrouvée dans l'espace du citoyen, casier en attente puis accepté par le citoyen, attestation vérifiée sans compte puis refusée après modification d'une lettre, catalogue public et intégrité du journal. L'application mobile a deux tests d'interface (démarrage et connexion, états des demandes) ; c'est la partie la moins couverte.

Une réserve : le contrat commun des trois registres publié par le jury doit encore être confronté à notre connecteur. Notre propre format est documenté dans `couches/1-registres/openapi.yaml`. Un format différent se décrit dans un profil, comme ci-dessus, mais nous ne l'avons pas encore fait pour le leur.

## 5. Protection des données personnelles

Nous avons cherché à réduire ce qui existe plutôt qu'à le protéger après coup.

*Minimisation et finalité.* La réponse est fermée, jamais la donnée. Chaque demande porte une finalité, et un droit n'existe que pour une question et une finalité données. Un registre ne renvoie que sa part de la fiche : le casier ne renvoie ni identité ni naissance, ce que vérifie un test.

*L'identifiant seul.* Pour désigner le citoyen, l'agent saisit son identifiant unique et rien d'autre : ni date de naissance, ni nom, ni lieu de naissance. Le serveur exige l'identifiant et ignore tout autre champ, dans le guichet comme dans l'API des administrations. L'identifiant étant unique, ces données supplémentaires n'apportaient rien à la demande et constituaient autant de données personnelles en circulation.

*Conservation.* L'identifiant est chiffré le temps du traitement puis effacé. En base, le citoyen n'est identifié que par une empreinte calculée avec une clé secrète (HMAC). Un test vérifie qu'aucun identifiant ni date de naissance ne subsiste dans les demandes, le journal ou les attestations.

*Consentement et information.* Voir la couche 3. L'accord est retirable, et l'attestation déjà émise cesse alors d'être valable. Le citoyen n'est jamais consulté à son insu : l'historique liste aussi les consultations qui n'ont pas demandé son accord.

*Inscription du citoyen.* Le citoyen crée son compte avec son identifiant, son nom, ses prénoms, son numéro de téléphone et un mot de passe. Pour qu'on ne puisse pas ouvrir un compte sur l'identifiant d'un autre, et accepter des demandes à sa place, KUMA BANA interroge le registre d'état civil : l'identifiant doit exister et appartenir à une personne vivante, le nom et les prénoms doivent être les siens (accents et majuscules ne comptent pas, mais la comparaison reste exacte), et le numéro saisi doit être celui que le registre connaît pour elle. La fiche ne sert qu'à cette comparaison ; elle n'est ni conservée ni journalisée. Le compte garde le nom tel que le citoyen l'a saisi, pour l'accueillir dans l'application. Le message d'échec est le même dans tous les cas, pour ne rien révéler du registre. Un identifiant ne sert qu'à un compte, et l'inscription est limitée à cinq essais par minute. Une demande faite avant l'inscription attend le citoyen : il la retrouve dès son premier accès. Le citoyen se connecte ensuite avec son identifiant ; il n'a pas besoin d'adresse e-mail.

*Notifications sans donnée.* Le message push ne dit ni qui demande, ni quoi, ni la réponse : seulement « une administration attend votre accord » ou « une information vous concernant a été vérifiée ». L'application va ensuite chercher le détail par l'API authentifiée. Chaque citoyen a son propre sujet ntfy, dérivé de la clé secrète de la plateforme : il est impossible à deviner et seul le compte connecté peut l'obtenir. Seule l'API publie, depuis le réseau interne ; nginx n'autorise depuis l'extérieur que la lecture d'un sujet au bon format, et refuse toute publication (nous l'avons vérifié, y compris la publication par GET que ntfy accepte nativement). Une panne de ntfy ne bloque jamais une demande.

*Sécurité.* Les mots de passe et les clés d'API ne sont stockés que sous forme d'empreinte. Chaque route vérifie le rôle côté serveur. Un agent, une clé d'API ou un organisme se révoque immédiatement. La route de vérification publique des attestations est limitée à 60 appels par minute et par adresse. L'administrateur de la plateforme ne voit aucune donnée de citoyen.

*Séparation des pouvoirs.* L'administration qui demande n'est pas celle qui autorise. Le gestionnaire du registre valide chaque droit avec une référence juridique et, s'il le souhaite, une date de fin.

Une limite, que nous préférons écrire clairement : les références juridiques du catalogue sont des références de démonstration, marquées « à confirmer par l'administration responsable ». Nous n'affirmons aucune conformité à la loi burkinabè sur la protection des données à caractère personnel. Elle devra être vérifiée, article par article, avant toute utilisation réelle. Les données, les comptes et les clés du dépôt sont fictifs.

## 6. Limites et suite

Les registres sont simulés et la base PostgreSQL tourne sur un seul serveur, sans réplication ni sauvegarde automatique. Il n'y a pas de double authentification. L'identifiant national est ici fictif, la plateforme tourne sur un seul nœud. Le dépôt est publié sous licence Apache 2.0.

**Inscription.** Le contrôle du numéro de téléphone suppose que le registre connaisse un numéro par personne. Nos registres de démonstration en ont un. Si une source n'en fournit pas, l'inscription ne vérifie plus que l'identifiant, le nom et les prénoms, que d'autres personnes peuvent connaître. Nous n'envoyons pas encore de code par SMS pour prouver que le citoyen détient le téléphone : connaître le numéro d'une personne suffit aujourd'hui. Un code de confirmation est la prochaine étape. L'inscription n'existe que dans l'application mobile ; un citoyen inscrit peut en revanche se connecter au portail web avec son identifiant.

**USSD.** Le numéro de téléphone servira aussi à un canal USSD, pour le citoyen sans smartphone : une session `*XXX#` lui présenterait les demandes en attente et lui permettrait d'accepter ou de refuser, sur le même modèle que le code de confirmation déjà proposé à l'agent. Faute de temps et d'accès à une passerelle opérateur, ce canal est décrit mais pas réalisé.

**Notifications.** Sur Android, garder une connexion ouverte appli fermée impose un « service de premier plan » : une petite notification permanente indique que KUMA BANA reste à l'écoute, et le citoyen doit retirer l'application des optimisations de batterie pour qu'Android ne la coupe pas. C'est le prix d'un push sans Google ; l'application ntfy officielle fait de même. En démonstration, le flux passe en HTTP sur le réseau local ; en production, il passerait en HTTPS derrière le même nginx. iOS n'est pas traité.

**Administration.** Les rôles sont fixes : un administrateur attribue un rôle existant, il n'en crée pas de nouveaux. Un organisme reste inutilisable tant qu'il n'est pas activé, y compris pour les comptes déjà créés ; sa fiche l'indique explicitement.

Le citoyen ne peut pas lancer lui-même une demande, il répond à celles qu'on lui adresse. Une démarche entièrement à distance passerait par le site d'une administration appelant l'API avec sa clé ; c'est alors à cette administration d'identifier le citoyen, ce que KUMA BANA ne fait pas.

## 7. Reproduire

```powershell
docker compose up --build -d          # le système complet
cd backend; php artisan test          # 173 tests, dont ceux des cinq couches et des trois démarches (SQLite en mémoire ; ils passent aussi sur PostgreSQL)
cd portail; npm run e2e               # 8 parcours dans Chrome (le système Docker doit tourner)
```

Le détail de chaque couche est dans `couches/README.md`.
