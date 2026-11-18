# Couche 3 : consentement et minimisation

Cette couche décide de ce qui peut sortir, à quelles conditions et sous quelle forme.

Il n'existe que trois réponses : oui, non, impossible. Jamais une date, un nom ou une fiche. La réponse est calculée par une règle écrite dans une liste fermée de fonctions (`age_gte`, `equals`, `is_empty`, `in_list`, `date_gte`), sans code libre. Quand le registre est en panne, ou que le citoyen est introuvable, la réponse est « impossible » ou la demande attend : un faux « non » pourrait priver quelqu'un de ses droits, nous ne le produisons jamais.

Chaque question a un régime. Avec « base légale », un texte autorise la vérification : le citoyen est informé et la voit dans son historique. Avec « consentement », le registre n'est pas interrogé tant que le citoyen n'a pas accepté, et jamais s'il refuse. Un citoyen ne peut décider que pour lui-même.

Un droit n'existe pour une administration que si le gestionnaire du registre l'a validé, avec une référence juridique et éventuellement une date de fin.

L'agent ne saisit que l'identifiant unique du citoyen. Cet identifiant est chiffré le temps du traitement puis effacé. En base, le citoyen n'est identifié que par une empreinte (HMAC).

## Contenu

- `openapi.yaml` : le catalogue public et les consentements du citoyen
- `tests/ContratConsentementTest.php` : contrat, réponses fermées, aucun appel au registre sans accord, un citoyen ne décide que pour lui

Le code est dans `backend/app/Kuma/Assertions/EvaluateurRegle.php` (règles), `backend/app/Kuma/Demandes/ServiceDemandes.php` (régimes, consentement), `backend/app/Kuma/Support/Pseudonymiseur.php` et `backend/database/seeders/DemoSeeder.php` (catalogue). Les tests `EvaluateurRegleTest`, `ConsentementEtHistoriqueTest`, `CatalogueEtHabilitationTest` et `RevocationEtDroitsTest` s'y rattachent.

## Le citoyen sans smartphone

Tout le monde n'a pas l'application. Quand une demande attend l'accord du citoyen, un SMS lui dit qui demande et quelle question, avec un code à 6 chiffres. Il lit ce code à l'agent, qui le saisit dans le portail : l'accord est enregistré, avec le canal « code SMS » dans le journal.

Le code reste sous contrôle : valable 15 minutes, lié à cette demande (celui d'une autre demande est refusé), à usage unique, bloqué après 3 essais. On ne conserve que son empreinte, et l'API ne le renvoie jamais, y compris à l'agent. Seul le détenteur du numéro enregistré a pu le lire, donc le code vaut accord du citoyen.

Dans la démonstration, l'envoi est simulé : le SMS arrive dans l'écran « Messages » de l'application mobile du citoyen, qui joue ici le téléphone qu'il a en poche. L'écran de l'agent ne le montre jamais : l'agent ne reçoit le code que de la bouche du citoyen, comme en vrai. Avec un vrai opérateur, le même message arriverait par SMS sur un téléphone à touches. Les tests sont dans `backend/tests/Feature/CodeSmsTest.php` et dans le contrat de cette couche.

## Le catalogue

Sept questions : majeur, âge minimum, vivant, nationalité burkinabè, possède la nationalité indiquée, certificat de nationalité valide, casier vierge. Le casier et le fait de posséder une autre nationalité sont au régime du consentement, avec une preuve de courte durée.

Les références juridiques du catalogue sont des références de démonstration, marquées « à confirmer par l'administration responsable ».
