<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistryHealth extends Model
{
    protected $fillable = [
        'registre', 'etat', 'latence_ms', 'verifie_le', 'echecs_consecutifs', 'ouvert_jusqu_au',
    ];

    protected function casts(): array
    {
        return [
            'verifie_le' => 'datetime',
            'ouvert_jusqu_au' => 'datetime',
            'echecs_consecutifs' => 'integer',
            'latence_ms' => 'integer',
        ];
    }

    public function disjoncteurOuvert(): bool
    {
        return $this->ouvert_jusqu_au !== null && now()->lt($this->ouvert_jusqu_au);
    }
}
