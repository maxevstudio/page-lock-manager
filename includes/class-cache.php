<?php

namespace PageLock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibilité avec les plugins de cache de page.
 *
 * Une page protégée ne doit JAMAIS être mise en cache : la version verrouillée
 * servie depuis le cache bloquerait les visiteurs ayant saisi le mot de passe,
 * et une version déverrouillée en cache serait servie à tout le monde.
 *
 * `DONOTCACHEPAGE` ne suffit pas : WP Fastest Cache, par exemple, l'ignore
 * (vérifié sur la 1.5.3) et ne respecte que sa propre fonction
 * `wpfc_exclude_current_page()`. Chaque plugin connu reçoit donc son signal.
 */
class Cache {

	public function __construct() {
		\add_action( 'template_redirect', [ $this, 'exclude_locked_page' ], 0 );
	}

	/**
	 * Exclut la page courante de tous les caches si elle est protégée, quel
	 * que soit le visiteur (verrouillé ou déjà autorisé).
	 */
	public function exclude_locked_page(): void {
		if ( ! \is_singular() || \is_admin() ) {
			return;
		}

		$post_id = (int) \get_queried_object_id();
		if ( ! $post_id || ! is_page_locked( $post_id ) ) {
			return;
		}

		exclude_current_request_from_cache();
	}
}

/**
 * Demande à tous les caches connus de ne pas stocker la réponse en cours.
 */
function exclude_current_request_from_cache(): void {
	// WP Rocket, W3 Total Cache, WP Super Cache, Cache Enabler, SG Optimizer…
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}

	// WP Fastest Cache ignore DONOTCACHEPAGE.
	if ( function_exists( 'wpfc_exclude_current_page' ) ) {
		\wpfc_exclude_current_page();
	}

	// LiteSpeed Cache.
	\do_action( 'litespeed_control_set_nocache', 'Page Lock Manager: page protégée' );

	// CDN, Varnish et caches navigateur.
	\nocache_headers();
}

/**
 * Vide le cache d'une page dans les plugins de cache connus. Appelé dès que
 * l'état de protection ou le mot de passe change, pour qu'aucune ancienne
 * version (publique ou verrouillée) ne reste servie.
 */
function purge_page_cache( int $post_id ): void {
	// WP Fastest Cache.
	if ( function_exists( 'wpfc_clear_post_cache_by_id' ) ) {
		\wpfc_clear_post_cache_by_id( $post_id );
	}

	// WP Rocket.
	if ( function_exists( 'rocket_clean_post' ) ) {
		\rocket_clean_post( $post_id );
	}

	// W3 Total Cache.
	if ( function_exists( 'w3tc_flush_post' ) ) {
		\w3tc_flush_post( $post_id );
	}

	// WP Super Cache.
	if ( function_exists( 'wp_cache_post_change' ) ) {
		\wp_cache_post_change( $post_id );
	}

	// LiteSpeed Cache.
	\do_action( 'litespeed_purge_post', $post_id );

	\clean_post_cache( $post_id );
}
