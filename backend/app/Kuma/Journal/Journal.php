<?php

namespace App\Kuma\Journal;

use App\Kuma\Preuves\Signataire;
use App\Models\AuditLog;
use App\Models\JournalCheckpoint;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Journal chaîné. Chaque ligne contient l'empreinte SHA-256 de la précédente :
 * modifier ou supprimer une ligne casse la chaîne, et verifier() le détecte.
 *
 * Règle d'or : aucune donnée brute (date de naissance, nom, identifiant, contenu
 * d'un registre) ne doit entrer dans le journal. Une garde le refuse.
 */
final class Journal
{
    /** Clés interdites dans le détail d'une ligne, à tous les niveaux. */
    private const INTERDITES = [
        'identifiant', 'nom', 'prenoms', 'date_naissance', 'lieu_naissance',
        'condamnations', 'nationalite', 'donnees', 'fiche', 'statut_vital',
    ];

    /** Numéro du verrou PostgreSQL qui sérialise les écritures du journal. */
    private const VERROU = 4_247_011;

    private const GENESE ='0000000000000000000000000000000000000000000000000000000000000000';

    public function __construct(private readonly Signataire $signataire) {}

    /**
     * @param  array{acteur_type?: string, acteur_id?: int|string|null, organization_id?: int|null, objet_type?: string|null, objet_ref?: string|null, sujet_pseudo?: string|null, details?: array<string, mixed>, resultat?: string|null}  $contexte
     */
    public function ecrire(string $action, array $contexte = []): AuditLog
    {
        $details = $contexte['details'] ?? [];
        $this->refuserDonneesBrutes($details);

        return DB::transaction(function () use ($action, $contexte, $details) {
            // Une seule écriture à la fois : sinon deux requêtes simultanées liraient la même
            // dernière ligne et la chaîne bifurquerait. PostgreSQL accepte des écritures en
            // parallèle, d'où ce verrou (libéré à la fin de la transaction). SQLite n'a
            // qu'un écrivain à la fois et n'en a pas besoin.
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('SELECT pg_advisory_xact_lock(?)', [self::VERROU]);
            }

            $precedent = AuditLog::query()->orderByDesc('id')->first();
            $prevHash = $precedent?->hash ?? self::GENESE;

            $champs = [
                'horodatage' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
                'acteur_type' => $contexte['acteur_type'] ?? 'systeme',
                'acteur_id' => isset($contexte['acteur_id']) ? (string) $contexte['acteur_id'] : null,
                'organization_id' => isset($contexte['organization_id']) ? (int) $contexte['organization_id'] : null,
                'action' => $action,
                'objet_type' => $contexte['objet_type'] ?? null,
                'objet_ref' => $contexte['objet_ref'] ?? null,
                'sujet_pseudo' => $contexte['sujet_pseudo'] ?? null,
                'details' => $details,
                'resultat' => $contexte['resultat'] ?? null,
            ];

            return AuditLog::create($champs + [
                'prev_hash' => $prevHash,
                'hash' => self::calculer($prevHash, $champs),
            ]);
        });
    }

    /**
     * Recalcule toute la chaîne et signale la première rupture.
     *
     * @return array{valide: bool, total: int, rupture: array{id: int, motif: string}|null}
     */
    public function verifier(): array
    {
        $precedent = self::GENESE;
        $total = 0;

        foreach (AuditLog::query()->orderBy('id')->cursor() as $ligne) {
            $total++;

            if ($ligne->prev_hash !== $precedent) {
                return ['valide' => false, 'total' => $total, 'rupture' => ['id' => $ligne->id, 'motif' => 'chainage_rompu']];
            }

            $attendu = self::calculer($precedent, self::champs($ligne));
            if (! hash_equals($attendu, $ligne->hash)) {
                return ['valide' => false, 'total' => $total, 'rupture' => ['id' => $ligne->id, 'motif' => 'contenu_modifie']];
            }

            $precedent = $ligne->hash;
        }

        // La chaîne seule ne suffit pas : quelqu'un qui peut écrire en base pourrait
        // recalculer toutes les empreintes. Les points de contrôle signés, que seule
        // la clé privée peut produire, ferment cette porte.
        $ruptureDuPoint = $this->verifierPointsDeControle();
        if ($ruptureDuPoint !== null) {
            return ['valide' => false, 'total' => $total, 'rupture' => $ruptureDuPoint];
        }

        return ['valide' => true, 'total' => $total, 'rupture' => null, 'points_de_controle' => JournalCheckpoint::count()];
    }

    /**
     * Signe l'empreinte de tête du journal. Chaque point référence le précédent :
     * en supprimer un au milieu casse la suite. Ne fait rien si rien n'a changé.
     */
    public function creerPointDeControle(): ?JournalCheckpoint
    {
        $derniere = AuditLog::query()->orderByDesc('id')->first();
        if ($derniere === null) {
            return null;
        }

        $precedent = JournalCheckpoint::query()->orderByDesc('id')->first();
        if ($precedent !== null && (int) $precedent->dernier_id === (int) $derniere->id) {
            return null;
        }

        $total = AuditLog::query()->where('id', '<=', $derniere->id)->count();

        $jeton = $this->signataire->signer([
            'v' => 1,
            'type' => 'point_de_controle',
            'dernier_id' => $derniere->id,
            'hash' => $derniere->hash,
            'total' => $total,
            'precedent' => $precedent === null ? null : hash('sha256', $precedent->jeton),
            'emis_le' => now()->utc()->toIso8601ZuluString(),
        ]);

        return JournalCheckpoint::create([
            'dernier_id' => $derniere->id,
            'hash' => $derniere->hash,
            'total' => $total,
            'kid' => $this->signataire->cleActive(),
            'jeton' => $jeton,
        ]);
    }

    /**
     * @return array{id: int, motif: string}|null
     */
    private function verifierPointsDeControle(): ?array
    {
        $jetonPrecedent = null;

        foreach (JournalCheckpoint::query()->orderBy('id')->cursor() as $point) {
            $verdict = $this->signataire->verifier($point->jeton);
            $charge = $verdict['charge'];
            $repere = (int) ($charge['dernier_id'] ?? $point->dernier_id);

            if ($verdict['etat'] !== Signataire::ETAT_VALIDE || $charge === null) {
                return ['id' => $repere, 'motif' => 'point_de_controle_signature_invalide'];
            }

            $attendu = $jetonPrecedent === null ? null : hash('sha256', $jetonPrecedent);
            if (($charge['precedent'] ?? null) !== $attendu) {
                return ['id' => $repere, 'motif' => 'point_de_controle_chainage_rompu'];
            }

            $ligne = AuditLog::query()->find($repere);
            if ($ligne === null) {
                return ['id' => $repere, 'motif' => 'point_de_controle_ligne_absente'];
            }

            $total = AuditLog::query()->where('id', '<=', $ligne->id)->count();
            if (! hash_equals((string) ($charge['hash'] ?? ''), $ligne->hash) || $total !== (int) ($charge['total'] ?? -1)) {
                return ['id' => $repere, 'motif' => 'point_de_controle_incoherent'];
            }

            $jetonPrecedent = $point->jeton;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $champs
     */
    public static function calculer(string $prevHash, array $champs): string
    {
        $details = $champs['details'] ?? [];
        self::trier($details);
        $champs['details'] = $details;

        return hash('sha256', $prevHash."\n".json_encode(
            $champs,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private static function champs(AuditLog $ligne): array
    {
        return [
            'horodatage' => $ligne->horodatage,
            'acteur_type' => $ligne->acteur_type,
            'acteur_id' => $ligne->acteur_id,
            'organization_id' => $ligne->organization_id,
            'action' => $ligne->action,
            'objet_type' => $ligne->objet_type,
            'objet_ref' => $ligne->objet_ref,
            'sujet_pseudo' => $ligne->sujet_pseudo,
            'details' => $ligne->details ?? [],
            'resultat' => $ligne->resultat,
        ];
    }

    /**
     * @param  array<mixed>  $tableau
     */
    private static function trier(array &$tableau): void
    {
        ksort($tableau);
        foreach ($tableau as &$valeur) {
            if (is_array($valeur)) {
                self::trier($valeur);
            }
        }
    }

    /**
     * @param  array<mixed>  $details
     */
    private function refuserDonneesBrutes(array $details): void
    {
        foreach ($details as $cle => $valeur) {
            if (is_string($cle) && in_array($cle, self::INTERDITES, true)) {
                throw new LogicException("Donnée brute refusée dans le journal : {$cle}");
            }
            if (is_array($valeur)) {
                $this->refuserDonneesBrutes($valeur);
            }
        }
    }
}
