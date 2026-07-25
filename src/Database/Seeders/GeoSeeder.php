<?php
// src/Database/Seeders/GeoSeeder.php
//
// Déplacé depuis amana_web_familles le 21/07/2026. Toutes les requêtes
// ci-dessous ciblent désormais explicitement DB::connection('commun')
// plutôt que la connexion par défaut de l'app appelante — c'est le seul
// changement de fond par rapport à l'original (le reste, y compris le
// contenu des 3 fichiers JSON, est inchangé).

declare(strict_types=1);

namespace Amana\Shared\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Seeder : peuplement des tables villes / secteurs / quartiers (amana_commun).
 *
 * Source des données : export du Google Sheet géo du projet amana_geo
 * (3 CSV villes/secteurs/quartiers, convertis en JSON pour ce seeder afin
 * d'éviter les pièges d'échappement CSV sur des champs contenant du JSON
 * imbriqué — voir data/geo_*.json, à côté de ce fichier).
 *
 * Préparation des données (faite en amont, pas par ce seeder) :
 *   - 4 quartiers avaient un anneau auto-intersectant (géométrie invalide
 *     au sens OGC : Haute-Indre #53, Route du Bel #57, Galheur #58,
 *     La Tondrie #80). Corrigés via Shapely buffer(0) en ne conservant que
 *     le polygone dominant (99,4% à 100% de l'aire ; le reste n'étant que
 *     du bruit de digitalisation, < 0,6% de l'aire, écarté).
 *   - Tous les anneaux ont été réorientés au sens antihoraire pour
 *     l'extérieur (RFC 7946 / règle de la main droite) par bonne pratique.
 *   - Secteurs : "West" renommé en "Ouest".
 *   - Chaque polygone est fourni pré-converti en WKT MULTIPOLYGON (champ
 *     "wkt" des fichiers JSON, généré via Shapely) plutôt qu'en GeoJSON.
 *     Raison : ST_Multi(ST_GeomFromGeoJSON(...)), qui semblait la voie
 *     naturelle sur MySQL 8, échoue purement et simplement sur MariaDB
 *     (ST_Multi n'existe pas). La fonction constructeur MultiPolygon(...)
 *     existe bien sur les deux moteurs, mais RÉINITIALISE le SRID à 0
 *     (vérifié empiriquement : ST_SRID() vaut 4326 avant, 0 après passage
 *     dans MultiPolygon()) — silencieusement, sans erreur. Passer par
 *     ST_GeomFromText(wkt, 4326) directement évite les deux pièges : le
 *     SRID est fixé dès la construction, et la fonction existe à
 *     l'identique sur MySQL comme MariaDB.
 *   - Validité géométrique et cohérence relationnelle (FK, doublons,
 *     champs vides) vérifiées avec Shapely : 0 géométrie invalide,
 *     0 orphelin sur les 19 villes / 57 secteurs / 97 quartiers.
 *
 * Les IDs source sont préservés (insertion explicite) pour rester stables
 * et traçables si ce seeder est relancé, ou si d'autres tables (ex. la
 * résolution géographique différée des familles) doivent y faire
 * référence de façon prévisible.
 *
 * Ordre d'insertion : villes -> secteurs -> quartiers (contraintes FK).
 *
 * ATTENTION : ce seeder est destructif sur ces 3 tables (delete avant
 * réinsertion) — à ne lancer qu'en connaissance de cause, jamais sur une
 * base contenant déjà des données géo qu'on ne veut pas perdre. Peut être
 * lancé depuis N'IMPORTE QUELLE app consommatrice (il cible amana_commun
 * lui-même, indépendamment de la connexion par défaut de l'app appelante) :
 *
 *   php artisan db:seed --class="Amana\Shared\Database\Seeders\GeoSeeder"
 */
class GeoSeeder extends Seeder
{
    public function run(): void
    {
        $dataPath = __DIR__ . '/data';
        $db = DB::connection(config('amana-shared.connection', 'commun'));

        $db->transaction(function () use ($dataPath, $db) {
            $db->statement('SET FOREIGN_KEY_CHECKS=0');

            $db->table('quartiers')->delete();
            $db->table('secteurs')->delete();
            $db->table('villes')->delete();

            $this->seedVilles($db, "{$dataPath}/geo_villes.json");
            $this->seedSecteurs($db, "{$dataPath}/geo_secteurs.json");
            $this->seedQuartiers($db, "{$dataPath}/geo_quartiers.json");

            $db->statement('SET FOREIGN_KEY_CHECKS=1');
        });

        $this->realignAutoIncrement($db, 'villes');
        $this->realignAutoIncrement($db, 'secteurs');
        $this->realignAutoIncrement($db, 'quartiers');

        $this->verifyGeometry($db, 'villes');
        $this->verifyGeometry($db, 'quartiers');

        $this->command?->info(
            'Géographie peuplée (amana_commun) : '
            . $db->table('villes')->count() . ' villes, '
            . $db->table('secteurs')->count() . ' secteurs, '
            . $db->table('quartiers')->count() . ' quartiers.'
        );
    }

    private function seedVilles(\Illuminate\Database\ConnectionInterface $db, string $jsonPath): void
    {
        foreach ($this->readJson($jsonPath) as $row) {
            $db->statement(
                'INSERT INTO villes (id, nom, code_postal, departement, boundary, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ST_GeomFromText(?, 4326), NOW(), NOW())',
                [
                    $row['id'],
                    $row['nom'],
                    $row['code_postal'],
                    $row['departement'],
                    $row['wkt'],
                ]
            );
        }
    }

    private function seedSecteurs(\Illuminate\Database\ConnectionInterface $db, string $jsonPath): void
    {
        foreach ($this->readJson($jsonPath) as $row) {
            $db->statement(
                'INSERT INTO secteurs (id, nom, id_ville, created_at, updated_at)
                    VALUES (?, ?, ?, NOW(), NOW())',
                [$row['id'], $row['nom'], $row['id_ville']]
            );
        }
    }

    private function seedQuartiers(\Illuminate\Database\ConnectionInterface $db, string $jsonPath): void
    {
        foreach ($this->readJson($jsonPath) as $row) {
            $db->statement(
                'INSERT INTO quartiers (id, nom, id_secteur, boundary, created_at, updated_at)
                    VALUES (?, ?, ?, ST_GeomFromText(?, 4326), NOW(), NOW())',
                [
                    $row['id'],
                    $row['nom'],
                    $row['id_secteur'],
                    $row['wkt'],
                ]
            );
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function readJson(string $path): array
    {
        if (!is_readable($path)) {
            throw new RuntimeException("Fichier de données introuvable ou illisible : {$path}");
        }

        $decoded = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new RuntimeException("Format inattendu dans : {$path}");
        }

        return $decoded;
    }

    /**
     * Après insertion avec IDs explicites, AUTO_INCREMENT reste à sa
     * valeur par défaut : on le réaligne sur MAX(id)+1 pour que les
     * futures créations (via l'app) ne rentrent pas en collision avec
     * les IDs importés.
     */
    private function realignAutoIncrement(\Illuminate\Database\ConnectionInterface $db, string $table): void
    {
        $max = (int) ($db->table($table)->max('id') ?? 0);
        $db->statement("ALTER TABLE {$table} AUTO_INCREMENT = " . ($max + 1));
    }

    /**
     * Vérification post-import : toute géométrie invalide ici indique un
     * problème dans les données source ou dans la conversion WKT -> SQL
     * (ex. un futur re-export d'amana_geo pas repassé par la même
     * préparation Shapely). Fait échouer le seeder plutôt que de laisser
     * une géométrie invalide silencieusement en base (elle casserait
     * l'index SPATIAL et donc toute la résolution ST_Contains).
     *
     * ST_IsValid() n'existe pas sur MariaDB (seulement MySQL 8+) : sur un
     * moteur qui ne la supporte pas, cette vérification est simplement
     * ignorée avec un avertissement plutôt que de faire planter le
     * seeder après un import par ailleurs réussi.
     */
    private function verifyGeometry(\Illuminate\Database\ConnectionInterface $db, string $table): void
    {
        try {
            $invalid = $db->table($table)
                ->whereRaw('NOT ST_IsValid(boundary)')
                ->pluck('id');
        } catch (\Throwable $e) {
            $this->command?->warn(
                "Vérification ST_IsValid ignorée sur {$table} (fonction indisponible sur ce moteur : {$e->getMessage()})"
            );

            return;
        }

        if ($invalid->isNotEmpty()) {
            throw new RuntimeException(
                "Géométrie(s) invalide(s) dans {$table} après import : id(s) " . $invalid->implode(', ')
            );
        }
    }
}
