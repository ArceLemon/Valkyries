<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiCredential extends Model
{
    protected $fillable = [
        'organization_id', 'user_id', 'nom', 'kid', 'secret_hash', 'cle_publique',
        'questions', 'quota_par_minute', 'expire_le', 'revoquee_le', 'dernier_usage_le', 'cree_par',
    ];

    protected $hidden = ['secret_hash'];

    protected function casts(): array
    {
        return [
            'questions' => 'array',
            'quota_par_minute' => 'integer',
            'expire_le' => 'datetime',
            'revoquee_le' => 'datetime',
            'dernier_usage_le' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function technique(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function estUtilisable(): bool
    {
        return $this->revoquee_le === null
            && ($this->expire_le === null || now()->lt($this->expire_le));
    }

    public function signatureObligatoire(): bool
    {
        return $this->cle_publique !== null;
    }

    public function autorise(string $question): bool
    {
        return $this->questions === null || in_array($question, $this->questions, true);
    }
}
