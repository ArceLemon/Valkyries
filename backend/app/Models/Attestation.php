<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attestation extends Model
{
    protected $fillable = [
        'reference', 'verification_request_id', 'jeton', 'kid', 'valide_jusqu_au', 'revoquee_le',
    ];

    protected function casts(): array
    {
        return [
            'valide_jusqu_au' => 'datetime',
            'revoquee_le' => 'datetime',
        ];
    }

    public function demande(): BelongsTo
    {
        return $this->belongsTo(VerificationRequest::class, 'verification_request_id');
    }
}
