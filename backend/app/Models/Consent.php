<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consent extends Model
{
    protected $fillable = [
        'verification_request_id', 'sujet_pseudo', 'statut', 'expire_le', 'decide_le',
        'code_hash', 'code_expire_le', 'code_essais',
    ];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'expire_le' => 'datetime',
            'decide_le' => 'datetime',
            'code_expire_le' => 'datetime',
        ];
    }

    /** Un code est-il attendu : envoyé, pas expiré, pas bloqué ? */
    public function codeAttendu(int $essaisMax): bool
    {
        return $this->statut === 'en_attente'
            && $this->code_hash !== null
            && $this->code_expire_le !== null
            && now()->lt($this->code_expire_le)
            && $this->code_essais < $essaisMax;
    }

    public function demande(): BelongsTo
    {
        return $this->belongsTo(VerificationRequest::class, 'verification_request_id');
    }
}
