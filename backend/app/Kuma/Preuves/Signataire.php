<?php

namespace App\Kuma\Preuves;

use Carbon\Carbon;
use RuntimeException;
use Throwable;

/**
 * Signature des attestations avec Ed25519 (libsodium). Aucun algorithme
 * n'est écrit par l'équipe : on appelle les fonctions éprouvées de libsodium.
 *
 * Jeton d'attestation : base64url(charge utile JSON) . base64url(signature)
 */
final class Signataire
{
    public const ETAT_VALIDE = 'valide';

    public const ETAT_EXPIREE = 'expiree';

    public const ETAT_ALTEREE = 'alteree';

    public const ETAT_CLE_INCONNUE = 'cle_inconnue';

    /**
     * Génère une paire de clés et la rend active. Les anciennes clés publiques
     * restent disponibles pour vérifier les attestations déjà émises.
     */
    public function generer(): string
    {
        $dossier = $this->dossier();
        if (! is_dir($dossier) && ! mkdir($dossier, 0700, true) && ! is_dir($dossier)) {
            throw new RuntimeException("Impossible de créer le dossier des clés : {$dossier}");
        }

        $paire = sodium_crypto_sign_keypair();
        $publique = sodium_crypto_sign_publickey($paire);
        $secrete = sodium_crypto_sign_secretkey($paire);
        $kid = substr(hash('sha256', $publique), 0, 16);

        file_put_contents("{$dossier}/{$kid}.pub", base64_encode($publique));
        file_put_contents("{$dossier}/{$kid}.key", base64_encode($secrete));
        @chmod("{$dossier}/{$kid}.key", 0600);
        file_put_contents("{$dossier}/actif.txt", $kid);

        return $kid;
    }

    public function cleActive(): string
    {
        $fichier = $this->dossier().'/actif.txt';

        return is_file($fichier) ? trim((string) file_get_contents($fichier)) : $this->generer();
    }

    /**
     * @param  array<string, mixed>  $charge
     */
    public function signer(array $charge): string
    {
        $kid = $this->cleActive();
        $charge['kid'] = $kid;
        ksort($charge);

        $json = json_encode($charge, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $secrete = base64_decode((string) file_get_contents($this->dossier()."/{$kid}.key"), true);
        if ($secrete === false) {
            throw new RuntimeException('Clé de signature illisible.');
        }

        $signature = sodium_crypto_sign_detached($json, $secrete);

        return self::encoder($json).'.'.self::encoder($signature);
    }

    /**
     * @return array{etat: string, charge: array<string, mixed>|null}
     */
    public function verifier(string $jeton): array
    {
        $morceaux = explode('.', $jeton);
        if (count($morceaux) !== 2) {
            return ['etat' => self::ETAT_ALTEREE, 'charge' => null];
        }

        $json = self::decoder($morceaux[0]);
        $signature = self::decoder($morceaux[1]);
        $charge = $json === null ? null : json_decode($json, true);

        if ($json === null || $signature === null || ! is_array($charge) || ! isset($charge['kid'])) {
            return ['etat' => self::ETAT_ALTEREE, 'charge' => null];
        }

        $publique = $this->clePublique((string) $charge['kid']);
        if ($publique === null) {
            return ['etat' => self::ETAT_CLE_INCONNUE, 'charge' => null];
        }

        try {
            $bonne = strlen($signature) === SODIUM_CRYPTO_SIGN_BYTES
                && sodium_crypto_sign_verify_detached($signature, $json, $publique);
        } catch (Throwable) {
            $bonne = false;
        }

        if (! $bonne) {
            return ['etat' => self::ETAT_ALTEREE, 'charge' => null];
        }

        $expire = isset($charge['valide_jusqu_au']) ? Carbon::parse($charge['valide_jusqu_au']) : null;
        if ($expire !== null && now()->gt($expire)) {
            return ['etat' => self::ETAT_EXPIREE, 'charge' => $charge];
        }

        return ['etat' => self::ETAT_VALIDE, 'charge' => $charge];
    }

    /**
     * Clés publiques publiées, pour permettre à n'importe qui de vérifier.
     *
     * @return list<array{kid: string, algorithme: string, cle_publique: string}>
     */
    public function clesPubliques(): array
    {
        $cles = [];
        foreach (glob($this->dossier().'/*.pub') ?: [] as $fichier) {
            $cles[] = [
                'kid' => basename($fichier, '.pub'),
                'algorithme' => 'Ed25519',
                'cle_publique' => trim((string) file_get_contents($fichier)),
            ];
        }

        return $cles;
    }

    private function clePublique(string $kid): ?string
    {
        if (! preg_match('/^[a-f0-9]{16}$/', $kid)) {
            return null;
        }
        $fichier = $this->dossier()."/{$kid}.pub";
        if (! is_file($fichier)) {
            return null;
        }
        $cle = base64_decode((string) file_get_contents($fichier), true);

        return $cle === false ? null : $cle;
    }

    private function dossier(): string
    {
        return rtrim((string) config('kuma.cles.dossier'), '/\\');
    }

    private static function encoder(string $brut): string
    {
        return rtrim(strtr(base64_encode($brut), '+/', '-_'), '=');
    }

    private static function decoder(string $texte): ?string
    {
        $brut = base64_decode(strtr($texte, '-_', '+/'), true);

        return $brut === false ? null : $brut;
    }
}
