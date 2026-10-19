# AGENTS.md — Règles non négociables du projet Page Lock Manager

Ce fichier s'adresse à **tout agent (humain ou IA)** qui travaille sur ce dépôt.

Le dépôt est la seule source de vérité du projet. **Une conversation n'est jamais
la source de vérité.** Aucune session future ne doit supposer qu'elle a accès à
l'historique d'une conversation précédente.

## 0. Avant toute modification importante

1. Lire `AGENTS.md` (ce fichier).
2. Lire [PROJECT.md](PROJECT.md).
3. Lire [ARCHITECTURE.md](ARCHITECTURE.md).
4. Lire [STATUS.md](STATUS.md).
5. Lire [RELEASE.md](RELEASE.md) si le travail concerne le build ou une release.
6. Inspecter le code existant concerné avant de le modifier.

## 1. Règles de méthode

- Ne jamais recréer une fonctionnalité qui existe déjà : chercher d'abord dans
  `includes/` et `src/`.
- Maintenir `ARCHITECTURE.md` / `PROJECT.md` à jour dès qu'une décision
  structurelle change, et `STATUS.md` après chaque étape importante.
- Toute modification notable est ajoutée à la section `[Unreleased]` de
  `CHANGELOG.md` dans le même commit.
- Respecter le workflow de [RELEASE.md](RELEASE.md) pour toute publication :
  un tag `vX.Y.Z` poussé sur GitHub déclenche la mise à jour automatique de
  **tous les sites** qui utilisent le plugin.

## 2. Règles techniques

1. **La protection passe par le mécanisme natif de WordPress**
   (`post_password_required` + `the_password_form`). Ne jamais revenir à un
   simple filtre sur `the_content` : Elementor, l'API REST, les flux et la
   recherche le contournent (vérifié sur la 1.0.0).
2. **Aucun mot de passe en clair en base.** Seul le hash
   (`wp_hash_password`) est stocké ; `_plm_password` n'est qu'un canal
   d'écriture vidé dans la même requête.
3. **`_plm_password_hash` n'est jamais exposé** dans l'API REST.
4. **Composants et packages `@wordpress/*` en priorité**, React fourni par
   WordPress (externalisé par `wp-scripts`), jamais embarqué.
5. **Front-end public minimal** : formulaire HTML rendu en PHP, fonctionne sans
   JavaScript ; pas de React côté visiteur.
6. **Aucune dépendance externe obligatoire** (compte, SaaS, télémétrie). Seul
   appel sortant : l'API publique des releases GitHub pour les mises à jour.
7. **Les fonctions de `includes/helpers.php` forment l'API publique** utilisée
   par d'autres plugins : ne pas changer leur signature sans version MAJOR.
8. **Le ZIP de production ne contient que l'exécutable** (pas de `src/`,
   `node_modules/`, `.git/`…), généré par `npm run package`.

## 3. En cas de doute

Préférer la simplicité et le comportement natif de WordPress à l'ajout de
fonctionnalités.
