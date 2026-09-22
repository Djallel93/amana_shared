<?php
// src/Http/Controllers/SettingsControllerBase.php

declare(strict_types=1);

namespace Amana\Shared\Http\Controllers;

use Amana\Shared\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Base commune pour les contrôleurs de paramètres applicatifs.
 *
 * Ce qui est réellement générique et vit ici : lecture de tous les
 * réglages d'une app (Setting::allForApp), sauvegarde avec respect des
 * clés admin-only, et le audit() de la modification.
 *
 * Ce qui NE l'est PAS et reste à chaque app : le regroupement/affichage
 * des réglages (ex. amana_web_planning groupe par offset_*couleur_*
 * calendar_* et affiche un registre de calendriers Google en plus) — ces
 * apps surchargent index() et fournissent leur propre vue, tout en
 * gardant update() hérité tel quel.
 *
 * Exemple d'usage minimal (nouvelle app sans besoin de groupement) :
 *   class SettingsController extends SettingsControllerBase {}
 *   // index()/update() fonctionnent immédiatement avec la vue générique
 *   // amana-shared::settings.index
 *
 * Exemple avec personnalisation (comme amana_web_planning) :
 *   class SettingsController extends SettingsControllerBase
 *   {
 *       protected function adminOnlyKeys(): array { return ['inscription_ouverte']; }
 *       public function index(): View { ... vue et regroupement propres à l'app ... }
 *   }
 *
 * index() n'est PAS typé ici volontairement (voir plus bas) : une app
 * peut aussi bien surcharger avec `: View` (rendu Blade classique, comme
 * amana_web_planning) qu'avec `: \Inertia\Response` (amana_web_familles,
 * section E4 du refactor, 22/09/2026) — PHP exige qu'un type de retour
 * surchargé soit un sous-type de celui du parent, et `Inertia\Response`
 * n'a aucune relation avec `Illuminate\View\View`, donc les deux ne
 * peuvent pas cohabiter sous un type de retour commun sans faire de ce
 * package une dépendance dure d'inertia/inertia-laravel (que
 * amana_web_planning n'utilise pas).
 */
abstract class SettingsControllerBase extends Controller
{
    /** Code de l'app courante — config('amana-shared.app_code') par défaut. */
    protected function appCode(): string
    {
        return config('amana-shared.app_code');
    }

    /**
     * Clés réservées aux administrateurs — ignorées silencieusement si
     * soumises par un utilisateur non-admin. Vide par défaut : toutes les
     * clés sont modifiables par quiconque a accès à la route (déjà filtré
     * par le middleware role: sur la route elle-même).
     */
    protected function adminOnlyKeys(): array
    {
        return [];
    }

    /**
     * Vue générique — surchargez dans l'app pour un rendu sur mesure.
     *
     * Pas de type de retour déclaré ici (voir le docblock de la classe) :
     * ce socle lui-même renvoie une View, mais une app fille doit pouvoir
     * surcharger avec `: \Inertia\Response` sans violer la covariance des
     * types de retour de PHP.
     *
     * @return View
     */
    public function index()
    {
        $settings = Setting::allForApp($this->appCode());

        return view('amana-shared::settings.index', [
            'settings' => $settings,
            'user' => Auth::user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $appCode = $this->appCode();

        // Chargé avant validate() : sert à la fois à connaître le type réel
        // de chaque clé (voir typedValidationRules(), ajouté le 05/09/2026
        // avec le type 'float' — une valeur non numérique soumise pour une
        // clé float/integer doit être rejetée, pas castée silencieusement à
        // 0/0.0 à la lecture) et à construire $avant pour l'audit, sans
        // recharger deux fois les mêmes lignes.
        $settingsMeta = Setting::allForApp($appCode);

        $request->validate(array_merge(
            [
                'settings' => ['required', 'array'],
                // Filet de sécurité générique pour une clé inconnue de
                // $settingsMeta (ne devrait pas arriver depuis le formulaire
                // généré, mais après tout $settingsInput vient de la requête) —
                // typedValidationRules() resserre ensuite chaque clé connue.
                'settings.*' => ['nullable', 'string', 'max:500'],
            ],
            $this->typedValidationRules($settingsMeta)
        ));

        /** @var \Amana\Shared\Models\Personne $user */
        $user = Auth::user();
        $settingsInput = $request->input('settings', []);
        $adminOnlyKeys = $this->adminOnlyKeys();

        $connection = DB::connection(config('amana-shared.connection', 'commun'));

        $avant = $settingsMeta->map(fn($s) => $s['valeur_raw'])->toArray();

        $idApp = $connection->table('ref_applications')->where('code', $appCode)->value('id');

        if (!$idApp) {
            return redirect()->route('settings.index')
                ->with('error', "Application '{$appCode}' introuvable dans ref_applications.");
        }

        $apres = [];
        foreach ($settingsInput as $cle => $valeur) {
            if (in_array($cle, $adminOnlyKeys, true) && !$user->isAdmin()) {
                continue;
            }

            // $settingsMeta (chargé ci-dessus) reflète déjà l'existence de la
            // ligne — plus besoin d'un exists() supplémentaire par clé ici.
            if (!isset($settingsMeta[$cle])) {
                continue;
            }

            $valeur = trim((string) $valeur);

            $connection->table('ref_settings')
                ->where('id_application', $idApp)
                ->where('cle', $cle)
                ->update(['valeur' => $valeur]);

            $apres[$cle] = $valeur;
        }

        Setting::clearCache();
        audit('update', 'settings', null, $avant, $apres);

        return redirect()->route('settings.index')
            ->with('success', 'Paramètres enregistrés avec succès.');
    }

    /**
     * Règles de validation par clé selon son type réel en base (ajouté le
     * 05/09/2026 avec le type 'float') — settings.* générique ne suffit
     * plus à protéger un helper comme RouteOptimizationConfig
     * (amana_web_familles) qui caste désormais directement le résultat de
     * Setting::get() en confiance : une valeur non numérique soumise pour
     * une clé 'float'/'integer' doit être refusée ici, avant écriture.
     *
     * @return array<string, array<int, string>>
     */
    private function typedValidationRules(\Illuminate\Support\Collection $settingsMeta): array
    {
        $regles = [];

        foreach ($settingsMeta as $cle => $donnee) {
            $regles["settings.{$cle}"] = match ($donnee['type']) {
                'float' => ['nullable', 'numeric'],
                'integer' => ['nullable', 'integer'],
                'boolean' => ['nullable', 'in:0,1'],
                default => ['nullable', 'string', 'max:500'],
            };
        }

        return $regles;
    }
}
