<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Point de contrôle signé du journal. En ajout seul, comme le journal.
 */
class JournalCheckpoint extends Model
{
    protected $fillable = ['dernier_id', 'hash', 'total', 'kid', 'jeton'];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Un point de contrôle ne se modifie pas.');
        });

        static::deleting(function () {
            throw new LogicException('Un point de contrôle ne se supprime pas.');
        });
    }
}
