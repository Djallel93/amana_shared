# Conventions mobile — AMANA

> Document créé le 04/09/2026, après que le motif ci-dessous ait été
> ré-appliqué une deuxième fois (`personnes/index.blade.php` +
> `PlanningGrid.vue` existaient déjà, `familles/index.blade.php` vient de
> l'adopter à son tour) — ce document formalise donc quelque chose de
> prouvé deux fois, pas une intuition à valider plus tard.

Ce document existe pour qu'une troisième app (ou une nouvelle page dans
une app existante) n'ait pas à réinventer ce qui vit déjà, de façon non
écrite, dans `personnes/index.blade.php` (amana_web_familles et
amana_web_planning) et `PlanningGrid.vue` (amana_web_planning).

## Le point de rupture : `md` (768px)

Toute bascule tableau ↔ carte dans cet écosystème se fait au breakpoint
`md` de Tailwind (768px), sans exception :

- Tableau desktop : `hidden md:block overflow-x-auto`
- Carte mobile : `md:hidden divide-y divide-surface-3`

N'utilisez ni `sm` ni `lg` pour cette bascule précise, même si un cas
particulier semble mieux s'y prêter à première vue — la cohérence entre
pages (l'utilisateur ne doit jamais voir un tableau se transformer en
carte à une largeur différente d'une page à l'autre) prime sur
l'optimisation locale d'une seule vue.

## Anatomie de la "carte-ligne" mobile

Extraite de deux cas concrets :

- **Cas simple** (`personnes/index.blade.php`, une ligne = une entité) :
  avatar/icône en tête, ligne d'identité principale, ligne secondaire de
  métadonnées, boutons d'action en haut à droite (`.btn-touch`), badge de
  rôle/statut affiché en dessous de la ligne d'identité.
- **Cas dense / 2D** (`PlanningGrid.vue`) : une grille hebdomadaire à deux
  dimensions devient une liste d'agenda par jour, chaque tâche affichée en
  chip dans une grille `grid-cols-2` de largeur fixe (justifié : les
  libellés de tâches sont courts et déjà dimensionnés pour 2 par ligne sur
  téléphone).

Dans les deux cas, la même anatomie de base s'applique :

1. Avatar/icône ou identifiant visuel en tête de carte.
2. Ligne d'identité principale (nom, titre — ce qui identifie l'entité).
3. Ligne(s) secondaire(s) de métadonnées (email, téléphone, adresse…).
4. Boutons d'action en haut à droite, utilisant `.btn-touch`.
5. Badge(s) de statut/rôle affiché sous la ligne d'identité, pas à côté.
6. Champs de détail additionnels : **`grid-cols-1 sm:grid-cols-2`**,
   jamais un `grid-cols-2`/`grid-cols-3` fixe pour une paire label+valeur
   — c'est précisément le bug corrigé le 04/09/2026 dans
   `DetailPanel.vue` (les 6 groupes de champs des onglets Identité /
   Adresse / Décision) et dans `FamillesStatistiques.vue` (la grille
   "Caractéristiques", seule grille de ce fichier sans palier
   responsive).

### Exception : grilles de chips/toggles à colonnes fixes

Un `grid-cols-2` ou `grid-cols-3` fixe reste correct pour une grille de
**chips ou de cases à cocher courtes**, déjà dimensionnée pour tenir 2 à 3
par ligne sur un téléphone — par exemple la grille de bascule des tâches
de `restrictions/index` ou les chips de tâches de `PlanningGrid.vue`. La
règle porte sur la **largeur du contenu**, pas sur une interdiction
générale des grilles à colonnes fixes : un court chip "Ven." tient très
bien à deux par ligne, un couple label+valeur de type "Organisation
d'origine : Croissant Rouge Algérien" ne tient pas.

## `.btn-touch` : l'utilitaire canonique de cible tactile

`amana_shared_ui/styles/amana-shared.css` définit déjà :

```css
.btn-touch {
  min-height: 44px;
  min-width: 44px;
}
```

Utilisez cette classe pour toute cible tactile (bouton, lien, case à
cocher stylée en chip, onglet). Ne recréez pas `min-h-[44px]
min-w-[44px]` composant par composant — au-delà de la duplication, un
futur ajustement de la cible tactile (ex. passage à 48px) nécessiterait
alors de retrouver chaque occurrence individuellement plutôt que de
changer une seule définition.

`.btn-touch` s'applique sans conflit à un élément qui a par ailleurs une
taille Tailwind fixe plus petite (ex. `w-9 h-9` = 36px) : `min-height`/
`min-width` priment sur `width`/`height` en CSS, donc l'élément est
simplement porté à 44px minimum sans qu'il faille retirer la classe de
taille existante.

## Pourquoi les stations Livraison (réception/pesée/packaging/chargement)

## sont prioritaires pour ce motif, une fois codées

Le domaine Livraison (`Campagne`, `Livraison`, etc.) a des migrations,
modèles et routes déjà en place, mais ses vues sont encore en cours de
construction (desktop-first, comme le reste de l'écosystème) au moment de
la rédaction de ce document — voir la feuille de route mobile pour le
détail. Une fois ces vues codées, les quatre écrans de station
(réception, pesée, packaging, chargement) devront être les **premiers**
à recevoir ce retrofit tableau→carte, avant les vues côté
admin/gestionnaire (campagnes, contacts, tableau de bord, statistiques).

La raison n'est pas technique mais d'usage : ces quatre écrans sont
utilisés par un membre d'équipe qui coche des étapes physiquement debout
sur un quai de chargement, téléphone ou tablette en main — pas par un
gestionnaire assis à un bureau. Le contexte d'usage, pas la complexité du
composant, détermine l'ordre de priorité du retrofit.

## Composants partagés (optionnel)

Deux composants Blade fins existent potentiellement dans `amana_shared`
(`x-amana-shared::responsive-table-wrapper` /
`x-amana-shared::responsive-card-list`) — de simples wrappers
`hidden md:block overflow-x-auto` / `md:hidden divide-y`, rien de plus.
Le contenu réel des colonnes/lignes n'est **pas** génériqué : les deux
implémentations de `personnes/index.blade.php` (amana_web_familles et
amana_web_planning) ont déjà divergé en pratique (rôles et champs
différents), preuve que forcer une vue générique unique coûterait plus
cher que la duplication actuelle. Documentez le motif, ne construisez pas
de composant de tableau générique.
