=== Page Lock Manager ===
Contributors: maxev
Tags: password, protection, lock, page, security
Requires at least: 6.6
Tested up to: 7.1
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
* Formulaire HTML (fonctionne sans JavaScript)
* S'appuie sur la protection native de WordPress : respecté par Elementor, l'API REST, les flux RSS et la recherche
* Limite des essais de mot de passe (5 échecs, puis 15 minutes de blocage)
* Metabox pour l'éditeur classique
* Mises à jour automatiques depuis GitHub
* Autres post types via le filtre `plkm_supported_post_types`

== Installation ==

1. Téléchargez le ZIP de la dernière release : https://github.com/maxevstudio/page-lock-manager/releases
2. Extensions → Ajouter → Téléverser une extension
3. Activez le plugin
4. Éditez une page et utilisez le panneau "Page Lock Manager" dans la sidebar

== Changelog ==

Voir CHANGELOG.md sur GitHub : https://github.com/maxevstudio/page-lock-manager/blob/main/CHANGELOG.md

= 1.0.0 =
* Version initiale
