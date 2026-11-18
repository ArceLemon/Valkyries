<?php

namespace App\Kuma\Demandes;

use App\Kuma\Assertions\EvaluateurRegle;
use App\Kuma\Journal\Journal;
use App\Kuma\Notifications\Notificateur;
use App\Kuma\Notifications\Push;
use App\Kuma\Preuves\Signataire;
use App\Kuma\Registres\GestionnaireRegistres;
use App\Kuma\Registres\Recherche;
use App\Kuma\Registres\ResultatRegistre;
use App\Kuma\Statut;
use App\Kuma\Support\Pseudonymiseur;
use App\Models\AccessRule;
use App\Models\Assertion;
use App\Models\Attestation;
use App\Models\Consent;
use App\Models\RegistryHealth;
use App\Models\User;
use App\Models\VerificationRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Orchestre le cycle d'une demande : contrôles d'accès, consentement, interrogation
 * du registre, calcul de la réponse minimale, preuve signée, journal.
 *
 * Principe : la donnée brute d'un registre n'existe que dans la mémoire de
 * traiter(), le temps de calculer la réponse. Elle n'est jamais enregistrée.
 */
final class ServiceDemandes
{
    public function __construct(
        private readonly Journal $journal,
        private readonly GestionnaireRegistres $registres,
        private readonly Signataire $signataire,
        private readonly Pseudonymiseur $pseudonymes,
        private readonly Notificateur $notificateur,
        private readonly Push $push,
    ) {}

