{{-- vendor/amana-shared/resources/views/layouts/partials/avatar.blade.php --}}
{{--
Pastille d'initiales — UNE seule vue pour le bloc marque de la sidebar
(desktop), la topbar mobile et l'en-tête de la page « Mon profil ».

Variables :
  $personne : Amana\Shared\Models\Personne (ou sous-classe)
  $size     : int — diamètre en px (défaut 38) ; taille posée en style inline
  $actif    : bool — anneau d'accent (page de profil courante)

Initiales et couleur viennent des accesseurs de Personne (`initiales`,
`couleur_avatar`) : la couleur dérive de l'ID de ref_personnes, donc la même
personne a la même pastille dans toutes les apps. Fond en `style` inline
(HSL) : aucun safelist Tailwind à maintenir. Décorative (aria-hidden) : c'est
le lien qui l'englobe qui porte le libellé accessible.
--}}
@php
    $taille = (int) ($size ?? 38);
@endphp
<span
    class="inline-flex items-center justify-center rounded-full flex-shrink-0 select-none text-white font-bold leading-none ring-2 {{ ($actif ?? false) ? 'ring-accent-light' : 'ring-white/25' }}"
    style="width:{{ $taille }}px;height:{{ $taille }}px;font-size:{{ max(11, (int) round($taille * 0.38)) }}px;background-color:{{ $personne->couleur_avatar }}"
    aria-hidden="true">{{ $personne->initiales }}</span>
