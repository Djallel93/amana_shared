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
 * des réglages (ex. amana_web_planning groupe par offset_*/couleur_*/
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

    /** Vue générique — surchargez dans l'app pour un rendu sur mesure. */
    public function index(): View
    {
        $settings = Setting::allForApp($this->appCode());

        return view('amana-shared::settings.index', [
            'settings' => $settings,
            'user' => Auth::user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var \Amana\Shared\Models\Personne $user */
        $user = Auth::user();
        $appCode = $this->appCode();
        $settingsInput = $request->input('settings', []);
        $adminOnlyKeys = $this->adminOnlyKeys();

        $connection = DB::connection(config('amana-shared.connection', 'commun'));

        $avant = Setting::allForApp($appCode)
            ->map(fn($s) => $s['valeur_raw'])
            ->toArray();

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

            $existe = $connection->table('ref_settings')
                ->where('id_application', $idApp)
                ->where('cle', $cle)
                ->exists();

            if (!$existe) {
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
}
