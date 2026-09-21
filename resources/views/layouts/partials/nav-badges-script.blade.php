{{-- vendor/amana-shared/resources/views/layouts/partials/nav-badges-script.blade.php --}}
{{--
Rafraîchissement en direct des badges de la sidebar (cas 3 de la règle de
polling : page Blade sans Vue/Inertia — un setInterval en ligne).
Émis par sidebar.blade.php UNIQUEMENT si $navBadgesUrl est défini, c.-à-d.
fournisseur NavBadgeProvider lié + route enregistrée par l'app + utilisateur
connecté (voir Http\ViewComposers\SidebarComposer).

Garde-fous :
  - onglet masqué → aucune requête ; rafraîchissement immédiat au retour ;
  - jamais deux requêtes en vol ;
  - échec réseau/serveur → on GARDE les valeurs affichées (aucun flash
    d'erreur) ; 401/403/419 ou redirection (session expirée) → on cesse d'interroger ;
  - navigation côté client (Inertia émet `inertia:navigate` sur document) →
    un rafraîchissement, car la sidebar est HORS de la région @inertia et ne
    se recalcule pas toute seule ;
  - le balisage (data-nav-badge) est identique sur desktop et mobile : tous
    les éléments correspondants sont mis à jour.
--}}
<script>
    (function () {
        var url = @json($navBadgesUrl);
        var every = {{ (int) ($navBadgesPollSeconds ?? 45) }} * 1000;
        var timer = null;
        var inflight = false;
        var stopped = false;

        function apply(counts) {
            var els = document.querySelectorAll('[data-nav-badge]');
            for (var i = 0; i < els.length; i++) {
                var el = els[i];
                var n = parseInt(counts[el.getAttribute('data-nav-badge')], 10);
                if (isFinite(n) && n > 0) {
                    el.textContent = n > 99 ? '99+' : String(n);
                    el.setAttribute('aria-label', n + ' en attente');
                    el.style.display = '';
                } else {
                    el.textContent = '';
                    el.removeAttribute('aria-label');
                    el.style.display = 'none';
                }
            }
        }

        function poll() {
            if (stopped || inflight || document.hidden) return;
            inflight = true;
            fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                cache: 'no-store'
            }).then(function (res) {
                // Session expirée : selon l'app, 401/403/419 — ou une redirection vers la
                // page de connexion (le middleware d'auth partagé redirige même les
                // requêtes JSON). Dans les deux cas on cesse d'interroger.
                if (res.redirected || res.status === 401 || res.status === 403 || res.status === 419) {
                    stopped = true;
                    stop();
                    throw new Error('session');
                }
                if (!res.ok) throw new Error('http ' + res.status);
                return res.json();
            }).then(apply).catch(function () {
                /* on garde les dernières valeurs affichées */
            }).then(function () {
                inflight = false;
            });
        }

        function start() {
            if (timer === null && !stopped && !document.hidden) timer = setInterval(poll, every);
        }

        function stop() {
            if (timer !== null) { clearInterval(timer); timer = null; }
        }

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) { stop(); return; }
            poll();
            start();
        });
        document.addEventListener('inertia:navigate', poll);

        start();
    })();
</script>
