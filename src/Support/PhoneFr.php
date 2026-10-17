<?php
// src/Support/PhoneFr.php

declare(strict_types=1);

namespace Amana\Shared\Support;

/**
 * Format de téléphone français accepté par AMANA — défini UNE fois ici et
 * partagé par la page « Mon profil », les formulaires d'administration de
 * planning (Store/UpdatePersonneRequest) et PersonneIntakeService (intake
 * familles, candidature bénévole planning), qui portaient chacun leur propre
 * copie de cette expression, incohérentes entre elles.
 *
 * Écart volontaire avec l'ancienne expression du formulaire admin planning
 * (/^(\+33|0033|0)[1-9](\s?[0-9]{2}){4}$/) : elle REFUSAIT l'un de ses
 * propres exemples, « +33 6 12 34 56 78 » (l'espace entre l'indicatif et le 6
 * n'y était pas prévu). Ici, un espace optionnel est accepté après
 * +33 / 0033 ; tout ce que l'ancienne expression acceptait reste accepté.
 */
final class PhoneFr
{
    public const REGEX = '/^((\+33|0033)\s?|0)[1-9](\s?[0-9]{2}){4}$/';

    public const MESSAGE = 'Format invalide. Exemples : 06 12 34 56 78, +33 6 12 34 56 78';

    /**
     * Affichage « +33 1 23 45 67 89 » (17/10/2026), quel que soit le format de saisie (0X XX XX XX XX,
     * +33X..., 0033..., avec ou sans espaces/points/tirets). Un numéro qui n'est pas un numéro français
     * valide (étranger, incomplet) est renvoyé TEL QUEL : on ne masque jamais une donnée. Affichage
     * seulement — ne remplace pas la valeur stockée.
     */
    public static function formater(?string $telephone): ?string
    {
        if ($telephone === null || trim($telephone) === '') {
            return null;
        }

        $chiffres = preg_replace('/\D/', '', $telephone) ?? '';

        // Normalise vers 9 chiffres significatifs (sans le 0 ou l'indicatif 33 / 0033).
        if (str_starts_with($chiffres, '0033') && strlen($chiffres) === 13) {
            $chiffres = substr($chiffres, 4);
        } elseif (str_starts_with($chiffres, '33') && strlen($chiffres) === 11) {
            $chiffres = substr($chiffres, 2);
        } elseif (str_starts_with($chiffres, '0') && strlen($chiffres) === 10) {
            $chiffres = substr($chiffres, 1);
        }

        // Un numéro français ne commence jamais par 0 une fois l'indicatif retiré.
        if (strlen($chiffres) !== 9 || $chiffres[0] === '0') {
            return $telephone;
        }

        $groupes = [$chiffres[0]];
        for ($i = 1; $i < 9; $i += 2) {
            $groupes[] = substr($chiffres, $i, 2);
        }

        return '+33 ' . implode(' ', $groupes);
    }

    /**
     * Valeur composable d'un lien `tel:` (17/10/2026) : « +33123456789 » pour un numéro français
     * reconnu, sinon les chiffres saisis (avec leur « + » éventuel). Le texte AFFICHÉ passe par
     * formater() ; le href reste une valeur sans espace, que tous les composeurs comprennent.
     */
    public static function lien(?string $telephone): ?string
    {
        if ($telephone === null || trim($telephone) === '') {
            return null;
        }

        $formate = self::formater($telephone);
        if ($formate !== null && str_starts_with($formate, '+33 ')) {
            return '+33' . preg_replace('/\D/', '', substr($formate, 4));
        }

        $brut = preg_replace('/[^\d+]/', '', $telephone) ?? '';

        return $brut === '' ? null : $brut;
    }
}
