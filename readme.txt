=== Page Lock Manager ===
Contributors: idylleproduction
Tags: password, protection, lock, page, security
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Protégez vos pages WordPress par mot de passe avec une interface admin moderne intégrée à l'éditeur.

== Description ==

Page Lock Manager permet de protéger l'accès à vos pages WordPress par mot de passe.

= Fonctionnalités =

* Protection par mot de passe sur n'importe quelle page
* Interface intégrée dans la sidebar de l'éditeur WordPress
* Mot de passe hashé de manière sécurisée (jamais stocké en clair)
* Formulaire de saisie côté front propre et responsive
* Indicateur visuel dans la liste des pages (colonne "Protection")
* Cookie sécurisé pour conserver l'accès
* Fallback formulaire HTML (fonctionne sans JavaScript)
* Compatibilité future avec d'autres post types via filtre

== Installation ==

1. Téléchargez le plugin dans `/wp-content/plugins/page-lock-manager/`
2. Exécutez `npm install && npm run build` dans le répertoire du plugin
3. Activez le plugin via le menu Plugins de WordPress
4. Éditez une page et utilisez le panneau "Page Lock Manager" dans la sidebar

== Changelog ==

= 1.0.0 =
* Version initiale
