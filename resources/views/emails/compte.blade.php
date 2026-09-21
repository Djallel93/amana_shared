{{-- vendor/amana-shared/resources/views/emails/compte.blade.php --}}
{{--
Gabarit unique des emails de sécurité du compte (changement d'email / de mot
de passe) — voir Notifications\CompteNotification.

Variables :
  $badge, $title, $titleSub, $prenom, $logoCid, $footerText
  $paragraphs  : string[] — texte brut (échappé ici)
  $ctaUrl, $ctaLabel, $ctaNote  : bouton optionnel
  $warnTitle, $warnText         : encart « ce n'était pas vous ? » optionnel
--}}
<!DOCTYPE html>
<html lang="fr" dir="ltr">

<head>
    <title>{{ strip_tags($title) }} — {{ $titleSub }}</title>
    @include('amana-shared::emails.partials._head')
</head>

<body>
    <div class="shell">
        <div class="wrapper">

            @include('amana-shared::emails.partials._header', [
                'badge' => $badge,
                'title' => e($title),
                'titleSub' => $titleSub,
            ])

            <div class="stripe"></div>

            <div class="body">

                <p class="greeting">Cher(e) <em>{{ $prenom }}</em>,</p>

                @foreach($paragraphs as $paragraphe)
                    <p class="body-text">{{ $paragraphe }}</p>
                @endforeach

                @if(!empty($ctaUrl))
                    <div class="cta-wrap">
                        <a href="{{ $ctaUrl }}" class="cta-button">{{ $ctaLabel ?? 'Continuer' }}</a>
                        @if(!empty($ctaNote))
                            <p class="cta-note">{{ $ctaNote }}</p>
                        @endif
                    </div>
                @endif

                @if(!empty($warnTitle))
                    <table class="hint-box" role="presentation" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td class="hint-icon">⚠️</td>
                            <td class="hint-text">
                                <strong>{{ $warnTitle }}</strong><br>
                                {{ $warnText ?? '' }}
                            </td>
                        </tr>
                    </table>
                @endif

            </div>

            @include('amana-shared::emails.partials._footer', ['footerText' => $footerText ?? null])

        </div>
    </div>
</body>

</html>
