<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Une démarche : une liste de questions et une finalité (ex. création d'entreprise).
 */
class Procedure extends Model
{
    protected $fillable = ['code', 'libelle', 'finalite'];

    public function assertions(): BelongsToMany
    {
        return $this->belongsToMany(Assertion::class, 'procedure_assertions')
            ->withPivot('parametre');
    }
}
