<?php

namespace App\Kuma\Notifications;

use App\Models\SmsEnvoye;
use App\Models\User;
use App\Models\VerificationRequest;
use Illuminate\Support\Facades\Log;

/**
 * Prévient le citoyen qu'un organisme attend son accord.
 *
 * Le message ne contient jamais de donnée : seulement qui demande, quelle question
 * et jusqu'à quand. Le citoyen ouvre ensuite l'application pour décider.
 *
 * Le canal est réglé par KUMA_NOTIFICATIONS : « simule » range le message en base
 * (démonstration), « aucun » désactive l'envoi. Brancher un opérateur SMS revient à
 * ajouter un cas dans acheminer(), le reste ne change pas.
 */
final class Notificateur
{
    /** Le citoyen peut-il être prévenu par message ? */
    public function joignable(User $citoyen): bool
    {
        return config('kuma.notifications.canal', 'simule') !== 'aucun'
            && $citoyen->telephone !== null && $citoyen->telephone !== '';
    }

    /**
     * @param  string|null  $code  code de confirmation à donner à l'agent, pour qui n'a pas l'application
     */
    public function demandeEnAttente(VerificationRequest $demande, User $citoyen, ?string $code = null): ?SmsEnvoye
    {
        if (! $this->joignable($citoyen)) {
            return null;
        }

        $heures = (int) config('kuma.consentement.validite_heures');
        $message = sprintf(
            'KUMA BANA : %s demande votre accord pour « %s ». Ouvrez l\'application pour accepter ou refuser, sous %d h.',
            $demande->organization->nom,
            $demande->assertion->libelle,
            $heures,
        );
        if ($code !== null) {
            $message .= sprintf(
                ' Sans l\'application, donnez ce code à l\'agent pour accepter : %s (valable %d min). Sinon ne le communiquez à personne.',
                $code,
                (int) config('kuma.notifications.code_validite_minutes'),
            );
        }

        return $this->acheminer((string) config('kuma.notifications.canal', 'simule'), $citoyen, $citoyen->telephone, $message, $demande);
    }

    private function acheminer(string $canal, User $citoyen, string $numero, string $message, VerificationRequest $demande): SmsEnvoye
    {
        // Seul le canal simulé existe pour l'instant : le message est conservé,
        // pas transmis à un opérateur.
        Log::info('Message au citoyen (canal '.$canal.')', ['demande' => $demande->reference]);

        return SmsEnvoye::create([
            'user_id' => $citoyen->id,
            'verification_request_id' => $demande->id,
            'canal' => $canal,
            'destinataire' => $numero,
            'message' => $message,
        ]);
    }
}
