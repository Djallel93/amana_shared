{{-- resources/views/emails/partials/_footer.blade.php --}}
{{--
Contenu (footerText) et couleur du séparateur (via .footer-divider, définie
dans _head.blade.php par app) sont les deux seules choses qui différaient
entre planning/familles avant leur centralisation le 04/08/2026 — le reste
de la structure était déjà identique.

Variable optionnelle :
$footerText — texte au-dessus de l'adresse de contact. Si omis, retombe sur
config('amana-shared.branding.email_footer_text') pour ne pas avoir à
modifier chaque site d'appel existant.
--}}
<div class="footer">
    <div class="footer-logo">AMANA</div>
    <div class="footer-divider"></div>
    <p>
        {{ $footerText ?? config('amana-shared.branding.email_footer_text') }}<br>
        Pour toute question&nbsp;:
        <a href="mailto:{{ config('amana-shared.branding.contact_email', 'amana44.benevole@gmail.com') }}">{{ config('amana-shared.branding.contact_email', 'amana44.benevole@gmail.com') }}</a>
    </p>
</div>
