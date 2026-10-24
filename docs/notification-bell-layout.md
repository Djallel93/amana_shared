# Cloche de notifications — place réservée dans le layout

> **À lire avant de créer un layout ou une nouvelle app AMANA.**
> Ajouté le 23/10/2026, après qu'elle a recouvert les boutons « Créer une campagne » et
> « Créer une famille » dans amana_web_familles.

## Le problème

`NotificationBell` (paquet `@amana/shared-ui`) est positionnée en **`position: fixed`** dans le coin
supérieur droit de la fenêtre :

| Propriété | Valeur |
|---|---|
| `top` | `12px` (+ 56 px de topbar sur mobile, + la hauteur des bandeaux `OfflineBanner` / `UrgentAlertBar` quand ils sont visibles — voir `useTopStackOffset`) |
| `right` | `16px` |
| Taille | `40 × 40 px` |
| `z-index` | `400` |

Étant `fixed`, elle est **hors du flux** : aucun élément de page ne « sait » qu'elle est là, et elle
recouvre tout ce qui se trouve dessous. Tout ce qui est aligné à droite en haut du contenu (boutons
d'action des en-têtes de page, filtres…) se retrouve sous la cloche.

## La règle

**Le layout réserve la place, pas les pages.** `<main>` porte un padding-haut de **3.75rem**
(`pt-15` en Tailwind 4) :

```
12 px (marge de la cloche) + 40 px (cloche) + 8 px (air) = 60 px = 3.75rem
```

```html
<main class="flex-1 px-8 pb-8 pt-15 max-w-(--breakpoint-xl) w-full mx-auto
             max-lg:px-7 max-lg:pb-7 max-sm:px-4 max-sm:pb-5">
```

Sur mobile, le décalage de la topbar (56 px) est déjà absorbé par `max-sm:pt-topbar` sur
`#mainWrapper` : la même valeur suffit.

Ce que fait `layouts/app.blade.php` de ce paquet depuis cette version ; l'app Inertia
d'amana_web_familles (`resources/views/app.blade.php`) duplique le shell et applique la même
classe. **Toute app qui écrit son propre shell doit faire pareil.**

## À ne pas faire

- Ne pas déplacer ou décaler la cloche page par page (`mr-14` sur un en-tête…) : c'est le layout
  qui est responsable, sinon chaque nouvelle page refait l'erreur.
- Ne pas repasser `<main>` en `p-8` « pour simplifier » : le haut redevient à 2rem et la cloche
  recouvre de nouveau le coin.
- Ne pas supposer que le coin supérieur droit est libre : y mettre un contrôle sans retoucher ce
  layout, c'est le mettre sous la cloche.

## Limite connue

Quand un bandeau (`UrgentAlertBar`, `OfflineBanner`) s'affiche, la cloche descend de sa hauteur
sans que le contenu ne bouge : la place réservée peut alors être un peu juste tant que le bandeau
est visible. Acceptable pour une alerte temporaire ; si ça devient gênant, la solution propre est de
pousser aussi le contenu (variable CSS publiée par `useTopStackOffset`), pas d'agrandir le padding.

## Pour aller plus loin

- Comportement de la cloche (animation à l'arrivée d'une notification, titre d'onglet, réglages
  `configureNotifications`) : `README.md` de `amana_shared_ui`, section « Cloche de notifications ».
- Stockage et sondage des notifications : `Amana\Shared\Services\NotificationCenterService`.
