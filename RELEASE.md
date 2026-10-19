# RELEASE.md — Workflow de release

Page Lock Manager suit le [Semantic Versioning](https://semver.org/) :
MAJOR (rupture, ex. API PHP publique), MINOR (fonctionnalité), PATCH (correctif).

⚠️ Une release publiée est **installée automatiquement sur tous les sites**
qui utilisent le plugin (en général dans la journée). Ne taguer qu'une version testée.

## Publier une version

1. Vérifier que la section `[Unreleased]` de `CHANGELOG.md` décrit les
   changements et que tout est commité.
2. Lancer :
   ```bash
   npm run release -- --version=1.2.0
   ```
   Le script : vérifie que le dépôt est propre → lint PHP/JS/CSS → build →
   écrit la version (`package.json`, en-tête + `PLKM_VERSION`, `Stable tag`) →
   date la section du CHANGELOG → génère `dist/page-lock-manager-1.2.0.zip` →
   commit `Release 1.2.0` + tag `v1.2.0`. Il ne pousse rien.
3. Publier :
   ```bash
   git push origin main --follow-tags
   ```
4. Le workflow GitHub `release.yml` vérifie que le tag correspond à la version
   du plugin, relance lint + build, et attache le ZIP à la GitHub Release.
5. Les sites détectent la release via `includes/class-updater.php`.

`npm run package` construit seulement le ZIP de la version courante, sans
rien modifier (utilisé par la CI, utile pour un test manuel).

## Contenu du ZIP

Dossier racine `page-lock-manager/` (indispensable : c'est lui que WordPress
remplace lors d'une mise à jour) contenant uniquement :
`page-lock-manager.php`, `uninstall.php`, `includes/`, `build/`, `assets/`,
`languages/`, `readme.txt`, `LICENSE`.

## Premier déploiement sur un site existant

Un site encore en 1.0.0 (sans système de mise à jour) doit recevoir **une fois**
le ZIP à la main : Extensions → Ajouter → Téléverser → « Remplacer la version
actuelle ». Les meta existantes sont reprises telles quelles. Les mises à jour
suivantes sont automatiques.
