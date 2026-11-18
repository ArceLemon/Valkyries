<?php

namespace App\Models;

use App\Kuma\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'organization_id', 'identifiant', 'actif', 'technique', 'telephone',
    ];

    protected $hidden = ['password', 'remember_token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'actif' => 'boolean',
            'technique' => 'boolean',
            'telephone' => 'encrypted',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function estRole(Role ...$roles): bool
    {
        foreach ($roles as $role) {
            if ($this->role === $role->value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Un compte peut se connecter s'il est actif et, pour un agent,
     * si son organisme est actif (la suspension d'un organisme est immédiate).
     */
    public function peutSeConnecter(): bool
    {
        if (! $this->actif) {
            return false;
        }

        if ($this->organization_id === null) {
            return true;
        }

        return $this->organization?->estActive() ?? false;
    }
}
