# Changelog

Toutes les modifications notables de Page Lock Manager sont documentées dans ce
fichier.

Le format s'inspire de [Keep a Changelog](https://keepachangelog.com/fr/) et le
projet suit le [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Security
- La protection s'appuie désormais sur le mécanisme natif de WordPress
  (`post_password_required`). Corrige le contournement de la protection sur
  les pages construites avec Elementor, et la fuite du contenu protégé via
  l'API REST (`/wp-json/wp/v2/pages`), les flux RSS et la recherche.
- Limite des essais de mot de passe : 5 échecs par IP et par page, puis
  blocage de 15 minutes.
- Suppression de la route REST publique `plm/v1/verify` (inutilisée).
- Les meta du plugin ne sont lisibles/modifiables que par les utilisateurs
  pouvant éditer la page concernée.

### Added
- Mises à jour automatiques depuis les releases GitHub (`Update URI`).
- Metabox de protection pour l'éditeur classique.
- `noindex, nofollow` sur les pages protégées.
- API PHP publique (`PageLock\set_page_password()`, `remove_page_lock()`…).
- Colonne admin : état « Sans mot de passe » quand la protection est activée
  sans mot de passe.
- Désinstallation propre (`uninstall.php`), workflows GitHub CI et release,
  script `npm run release` / `npm run package`, documentation du dépôt.

### Changed
- Une page activée sans mot de passe reste publique au lieu de devenir
  inaccessible à tout le monde.
- WordPress 6.6 minimum (requis par le build de l'éditeur).
- Le support des autres post types (`plkm_supported_post_types`) s'applique
  aussi au hash du mot de passe et à la colonne admin.

## [1.0.0]

- Version initiale.
