<?php
// src/Services/EmailChangeLink.php

declare(strict_types=1);

namespace Amana\Shared\Services;

use Amana\Shared\Models\Personne;
use Illuminate\Support\Facades\URL;

/**
 * Lien de confirmation de changement d'adresse email — SANS état (aucune
 * table, aucune colonne dans amana_commun) : tout tient dans une URL signée
 * et expirante (60 min) qui porte l'id de la personne, la nouvelle adresse et
 * une empreinte de l'adresse ACTUELLE.
 *
 * L'empreinte rend le lien à effet unique : une fois l'adresse changée (par ce
 * lien, par un administrateur, par un autre lien), l'empreinte de l'adresse
 * courante ne correspond plus et le lien meurt. C'est un HMAC de l'adresse
 * avec la clé de l'app : on ne peut ni la déduire ni la fabriquer, et le lien
 * n'expose pas l'ancienne adresse en clair.
 */
final class EmailChangeLink
{
    public const ROUTE = 'profile.email.confirm';

    public const VALIDITE_MINUTES = 60;

    public static function make(Personne $personne, string $nouvelEmail): string
    {
        return URL::temporarySignedRoute(
            self::ROUTE,
            now()->addMinutes(self::VALIDITE_MINUTES),
            [
                'id' => $personne->getKey(),
                'email' => $nouvelEmail,
                'fp' => self::fingerprint((string) $personne->email),
            ],
        );
    }

    public static function fingerprint(string $email): string
    {
        return substr(hash_hmac('sha256', mb_strtolower(trim($email)), (string) config('app.key')), 0, 32);
    }

    /** Le lien porte-t-il encore l'empreinte de l'adresse ACTUELLE de la personne ? */
    public static function isCurrent(Personne $personne, string $fingerprint): bool
    {
        return hash_equals(self::fingerprint((string) $personne->email), $fingerprint);
    }
}
