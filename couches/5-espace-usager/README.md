# Couche 5 : espace usager

C'est ce que le citoyen voit et peut faire, depuis le portail web ou l'application mobile Flutter. Les deux appellent la même API.

La page centrale est « Qui a consulté mes données ? » : chaque consultation y apparaît avec l'organisme, la question, la finalité, le régime et la réponse, y compris celles qui n'ont pas demandé son accord. Le citoyen y voit qu'on a demandé s'il est majeur, jamais sa date de naissance, ce que vérifie un test.

Quand une demande attend son accord, il est prévenu par un message qui dit qui demande et quelle question, sans aucune donnée. Dans ce prototype le canal est simulé : le message est rangé en base et lisible par `/api/citoyen/messages`. Brancher un vrai opérateur SMS ne touche qu'une méthode de `Notificateur`. Le numéro est chiffré, et `KUMA_NOTIFICATIONS=aucun` coupe l'envoi. Un citoyen sans numéro retrouve de toute façon la demande dans l'application.

Le citoyen crée lui-même son compte dans l'application (`POST /api/auth/inscription`) : identifiant, nom, prénoms, téléphone et mot de passe. L'état civil confirme que l'identifiant existe, que la personne est en vie, que le nom et les prénoms sont les siens et que le numéro est le sien ; la fiche n'est pas conservée. Il se connecte ensuite avec son identifiant, sans adresse e-mail.

Dans l'application mobile, il reçoit aussi une notification push dès qu'une demande attend son accord ou qu'une information le concernant a été vérifiée. Le message ne contient aucune donnée ; il passe par ntfy, auto-hébergé, sur un sujet propre à chaque citoyen (`GET /api/citoyen/notifications/abonnement`).

Il peut accepter, refuser ou retirer un accord, avec une animation qui confirme la validation. Il retrouve ses attestations, chacune avec un code QR qu'il peut présenter à n'importe quelle administration. Un citoyen ne voit que ce qui le concerne et ne peut agir que pour lui-même.

## Contenu

- `openapi.yaml` : inscription, connexion, session, historique, preuves, notifications (les décisions de consentement sont dans la couche 3)
- `tests/ContratEspaceUsagerTest.php` : contrat, historique sans donnée, cloisonnement entre citoyens
- `tests/TroisDemarchesTest.php` : les trois démarches de bout en bout et leurs consultations dans l'espace

Le code est dans `portail/src/pages/citoyen/` (web), `mobile/lib/` (Flutter) et les contrôleurs `CitoyenController` et `AuthController` de l'API.

## Les trois démarches vues par le citoyen

Pour la création d'entreprise, il valide l'accès à son casier dans l'application et retrouve trois consultations. Pour la délivrance d'un document, il voit deux consultations sans rien avoir à faire. Pour l'inscription sur les listes électorales, il en voit trois, là aussi sans rien faire. Dans aucun cas on ne lui a demandé de justificatif.
