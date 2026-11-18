<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessRule extends Model
{
    protected $fillable = [
        'organization_id', 'assertion_id', 'finalite', 'valide_par_source', 'valide_le',
        'justification', 'reference_juridique', 'expire_le',
    ];

    protected function casts(): array
    {
        return [
            'valide_par_source' => 'boolean',
            'valide_le' => 'datetime',
            'expire_le' => 'datetime',
        ];
    }

    /**
     * en_attente (pas encore validé par la source), valide, ou expire.
     */
    public function etat(): string
    {
        if (! $this->valide_par_source) {
            return 'en_attente';
        }

        return $this->expire_le !== null && now()->gte($this->expire_le) ? 'expire' : 'valide';
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function assertion(): BelongsTo
    {
        return $this->belongsTo(Assertion::class);
    }
}
