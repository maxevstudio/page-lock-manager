# ARCHITECTURE.md

## Arborescence

```
page-lock-manager.php        Bootstrap : en-têtes, constantes PLKM_*, require
uninstall.php                Supprime les meta _plm_* à la désinstallation
includes/
  helpers.php                API publique (namespace PageLock) : état, mot de passe, cookie, limite d'essais
  class-plugin.php           Singleton, instancie les modules, charge les assets éditeur
  class-meta.php             register_post_meta (REST) des meta éditables
  class-rest.php             Hash du mot de passe après sauvegarde REST (éditeur de blocs)
  class-meta-box.php         Metabox de l'éditeur classique (__back_compat_meta_box)
  class-admin-columns.php    Colonne « Protection » des listes admin
  class-front-protection.php Protection front (filtres natifs), formulaire, cache, noindex
  class-updater.php          Mises à jour depuis les releases GitHub
src/editor/                  Panneau React de l'éditeur (PluginDocumentSettingPanel)
src/front/                   Petit script d'état de chargement du formulaire
assets/css/                  front.css (formulaire), editor.css (panneau)
build/                       Généré par wp-scripts (non versionné)
bin/release.mjs              Packaging et release
.github/workflows/           ci.yml (lint + build), release.yml (ZIP sur tag)
```

## Données

| Meta | Rôle | REST |
|---|---|---|
| `_plm_locked` | Interrupteur de protection | oui |
| `_plm_password_hash` | Hash `wp_hash_password` | **non** |
| `_plm_has_password` | Indicateur pour l'UI | oui |
| `_plm_password` | Canal d'écriture du mot de passe en clair, vidé dans la même requête | oui (toujours vide en lecture) |

Une page est **effectivement protégée** si `_plm_locked` est vrai **et** qu'un
hash existe (`is_page_locked()`). Activée sans mot de passe, elle reste
publique et la colonne admin l'indique « Sans mot de passe ».

## Protection front

1. `post_password_required` : vrai pour une page protégée si le visiteur ne
   peut pas l'éditer et n'a pas de cookie valide. Tout le cœur s'appuie sur ce
   filtre : `get_the_content()`, extraits, recherche, flux, commentaires,
   `content.rendered` de l'API REST, et Elementor (qui n'affiche pas son
   contenu builder si la page exige un mot de passe).
2. `the_password_form` : remplace le formulaire natif par le nôtre, uniquement
   sur nos pages.
3. `template_redirect` : traite le POST (nonce, limite d'essais, vérification
   du hash), pose le cookie puis redirige (PRG).
4. `DONOTCACHEPAGE` + `nocache_headers()` sur les pages protégées, et dès le
   chargement du plugin si un cookie `plm_access_*` est présent.
5. `wp_robots` : `noindex, nofollow`.

Cookie : `plm_access_{ID}` = `wp_hash( hash . '|' . ID )`, HttpOnly, SameSite
Lax, 24 h (`plkm_cookie_lifetime`). Changer le mot de passe invalide les
cookies existants.

Limite d'essais : transient par couple IP / page, 5 échecs → blocage 15 min
(`plkm_max_attempts`, `plkm_lockout_duration`, `plkm_client_ip` derrière un
proxy).

## Mises à jour

L'en-tête `Update URI: https://github.com/maxevstudio/page-lock-manager`
empêche WordPress de consulter wordpress.org et déclenche le filtre natif
`update_plugins_github.com`, auquel `Updater` répond avec la dernière release
GitHub (cache 6 h). Seul un asset `.zip` de la release est accepté. Les mises à
jour sont installées automatiquement (`auto_update_plugin`), désactivable via
`add_filter( 'plkm_auto_update', '__return_false' )`.

## Filtres publics

| Filtre | Défaut |
|---|---|
| `plkm_supported_post_types` | `[ 'page' ]` |
| `plkm_cookie_lifetime` | `DAY_IN_SECONDS` |
| `plkm_max_attempts` | `5` |
| `plkm_lockout_duration` | `15 * MINUTE_IN_SECONDS` |
| `plkm_client_ip` | `$_SERVER['REMOTE_ADDR']` |
| `plkm_github_repo` | `maxevstudio/page-lock-manager` |
| `plkm_auto_update` | `true` |

## API PHP pour les autres plugins

```php
if ( function_exists( 'PageLock\set_page_password' ) ) {
	PageLock\set_page_password( $page_id, $password ); // active + hash
}
PageLock\is_page_locked( $page_id );
PageLock\remove_page_lock( $page_id );
```