    /**
     * @param  array{question: string, parametre?: string|null, finalite: string, citoyen: array<string, string|null>, lot?: string|null}  $donnees
     * @param  array<string, string>  $trace  origine de la demande (clé d'API)
     */
    public function creer(User $agent, array $donnees, array $trace = []): VerificationRequest
    {
        $organisme = $agent->organization;
        if ($organisme === null || ! $organisme->estActive() || ! $organisme->aRole('demandeuse')) {
            $this->refuser($agent, 'organisme_non_habilite', $donnees, 403, 'Votre organisme n\'est pas habilité à poser des questions.');
        }

        $assertion = Assertion::where('code', $donnees['question'])->where('publie', true)->first();
        if ($assertion === null) {
            abort(422, 'Question inconnue.');
        }

        $parametre = $donnees['parametre'] ?? null;
        if ($assertion->parametree && ($parametre === null || $parametre === '')) {
            abort(422, 'Cette question demande un paramètre.');
        }
        if (! $assertion->parametree) {
            $parametre = null;
        }

        $droit = AccessRule::where('organization_id', $organisme->id)
            ->where('assertion_id', $assertion->id)
            ->where('finalite', $donnees['finalite'])
            ->where('valide_par_source', true)
            ->where(fn ($q) => $q->whereNull('expire_le')->orWhere('expire_le', '>', now()))
            ->exists();

        if (! $droit) {
            $this->refuser($agent, 'droit_absent', $donnees, 403, 'Votre organisme n\'a pas le droit de poser cette question pour cette finalité.', $assertion);
        }

        $cle = 'demande:'.$agent->id;
        if (RateLimiter::tooManyAttempts($cle, (int) config('kuma.quota_demandes_par_minute'))) {
            $this->refuser($agent, 'quota_depasse', $donnees, 429, 'Trop de demandes en peu de temps. Réessayez dans un instant.', $assertion);
        }
        RateLimiter::hit($cle, 60);

        // Le citoyen est désigné par son seul identifiant unique : l'administration
        // n'a besoin d'aucune autre donnée personnelle pour poser sa question.
        $identifiant = $this->nettoyer($donnees['citoyen']['identifiant'] ?? null);
        if ($identifiant === null) {
            abort(422, 'L\'identifiant du citoyen est requis.');
        }

        $sujet = $this->pseudonymes->pour($identifiant);

        $demande = VerificationRequest::create([
            'reference' => (string) Str::ulid(),
            'lot' => $donnees['lot'] ?? null,
            'organization_id' => $organisme->id,
            'user_id' => $agent->id,
            'assertion_id' => $assertion->id,
            'parametre' => $parametre,
            'finalite' => $donnees['finalite'],
            'statut' => Statut::RECUE,
            'sujet_pseudo' => $sujet,
            'identifiant_enc' => $identifiant,
        ]);

        // Si la demande vient d'une clé d'API : canal, identifiant de clé et, le cas
        // échéant, la signature de l'organisme, conservés comme preuve de l'émetteur.
        $this->journaliser($demande, $assertion, 'demande.creee', $agent, [
            'question' => $assertion->code,
            'finalite' => $demande->finalite,
            'regime' => $assertion->regime,
        ] + array_intersect_key($trace, array_flip(['canal', 'cle', 'signature'])), 'accepte');

        if ($assertion->exigeConsentement()) {
            // Sans compte actif, le citoyen ne peut pas consentir en ligne. Le consentement
            // n'est jamais contourné : la demande attend, et l'agent en voit la raison.
            $citoyen = $this->compteCitoyen($identifiant);
            $compte = $citoyen !== null;

            $demande->update([
                'statut' => Statut::EN_ATTENTE_CONSENTEMENT,
                'motif' => $compte === false ? 'citoyen_sans_compte' : null,
            ]);
            // Le code n'est créé que si le citoyen peut le recevoir : sinon personne ne le lirait.
            $code = $citoyen !== null && $this->notificateur->joignable($citoyen)
                ? str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT)
                : null;
            Consent::create([
                'verification_request_id' => $demande->id,
                'sujet_pseudo' => $sujet,
                'statut' => 'en_attente',
                'expire_le' => now()->addHours((int) config('kuma.consentement.validite_heures')),
                'code_hash' => $code === null ? null : $this->empreinteCode($demande, $code),
                'code_expire_le' => $code === null ? null : now()->addMinutes((int) config('kuma.notifications.code_validite_minutes')),
            ]);
            $this->journaliser($demande, $assertion, 'consentement.demande', $agent, [
                'question' => $assertion->code,
                'finalite' => $demande->finalite,
                'compte_citoyen' => $compte,
            ], 'en_attente');

            // Le citoyen est prévenu par message. Une panne d'envoi ne doit jamais
            // bloquer la demande : il la retrouvera de toute façon dans l'application.
            if ($citoyen !== null) {
                try {
                    $sms = $this->notificateur->demandeEnAttente($demande, $citoyen, $code);
                    if ($sms !== null) {
                        $this->journaliser($demande, $assertion, 'notification.envoyee', null, ['canal' => $sms->canal], 'envoye');
                    }
                } catch (\Throwable $e) {
                    report($e);
                    $this->journaliser($demande, $assertion, 'notification.echec', null, ['canal' => (string) config('kuma.notifications.canal')], 'echec');
                }

                // Notification push sur son téléphone, sans aucune donnée dans le message.
                $this->notifierPush($demande, $assertion, fn () => $this->push->demandeAccord($citoyen));
            }

            return $demande->refresh();
        }

