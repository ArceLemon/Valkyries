<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Ligne du journal chaîné. Une fois écrite, elle ne se modifie ni ne se supprime
 * par l'application (la base de données peut toujours être altérée de l'extérieur :
 * c'est précisément ce que la vérification de la chaîne détecte).
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'horodatage', 'acteur_type', 'acteur_id', 'organization_id', 'action',
        'objet_type', 'objet_ref', 'sujet_pseudo', 'details', 'resultat',
        'prev_hash', 'hash',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'organization_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Le journal est en ajout seul : aucune ligne ne se modifie.');
        });

        static::deleting(function () {
            throw new LogicException('Le journal est en ajout seul : aucune ligne ne se supprime.');
        });
    }
}
