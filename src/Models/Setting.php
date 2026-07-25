<?php
// src/Models/Setting.php

declare(strict_types=1);

namespace Amana\Shared\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Modèle pour ref_settings (amana_commun).
 *
 * Table partagée entre toutes les applications AMANA.
 * Toujours filtrer par id_application lors de la lecture.
 *
 * Utilisation :
 *   Setting::get('heure_cours', 'planning')         → '20:00'
 *   Setting::get('offset_entree_debut', 'planning') → -30 (int)
 *   Setting::set('heure_cours', 'planning', '20:30')
 *
 * Type 'encrypted' (fusionné depuis amana_web_familles le 21/07/2026) :
 * la valeur est chiffrée avec Crypt avant stockage et déchiffrée
 * automatiquement par get(). Utilisé par ex. pour le refresh token OAuth
 * Google Contacts (décision : DB chiffrée plutôt que .env). Ne jamais
 * utiliser set() pour une clé 'encrypted' — passer par setEncrypted(), qui
 * gère aussi la création de la ligne si elle n'existe pas encore (set() ne
 * fait qu'un UPDATE sur une ligne existante).
 *
 * Le cache statique évite les N+1 — il vit le temps d'une requête HTTP.
 * Attention : le cache est partagé par PROCESSUS PHP, pas par app — deux
 * apps différentes utilisant des appCode différents ne collisionnent pas
 * grâce à la clé "{$appCode}:{$cle}", mais dans un contexte de longue durée
 * (queue worker persistant) pensez à Setting::clearCache() entre jobs de
 * deux apps si jamais ce cas se présentait.
 */
class Setting extends Model
{
    protected $table = 'ref_settings';
    public $timestamps = false;

    protected $fillable = [
        'id_application',
        'cle',
        'valeur',
        'type',
        'libelle',
        'description',
    ];

    /** @var array<string, mixed> Cache ['appCode:cle' => valeur castée] */
    private static array $cache = [];

    public function getConnectionName(): ?string
    {
        return config('amana-shared.connection', 'commun');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'id_application');
    }

    // ── Helpers statiques ─────────────────────────────────────────────────

    public static function get(string $cle, string $appCode): mixed
    {
        $cacheKey = "{$appCode}:{$cle}";

        if (array_key_exists($cacheKey, self::$cache)) {
            return self::$cache[$cacheKey];
        }

        $row = self::connectionQuery()
            ->table('ref_settings as s')
            ->join('ref_applications as a', 'a.id', '=', 's.id_application')
            ->where('a.code', $appCode)
            ->where('s.cle', $cle)
            ->select('s.valeur', 's.type')
            ->first();

        $valeur = $row ? self::cast($row->valeur, $row->type) : null;

        self::$cache[$cacheKey] = $valeur;

        return $valeur;
    }

    /**
     * Met à jour la valeur d'un paramètre en base. Ne fait qu'un UPDATE — la
     * ligne doit déjà exister. Pour créer une clé 'encrypted', utiliser
     * setEncrypted().
     */
    public static function set(string $cle, string $appCode, string $valeur): void
    {
        self::connectionQuery()
            ->table('ref_settings as s')
            ->join('ref_applications as a', 'a.id', '=', 's.id_application')
            ->where('a.code', $appCode)
            ->where('s.cle', $cle)
            ->update(['s.valeur' => $valeur]);
    }

    /**
     * Crée ou met à jour un paramètre de type 'encrypted' : la valeur est
     * chiffrée avant stockage (Crypt::encryptString — AES-256-GCM, clé
     * APP_KEY), et get() la déchiffrera automatiquement à la lecture.
     * Contrairement à set(), fait un updateOrInsert.
     */
    public static function setEncrypted(
        string $cle,
        string $appCode,
        string $valeur,
        string $libelle = '',
        ?string $description = null
    ): void {
        $idApplication = self::connectionQuery()
            ->table('ref_applications')
            ->where('code', $appCode)
            ->value('id');

        if (!$idApplication) {
            throw new \RuntimeException("Application inconnue : {$appCode}");
        }

        self::connectionQuery()->table('ref_settings')->updateOrInsert(
            ['id_application' => $idApplication, 'cle' => $cle],
            [
                'valeur' => Crypt::encryptString($valeur),
                'type' => 'encrypted',
                'libelle' => $libelle,
                'description' => $description,
            ]
        );

        // On met en cache la valeur en clair, pas le chiffré.
        self::$cache["{$appCode}:{$cle}"] = $valeur;
    }

    /**
     * Charge tous les paramètres d'une application en une seule requête.
     * Alimente aussi le cache statique.
     */
    public static function allForApp(string $appCode): \Illuminate\Support\Collection
    {
        $rows = self::connectionQuery()
            ->table('ref_settings as s')
            ->join('ref_applications as a', 'a.id', '=', 's.id_application')
            ->where('a.code', $appCode)
            ->select('s.id', 's.cle', 's.valeur', 's.type', 's.libelle', 's.description')
            ->orderBy('s.id')
            ->get();

        return $rows->mapWithKeys(function ($row) use ($appCode) {
            $casted = self::cast($row->valeur, $row->type);

            self::$cache["{$appCode}:{$row->cle}"] = $casted;

            return [
                $row->cle => [
                    'id' => $row->id,
                    'valeur' => $casted,
                    'valeur_raw' => $row->valeur,
                    'type' => $row->type,
                    'libelle' => $row->libelle,
                    'description' => $row->description,
                ]
            ];
        });
    }

    // ── Cast interne ──────────────────────────────────────────────────────

    private static function cast(string $valeur, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $valeur,
            'boolean' => in_array(strtolower($valeur), ['1', 'true', 'yes', 'oui'], true),
            'encrypted' => self::decryptSafely($valeur),
            'time', 'string' => $valeur,
            default => $valeur,
        };
    }

    /**
     * Déchiffre une valeur 'encrypted', ou renvoie null (+ log) si la clé
     * APP_KEY a changé ou si la valeur est corrompue, plutôt que de planter
     * toute la requête HTTP courante.
     */
    private static function decryptSafely(string $valeur): ?string
    {
        try {
            return Crypt::decryptString($valeur);
        } catch (DecryptException $e) {
            Log::error('[Setting] Échec de déchiffrement d\'un paramètre', [
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private static function connectionQuery(): \Illuminate\Database\ConnectionInterface
    {
        return DB::connection(config('amana-shared.connection', 'commun'));
    }

    /** Vide le cache statique — utile dans les tests unitaires. */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
