# PROJECT.md — Cahier des charges

## Qu'est-ce que Page Lock Manager ?

Un petit plugin WordPress, maintenu en interne par Maxev, qui protège des pages
par mot de passe avec une interface intégrée à l'éditeur.

Il s'inscrit dans une démarche d'assainissement des sites clients (en premier
lieu Idylle Production) : remplacer les petits plugins tiers peu maintenus par
des outils maison, simples, versionnés sur GitHub et mis à jour
automatiquement. Seuls les gros éditeurs reconnus (Elementor, WP Rocket…)
restent comme dépendances tierces.

## Pourquoi pas uniquement le natif WordPress ?

| Besoin | Natif « Protégé par mot de passe » | Page Lock Manager |
|---|---|---|
| Respecté par Elementor, REST, flux, recherche | ✅ | ✅ (s'appuie sur le natif) |
| Mot de passe stocké hashé | ❌ en clair | ✅ |
| Accès mémorisé par page | ❌ un seul cookie pour tout le site | ✅ cookie par page |
| Formulaire soigné | ❌ brut | ✅ |
| Limite des essais | ❌ | ✅ 5 essais / 15 min |
| Vue d'ensemble admin | ❌ | ✅ colonne « Protection » |

Le statut « Privé » ne répond pas au besoin : il réserve la page aux
utilisateurs connectés, il ne protège pas par mot de passe.

## Périmètre actuel

- Protection par mot de passe de pages (post types extensibles par filtre).
- Panneau dans l'éditeur de blocs, metabox dans l'éditeur classique.
- Formulaire front sans JavaScript, cookie d'accès par page (24 h).
- Limite d'essais, `noindex`, désactivation du cache sur les pages protégées.
- Mises à jour automatiques depuis les releases GitHub.

## Évolutions envisagées (non planifiées)

- **Intégration avec Playlist Manager** : Playlist Manager créera
  automatiquement une page par playlist ; ces pages devront pouvoir être
  protégées. Option privilégiée : Playlist Manager **utilise** Page Lock Manager
  quand il est actif (appel de `PageLock\set_page_password()` et filtre
  `plkm_supported_post_types`), plutôt que d'embarquer sa propre copie. Une
  seule mise à jour de Page Lock Manager corrige alors la protection partout.
  Décision à prendre au moment de ce chantier.
- Réglage de protection directement dans le panneau de page d'Elementor.

## Hors scope

Rôles / comptes visiteurs, mots de passe multiples par page, statistiques
d'accès, protection de fichiers médias.
