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
}
