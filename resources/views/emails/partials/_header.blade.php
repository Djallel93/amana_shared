{{-- resources/views/emails/partials/_header.blade.php --}}
{{--
Variables:
$badge — e.g. 'Candidature validée'
$title — e.g. 'Bienvenue parmi nous !'
$titleSub — e.g. 'AMANA Planning'
--}}
<div class="header">

    <p class="bismillah arabic">بِسْمِ اللهِ الرَّحْمٰنِ الرَّحِيمِ</p>

    <div class="header-logo">
        <img src="{{ $logoCid ?? config('app.url') . '/favicon-96x96.png' }}" alt="AMANA">
    </div>

    <div class="header-brand">AMANA</div>
    <div class="header-sub">Association Musulmane de l'Agglomération Nantaise et ses Alentours</div>

    <p class="salam arabic">السَّلَامُ عَلَيْكُمْ وَرَحْمَةُ اللهِ وَبَرَكَاتُهُ</p>

    <table class="header-divider" role="presentation" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td></td>
            <td class="star">&#10022;</td>
            <td></td>
        </tr>
    </table>

    <div class="header-badge">{{ $badge }}</div>
    <div class="header-title">{!! $title !!}</div>
    <div class="header-title-sub">{{ $titleSub }}</div>

</div>
