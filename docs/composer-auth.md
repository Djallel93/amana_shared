# Authentification Composer/npm aux dépôts privés (amana/shared, @amana/shared-ui)

`amana_shared` et `amana_shared_ui` sont des dépôts GitHub **privés**.
Composer (via son repository `"type": "vcs"`) et npm (via une dépendance
`git+https://...`) ont donc tous les deux besoin d'un token pour les
cloner — que ce soit sur votre machine ou en CI.

Un seul mécanisme couvre les deux outils à la fois : la réécriture d'URL
git ci-dessous. Pas besoin de configurer Composer et npm séparément.

## 1. Créer le token

GitHub → **Settings** (compte personnel) → **Developer settings** →
**Personal access tokens** → **Fine-grained tokens** → **Generate new token**.

- **Repository access** : Only select repositories → `amana_shared` +
  `amana_shared_ui` uniquement (pas les dépôts des apps).
- **Permissions** : **Contents: Read-only** suffit — lecture seule, pas de
  push depuis ce token.
- **Expiration** : à votre convenance. Un token qui expire est plus sûr
  mais demande une rotation régulière (voir §4) ; pensez à noter la date
  d'expiration quelque part si vous n'optez pas pour "No expiration".

Copiez le token généré immédiatement (GitHub ne le raffichera plus jamais).

## 2. En local

```bash
git config --global url."https://x-access-token:VOTRE_TOKEN@github.com/".insteadOf "https://github.com/"
```

Cette ligne réécrit **toute** URL `https://github.com/...` en y injectant le
token, pour n'importe quel outil qui clone via git — donc aussi bien
`composer install` (dépôt `amana_shared`) que `npm install`/`npm ci`
(dépendance git `@amana/shared-ui`), sans configuration séparée pour
chacun.

Elle est globale (`--global`, dans `~/.gitconfig`) — s'applique à tous vos
dépôts locaux, pas seulement aux projets AMANA. Si vous préférez limiter la
portée à un seul projet, retirez `--global` et lancez la commande depuis le
dossier du projet concerné (écrit alors dans le `.git/config` local).

**Alternative sans toucher à la config git globale** : Composer accepte
aussi un token via `composer config --global github-oauth.github.com
VOTRE_TOKEN` (écrit dans `~/.composer/auth.json`), mais cette
méthode ne couvre QUE Composer — il faudrait configurer npm séparément
(`~/.npmrc` ou un token embarqué dans l'URL) pour `@amana/shared-ui`. La
réécriture d'URL git ci-dessus reste la manière la plus simple de tout
couvrir en une seule commande.

Si vous utilisez le `composer.local.json`/`npm link` pour du développement
avec des copies locales des packages (voir `docs/local-development.md` de
chaque app), ce token n'est même pas nécessaire : Composer/npm ne
contactent alors jamais GitHub.

## 3. En CI (déjà en place)

Les `.github/workflows/deploy.yaml` de `amana_web_planning` et
`amana_web_familles` font exactement la même réécriture d'URL, avec le
token lu depuis un secret de dépôt :

```yaml
- name: Configure git access for private amana/* repos
  run: |
    git config --global url."https://x-access-token:${{ secrets.AMANA_REPOS_PAT }}@github.com/".insteadOf "https://github.com/"
```

placée avant `composer install` et `npm ci` dans le job de build — elle
authentifie les deux en une seule étape.

Le secret `AMANA_REPOS_PAT` doit être ajouté (Settings → Secrets and
variables → Actions → New repository secret) sur **chaque** dépôt d'app
(`amana_web_planning`, `amana_web_familles`) — voir `MIGRATION.md` à la
racine de la livraison pour la checklist complète de premier déploiement.

Vous pouvez réutiliser le même token pour le local (§2) et pour la CI (§3),
ou en générer deux distincts si vous préférez pouvoir révoquer l'un sans
affecter l'autre.

## 4. Rotation / révocation

Le token n'ayant qu'un accès **lecture seule** sur deux dépôts précis,
l'impact d'une fuite est limité, mais en cas de doute ou à expiration :

1. Générer un nouveau token (§1).
2. Mettre à jour le secret `AMANA_REPOS_PAT` sur les deux dépôts d'app.
3. Mettre à jour `~/.gitconfig` en local (relancer la commande du §2 avec
   le nouveau token — elle remplace l'entrée existante).
4. Révoquer l'ancien token (GitHub → Developer settings → Fine-grained
   tokens → ... → Delete).

## Dépannage

**`Authentication failed` / `403` lors d'un `composer install` ou `npm ci`**
→ Le token n'est pas configuré, a expiré, ou n'a pas accès aux deux dépôts.
Revérifiez le scope du token (§1) et que la ligne `git config` (§2) a bien
été exécutée dans l'environnement où la commande échoue (une machine locale
n'hérite pas de la config d'une autre, un runner CI ne réutilise rien entre
jobs sauf si l'étape d'authentification est bien présente dans CE job).

**`Repository not found` (404) alors que le dépôt existe bien**
→ Symptôme classique d'un token sans accès au dépôt (mauvais scope) plutôt
que d'un vrai problème réseau — GitHub renvoie un 404 plutôt qu'un 403 sur
un dépôt privé pour ne pas confirmer son existence à qui n'a pas accès.

**Ça fonctionne en local mais pas en CI (ou l'inverse)**
→ Les deux environnements ont chacun leur propre config git — vérifiez que
le secret `AMANA_REPOS_PAT` existe bien sur le dépôt GitHub concerné (pas
seulement sur l'org, si applicable) et que l'étape "Configure git access"
s'exécute bien AVANT `composer install`/`npm ci` dans le workflow.
