<?php
// tests/Support/FakeProfileExtension.php

declare(strict_types=1);

namespace Amana\Shared\Tests\Support;

use Amana\Shared\Contracts\ProfileExtension;
use Amana\Shared\Models\Personne;

/** Extension de test : stocke ses valeurs en mémoire, expose un champ de chaque famille. */
final class FakeProfileExtension implements ProfileExtension
{
    /** @var array<int, array<string, mixed>> valeurs par id de personne */
    public static array $stockage = [];

    /** @var array<int, array<string, mixed>> derniers $validated reçus par save() */
    public static array $recu = [];

    /** Règles supplémentaires injectées par un test (ex. un champ « statut » à écarter). */
    public static array $reglesEnPlus = [];

    /** Simule une personne sans rien à éditer (fields() vide). */
    public static bool $sansChamps = false;

    public static function reset(): void
    {
        self::$stockage = [];
        self::$recu = [];
        self::$reglesEnPlus = [];
        self::$sansChamps = false;
    }

    public function title(): string
    {
        return 'Informations bénévole';
    }

    public function fields(Personne $personne): array
    {
        if (self::$sansChamps) {
            return [];
        }

        $v = self::$stockage[$personne->id] ?? [];

        return [
            ['name' => 'langue', 'label' => 'Langue préférée', 'type' => 'select', 'required' => true,
                'options' => ['fr' => 'Français', 'ar' => 'Arabe'], 'value' => $v['langue'] ?? 'fr'],
            ['name' => 'permis', 'label' => 'Permis de conduire', 'type' => 'checkbox', 'value' => (bool) ($v['permis'] ?? false)],
            ['name' => 'secteurs', 'label' => 'Secteurs', 'type' => 'multiselect',
                'options' => [1 => 'Nord', 2 => 'Sud'], 'value' => $v['secteurs'] ?? []],
            ['name' => 'note', 'label' => 'Note', 'type' => 'textarea', 'hint' => 'Facultatif', 'value' => $v['note'] ?? ''],
        ];
    }

    public function rules(Personne $personne): array
    {
        return array_merge([
            'langue' => ['required', 'in:fr,ar'],
            'permis' => ['boolean'],
            'secteurs' => ['array'],
            'secteurs.*' => ['integer', 'in:1,2'],
            'note' => ['nullable', 'string', 'max:50'],
        ], self::$reglesEnPlus);
    }

    public function save(Personne $personne, array $validated): void
    {
        self::$recu[$personne->id] = $validated;
        self::$stockage[$personne->id] = $validated;
    }

    public function view(): ?string
    {
        return null;
    }
}
