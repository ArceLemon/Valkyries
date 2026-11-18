<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assertion extends Model
{
    protected $fillable = [
        'code', 'libelle', 'registre', 'regle', 'sensibilite', 'regime',
        'validite_minutes', 'parametree', 'publie', 'description', 'base_juridique',
    ];

    protected function casts(): array
    {
        return [
            'parametree' => 'boolean',
            'publie' => 'boolean',
            'validite_minutes' => 'integer',
        ];
    }

    public function exigeConsentement(): bool
    {
        return $this->regime === 'consentement';
    }
}
