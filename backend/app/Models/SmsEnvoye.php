<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsEnvoye extends Model
{
    protected $table = 'sms_envoyes';

    protected $fillable = [
        'user_id', 'verification_request_id', 'canal', 'destinataire', 'message', 'statut',
    ];

    protected function casts(): array
    {
        return [
            'destinataire' => 'encrypted',
        ];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
