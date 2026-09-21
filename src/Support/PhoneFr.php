<?php
// src/Support/PhoneFr.php

declare(strict_types=1);

namespace Amana\Shared\Support;

/**
 * Format de téléphone français accepté par AMANA — défini UNE fois ici et
 * partagé par la page « Mon profil » et (à terme) les formulaires
 * d'administration, qui portaient chacun leur copie de cette expression.
 * Même message que le formulaire admin de planning (UpdatePersonneRequest).
 *
 * Écart volontaire avec l'expression de ce formulaire admin
 * (/^(\+33|0033|0)[1-9](\s?[0-9]{2}){4}$/) : elle REFUSE l'un de ses propres
 * exemples, « +33 6 12 34 56 78 » (l'espace entre l'indicatif et le 6 n'y est
 * pas prévu). Ici, un espace optionnel est accepté après +33 / 0033 ; tout ce
 * que l'ancienne expression acceptait reste accepté. Le formulaire admin
 * pourra adopter cette constante plus tard (hors périmètre de ce patch).
 */
final class PhoneFr
{
    public const REGEX = '/^((\+33|0033)\s?|0)[1-9](\s?[0-9]{2}){4}$/';

    public const MESSAGE = 'Format invalide. Exemples : 06 12 34 56 78, +33 6 12 34 56 78';
}
