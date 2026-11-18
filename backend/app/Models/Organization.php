<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = ['nom', 'sigle', 'tutelle', 'roles', 'registres', 'statut'];

    protected function casts(): array
    {
        return [
            'roles' => 'array',
            'registres' => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function droits(): HasMany
    {
        return $this->hasMany(AccessRule::class);
    }

    public function estActive(): bool
    {
        return $this->statut === 'actif';
    }

    public function aRole(string $role): bool
    {
        return in_array($role, $this->roles ?? [], true);
    }
}
