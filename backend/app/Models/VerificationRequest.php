<?php

namespace App\Models;

use App\Kuma\Statut;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VerificationRequest extends Model
{
    protected $fillable = [
        'reference', 'lot', 'organization_id', 'user_id', 'assertion_id', 'parametre',
        'finalite', 'statut', 'reponse', 'motif', 'source', 'sujet_pseudo',
        'identifiant_enc', 'date_naissance_enc', 'recherche_enc',
        'tentatives', 'prochaine_tentative_at', 'traite_le',
    ];

    protected function casts(): array
    {
        return [
            // Chiffrés au repos, effacés dès que la demande est terminée
            'identifiant_enc' => 'encrypted',
            'date_naissance_enc' => 'encrypted',
            'recherche_enc' => 'encrypted:array',
            'prochaine_tentative_at' => 'datetime',
            'traite_le' => 'datetime',
            'tentatives' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assertion(): BelongsTo
    {
        return $this->belongsTo(Assertion::class);
    }

    public function consentement(): HasOne
    {
        return $this->hasOne(Consent::class);
    }

    public function attestation(): HasOne
    {
        return $this->hasOne(Attestation::class);
    }

    public function estFinale(): bool
    {
        return in_array($this->statut, Statut::FINAUX, true);
    }

    /**
     * Ce que voit l'administration demandeuse : la réponse utile, rien d'autre.
     *
     * @return array<string, mixed>
     */
    public function vue(): array
    {
        $this->loadMissing(['assertion', 'attestation', 'consentement']);

        return [
            'reference' => $this->reference,
            'lot' => $this->lot,
            'question' => $this->assertion->code,
            'libelle' => $this->assertion->libelle,
            'parametre' => $this->parametre,
            'finalite' => $this->finalite,
            'statut' => $this->statut,
            'reponse' => $this->reponse,
            'motif' => $this->motif,
            'source' => $this->source,
            'consentement' => $this->consentement?->statut,
            // Un code SMS est-il attendu ? L'agent ne le voit jamais, il le reçoit du citoyen.
            'code_attendu' => $this->consentement?->codeAttendu((int) config('kuma.notifications.code_essais_max')) ?? false,
            'attestation' => $this->attestation?->jeton,
            'creee_le' => $this->created_at?->toIso8601String(),
            'traitee_le' => $this->traite_le?->toIso8601String(),
        ];
    }
}
