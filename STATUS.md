# STATUS.md — État du projet

> Ce fichier décrit un **état**, pas un journal. Mettre à jour après chaque
> étape importante.

## Current version

`1.1.0` — première release publiée sur GitHub (tag `v1.1.0`).

## Completed

- Dossier réorganisé sur le modèle des autres plugins Maxev (Nook,
  SimpleTrad), dépôt Git, documentation permanente.
- Mises à jour automatiques via releases GitHub, workflows CI et release.
- Protection branchée sur le mécanisme natif WordPress, limite d'essais,
  metabox éditeur classique, `noindex`.

## Vérifié (WordPress Playground, WP 7.1 + Elementor, PHP 8.3)

- 1.0.0 : contenu d'une page Elementor protégée visible, contenu de toutes
  les pages protégées visible via `/wp-json/wp/v2/pages` → **confirmé**.
- Nouvelle version : page Gutenberg et page Elementor protégées bloquées,
  REST `content`/`excerpt` vides, aucune fuite via la recherche, route
  `plm/v1/verify` supprimée (404), bon mot de passe → 302 + cookie valable
  pour cette page seulement, blocage au 5e échec, `noindex`, en-têtes
  no-cache, panneau éditeur affiché, nouveau mot de passe hashé sans trace en
  clair, ancien mot de passe refusé, colonne admin correcte.

## Non vérifié

- Metabox de l'éditeur classique (plugin Classic Editor) : code relu, non
  testé dans un navigateur.
- Mise à jour automatique de bout en bout : nécessite une première release
  GitHub publiée.
- Comportement sur le site Idylle Production réel (thème, WP Rocket, CDN).

## Next

1. Installer une fois le ZIP 1.1.0 à la main sur les sites existants (dont
   Idylle Production), puis vérifier les pages protégées en navigation privée.
2. Vérifier à la release suivante que la mise à jour automatique s'installe.
