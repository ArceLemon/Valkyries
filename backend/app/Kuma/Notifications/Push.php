<?php

namespace App\Kuma\Notifications;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Notifications push vers l'application du citoyen, par un serveur ntfy
 * auto-hébergé (logiciel libre, Apache 2.0 / GPLv2) : aucun service de Google ou
 * d'Apple, aucune dépendance extérieure.
 *
 * Règles de protection :
 *  - chaque citoyen a son propre sujet, dérivé de la clé secrète de la plateforme :
 *    impossible à deviner, et seul son compte connecté peut le connaître ;
 *  - le message ne contient AUCUNE donnée : ni organisme, ni question, ni réponse.
 *    Il dit seulement « ouvrez l'application », qui va chercher le détail par l'API
 *    authentifiée ;
 *  - seul ce serveur publie : ntfy n'est joignable en écriture que depuis le réseau
 *    interne, le téléphone ne peut que s'abonner (voir portail/docker/nginx.conf).
 *
 * Désactivé si KUMA_PUSH_URL est vide. Une panne de ntfy ne bloque jamais une demande.
 */
final class Push
{
    public function actif(): bool
    {
        return (string) config('kuma.push.url') !== '';
    }

    /** Sujet ntfy secret et stable du citoyen. */
    public function sujet(User $citoyen): string
    {
        $cle = (string) (config('kuma.push.cle') ?: config('app.key'));

        return 'kb-'.substr(hash_hmac('sha256', 'push|'.$citoyen->id, $cle), 0, 32);
    }

    public function demandeAccord(User $citoyen): bool
    {
        return $this->envoyer(
            $citoyen,
            'Une administration attend votre accord',
            'Ouvrez KUMA BANA pour accepter ou refuser.',
            'accord',
            'high',
        );
    }

    public function consultation(User $citoyen): bool
    {
        return $this->envoyer(
            $citoyen,
            'Une information vous concernant a été vérifiée',
            'Retrouvez qui l\'a consultée, et pourquoi, dans votre historique.',
            'consultation',
            'default',
        );
    }

    private function envoyer(User $citoyen, string $titre, string $message, string $type, string $priorite): bool
    {
        if (! $this->actif()) {
            return false;
        }

        try {
            $requete = Http::timeout(3)->withHeaders([
                'Title' => $titre,
                'Priority' => $priorite,
                'Tags' => $type,
            ]);
            if ((string) config('kuma.push.jeton') !== '') {
                $requete = $requete->withToken((string) config('kuma.push.jeton'));
            }

            return $requete
                ->withBody($message, 'text/plain; charset=utf-8')
                ->post(rtrim((string) config('kuma.push.url'), '/').'/'.$this->sujet($citoyen))
                ->successful();
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
