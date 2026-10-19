<?php

namespace PageLock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fonctions utilitaires du plugin.
 */

/**
 * Vérifie si une page est protégée par Page Lock Manager.
 */
function is_page_locked( int $post_id ): bool {
	return (bool) \get_post_meta( $post_id, '_plm_locked', true );
}

/**
 * Récupère le hash du mot de passe d'une page protégée.
 */
function get_page_password_hash( int $post_id ): string {
	return (string) \get_post_meta( $post_id, '_plm_password_hash', true );
}

/**
 * Vérifie un mot de passe contre le hash stocké.
 */
function verify_page_password( int $post_id, string $password ): bool {
	$hash = get_page_password_hash( $post_id );
	if ( empty( $hash ) ) {
		return false;
	}
	return \wp_check_password( $password, $hash );
}

/**
 * Génère le nom du cookie pour une page donnée.
 */
function get_cookie_name( int $post_id ): string {
	return 'plm_access_' . $post_id;
}

/**
 * Vérifie si le visiteur a déjà un accès valide via cookie.
 */
function has_valid_access( int $post_id ): bool {
	$cookie_name = get_cookie_name( $post_id );
	if ( ! isset( $_COOKIE[ $cookie_name ] ) ) {
		return false;
	}

	$token = \sanitize_text_field( \wp_unslash( $_COOKIE[ $cookie_name ] ) );
	$hash  = get_page_password_hash( $post_id );

	return \hash_equals( \wp_hash( $hash . '|' . $post_id ), $token );
}

/**
 * Définit le cookie d'accès pour une page.
 */
function set_access_cookie( int $post_id ): void {
	$hash       = get_page_password_hash( $post_id );
	$token      = \wp_hash( $hash . '|' . $post_id );
	$cookie_name = get_cookie_name( $post_id );
	$expire     = time() + DAY_IN_SECONDS;

	\setcookie( $cookie_name, $token, [
		'expires'  => $expire,
		'path'     => COOKIEPATH,
		'domain'   => COOKIE_DOMAIN,
		'secure'   => \is_ssl(),
		'httponly'  => true,
		'samesite' => 'Lax',
	] );
}