        return $this->traiter($demande);
    }

    /**
     * Interroge le registre et calcule la réponse minimale. Appelée à la création,
     * après un consentement, et par la reprise automatique.
     */
    public function traiter(VerificationRequest $demande): VerificationRequest
    {
        $assertion = $demande->assertion;
        $demande->update([
            'statut' => Statut::EN_COURS,
            'tentatives' => $demande->tentatives + 1,
        ]);

        $recherche = new Recherche($demande->identifiant_enc);
        $resultat = $this->registres->interroger($assertion->registre, $recherche);

        if ($resultat->estIndisponible()) {
            return $this->mettreEnAttente($demande, $assertion, $resultat);
        }

        $reponse = 'impossible';
        $motif = null;
        $statut = Statut::TERMINEE;

        switch ($resultat->statut) {
            case ResultatRegistre::INTROUVABLE:
                $motif = 'introuvable';
                break;
            case ResultatRegistre::AMBIGU:
                $motif = 'ambigu';
                $statut = Statut::AMBIGUE;
                break;
            default:
                [$reponse, $motif] = $this->calculer($demande, $assertion, $resultat->donnees);
        }

        // Sur base légale, le citoyen n'a rien validé : il est prévenu de la consultation.
        // Son compte est cherché avant que l'identifiant ne soit effacé.
        $aPrevenir = $assertion->exigeConsentement() ? null : $this->compteCitoyen($demande->identifiant_enc);

        // Ici la donnée brute disparaît : seul le résultat minimal est conservé.
        $demande->update([
            'statut' => $statut,
            'reponse' => $reponse,
            'motif' => $motif,
            'source' => $assertion->registre,
            'traite_le' => now(),
            'prochaine_tentative_at' => null,
            'identifiant_enc' => null,
            'date_naissance_enc' => null,
            'recherche_enc' => null,
        ]);

        $this->journaliser($demande, $assertion, 'donnee.consultee', null, [
            'question' => $assertion->code,
            'finalite' => $demande->finalite,
            'regime' => $assertion->regime,
            'registre' => $assertion->registre,
            'reponse' => $reponse,
            'motif' => $motif,
        ], $reponse);

        if ($reponse === 'oui') {
            $this->emettreAttestation($demande, $assertion);
        }

        if ($aPrevenir !== null && $resultat->statut === ResultatRegistre::TROUVE) {
            $this->notifierPush($demande, $assertion, fn () => $this->push->consultation($aPrevenir));
        }

        return $demande->refresh();
    }

    /**
     * Envoie une notification push et la journalise. Une panne de ntfy ne bloque rien.
     *
     * @param  callable(): bool  $envoi
     */
    private function notifierPush(VerificationRequest $demande, Assertion $assertion, callable $envoi): void
    {
        if (! $this->push->actif()) {
            return;
        }
        $ok = $envoi();
        $this->journaliser($demande, $assertion, $ok ? 'notification.envoyee' : 'notification.echec', null, ['canal' => 'push'], $ok ? 'envoye' : 'echec');
    }

    /**
     * Relance les demandes mises en attente par une panne, quand leur heure est venue.
     * Retire aussi les consentements non décidés à temps.
     */
    public function reprendre(): int
    {
        $this->expirerConsentements();

        $reprises = 0;
        VerificationRequest::query()
            ->where('statut', Statut::EN_ATTENTE_REGISTRE)
            ->with('assertion')
            ->orderBy('id')
            ->each(function (VerificationRequest $demande) use (&$reprises) {
                // Une demande repart quand son heure est venue, OU dès que la sonde de
                // santé voit le registre revenir : inutile d'attendre la fin d'un délai
                // devenu trop long alors que le registre répond déjà.
                if ($this->echeanceAtteinte($demande) || $this->registreRevenu($demande)) {
                    $this->traiter($demande);
                    $reprises++;
                }
            });

        return $reprises;
    }

    private function echeanceAtteinte(VerificationRequest $demande): bool
    {
        return $demande->prochaine_tentative_at === null || $demande->prochaine_tentative_at->lte(now());
    }

    /**
     * Le registre est en service, circuit refermé, et cela a été constaté après la
     * dernière tentative de cette demande.
     */
    private function registreRevenu(VerificationRequest $demande): bool
    {
        $sante = RegistryHealth::query()->where('registre', $demande->assertion->registre)->first();

        return $sante !== null
            && $sante->etat === 'en_service'
            && ! $sante->disjoncteurOuvert()
            && $sante->verifie_le !== null
            && $sante->verifie_le->gte($demande->updated_at);
    }

    public function decider(User $citoyen, Consent $consentement, bool $accepte): VerificationRequest
    {
        $demande = $consentement->demande;
        $assertion = $demande->assertion;

        if ($consentement->statut !== 'en_attente' || now()->gt($consentement->expire_le)) {
            abort(409, 'Cette demande n\'attend plus de réponse.');
        }

        $consentement->update([
            'statut' => $accepte ? 'accepte' : 'refuse',
            'decide_le' => now(),
        ]);

        $this->journaliser($demande, $assertion, $accepte ? 'consentement.accepte' : 'consentement.refuse', $citoyen, [
            'question' => $assertion->code,
            'finalite' => $demande->finalite,
        ], $accepte ? 'accepte' : 'refuse');

        if ($accepte) {
            return $this->traiter($demande);
        }

        $demande->update([
            'statut' => Statut::REFUSEE_CITOYEN,
            'traite_le' => now(),
            'identifiant_enc' => null,
            'date_naissance_enc' => null,
            'recherche_enc' => null,
        ]);

        return $demande->refresh();
    }

    /**
     * Le citoyen sans smartphone donne à l'agent le code reçu par SMS. Seul le détenteur
     * du numéro enregistré a pu le lire : le code vaut donc accord du citoyen.
     *
     * Un mauvais code compte comme un essai. Au troisième, le code est annulé :
     * le citoyen devra décider dans l'application.
     */
    public function accepterParCode(User $agent, VerificationRequest $demande, string $code): VerificationRequest
    {
        $consentement = $demande->consentement;
        $assertion = $demande->assertion;
        $max = (int) config('kuma.notifications.code_essais_max');

        if ($consentement === null || $consentement->statut !== 'en_attente' || now()->gt($consentement->expire_le)) {
            abort(409, 'Cette demande n\'attend plus de réponse.');
        }
        if ($consentement->code_hash === null) {
            abort(409, 'Aucun code n\'a été envoyé pour cette demande.');
        }
        if ($consentement->code_essais >= $max) {
            abort(423, 'Trop d\'essais : le citoyen doit décider dans l\'application.');
        }
        if (now()->gt($consentement->code_expire_le)) {
            abort(410, 'Ce code a expiré.');
        }

        if (! hash_equals($consentement->code_hash, $this->empreinteCode($demande, $code))) {
            $consentement->increment('code_essais');
            $reste = $max - $consentement->code_essais;
            $this->journaliser($demande, $assertion, 'consentement.code_refuse', $agent, ['essais_restants' => max(0, $reste)], 'refuse');
            if ($reste <= 0) {
                abort(423, 'Trop d\'essais : le citoyen doit décider dans l\'application.');
            }
            abort(422, 'Code incorrect. Il reste '.$reste.' essai'.($reste > 1 ? 's' : '').'.');
        }

        $consentement->update(['statut' => 'accepte', 'decide_le' => now(), 'code_hash' => null]);
        $this->journaliser($demande, $assertion, 'consentement.accepte', $agent, [
            'question' => $assertion->code,
            'finalite' => $demande->finalite,
            'canal' => 'code_sms',
        ], 'accepte');

        return $this->traiter($demande);
    }

    /** Le code est lié à la demande : celui d'une autre demande ne passe pas. */
    private function empreinteCode(VerificationRequest $demande, string $code): string
    {
        return hash_hmac('sha256', $demande->reference.'|'.$code, (string) config('app.key'));
    }

    /**
     * Le citoyen retire son consentement : l'attestation déjà émise est révoquée.
     */
    public function retirer(User $citoyen, Consent $consentement): void
    {
        if ($consentement->statut !== 'accepte') {
            abort(409, 'Seul un consentement accepté peut être retiré.');
        }

        $demande = $consentement->demande;
        $assertion = $demande->assertion;

        $consentement->update(['statut' => 'retire', 'decide_le' => now()]);
        Attestation::where('verification_request_id', $demande->id)
            ->whereNull('revoquee_le')
            ->update(['revoquee_le' => now()]);

        $this->journaliser($demande, $assertion, 'consentement.retire', $citoyen, [
            'question' => $assertion->code,
            'finalite' => $demande->finalite,
        ], 'retire');
    }

    /**
     * @param  array<string, mixed>  $donnees  donnée brute, locale à cette méthode
     * @return array{0: string, 1: string|null}
     */
    private function calculer(VerificationRequest $demande, Assertion $assertion, array $donnees): array
    {
        $verdict = EvaluateurRegle::evaluer($assertion->regle, $donnees, $demande->parametre);

        return match ($verdict) {
            true => ['oui', null],
            false => ['non', null],
            null => ['impossible', 'donnee_indisponible'],
        };
    }

    private function mettreEnAttente(VerificationRequest $demande, Assertion $assertion, ResultatRegistre $resultat): VerificationRequest
    {
        $delais = (array) config('kuma.reprise.delais_secondes', [10, 30, 60]);
        $rang = min($demande->tentatives - 1, count($delais) - 1);

        if ($demande->tentatives >= (int) config('kuma.reprise.max_tentatives', 12)) {
            $demande->update([
                'statut' => Statut::EXPIREE,
                'motif' => 'registre_indisponible',
                'traite_le' => now(),
                'identifiant_enc' => null,
                'date_naissance_enc' => null,
                'recherche_enc' => null,
            ]);
        } else {
            // Jamais de réponse inventée : la demande attend le retour du registre.
            $demande->update([
                'statut' => Statut::EN_ATTENTE_REGISTRE,
                'prochaine_tentative_at' => now()->addSeconds((int) $delais[max(0, $rang)]),
            ]);
        }

        $this->journaliser($demande, $assertion, 'registre.indisponible', null, [
            'question' => $assertion->code,
            'registre' => $assertion->registre,
            'tentative' => $demande->tentatives,
            'cause' => $resultat->detail,
        ], 'indisponible');

        return $demande->refresh();
    }

    private function emettreAttestation(VerificationRequest $demande, Assertion $assertion): void
    {
        $organisme = $demande->organization;
        $validite = now()->addMinutes($assertion->validite_minutes);
        $reference = (string) Str::ulid();

        $jeton = $this->signataire->signer([
            'v' => 1,
            'ref' => $reference,
            'question' => $assertion->code,
            'parametre' => $demande->parametre,
            'reponse' => $demande->reponse,
            'source' => $assertion->registre,
            'demandeur' => $organisme->sigle,
            'emise_le' => now()->utc()->toIso8601ZuluString(),
            'valide_jusqu_au' => $validite->utc()->toIso8601ZuluString(),
            'sujet' => substr($demande->sujet_pseudo, 0, 16),
        ]);

        Attestation::create([
            'reference' => $reference,
            'verification_request_id' => $demande->id,
            'jeton' => $jeton,
            'kid' => $this->signataire->cleActive(),
            'valide_jusqu_au' => $validite,
        ]);

        $this->journaliser($demande, $assertion, 'attestation.emise', null, [
            'question' => $assertion->code,
            'attestation' => $reference,
        ], 'emise');
    }

    private function expirerConsentements(): void
    {
        Consent::query()
            ->where('statut', 'en_attente')
            ->where('expire_le', '<', now())
            ->each(function (Consent $consentement) {
                $demande = $consentement->demande;
                $consentement->update(['statut' => 'expire']);
                $demande->update([
                    'statut' => Statut::EXPIREE,
                    'motif' => 'consentement_non_donne',
                    'traite_le' => now(),
                    'identifiant_enc' => null,
                    'date_naissance_enc' => null,
                    'recherche_enc' => null,
                ]);
                $this->journaliser($demande, $demande->assertion, 'consentement.expire', null, [
                    'question' => $demande->assertion->code,
                ], 'expire');
            });
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function journaliser(VerificationRequest $demande, Assertion $assertion, string $action, ?User $acteur, array $details, ?string $resultat): void
    {
        $this->journal->ecrire($action, [
            'acteur_type' => $acteur === null ? 'systeme' : $acteur->role,
            'acteur_id' => $acteur?->id,
            'organization_id' => $demande->organization_id,
            'objet_type' => 'demande',
            'objet_ref' => $demande->reference,
            'sujet_pseudo' => $demande->sujet_pseudo,
            'details' => $details,
            'resultat' => $resultat,
        ]);
    }

    /**
     * Journalise un refus (droit absent, quota...) puis répond par une erreur HTTP.
     *
     * @param  array<string, mixed>  $donnees
     */
    private function refuser(User $agent, string $cause, array $donnees, int $code, string $message, ?Assertion $assertion = null): never
    {
        $this->journal->ecrire('demande.refusee', [
            'acteur_type' => $agent->role,
            'acteur_id' => $agent->id,
            'organization_id' => $agent->organization_id,
            'objet_type' => 'demande',
            'details' => [
                'question' => $assertion?->code ?? Arr::get($donnees, 'question'),
                'finalite' => Arr::get($donnees, 'finalite'),
                'cause' => $cause,
            ],
            'resultat' => 'refuse',
        ]);

        abort($code, $message);
    }

    /** Le compte actif du citoyen désigné par cet identifiant, s'il en a un. */
    private function compteCitoyen(?string $identifiant): ?User
    {
        if ($identifiant === null) {
            return null;
        }

        return User::query()
            ->where('role', 'citoyen')
            ->where('identifiant', $identifiant)
            ->where('actif', true)
            ->first();
    }

    private function nettoyer(mixed $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }
        $valeur = trim((string) $valeur);

        return $valeur === '' ? null : $valeur;
    }
}
