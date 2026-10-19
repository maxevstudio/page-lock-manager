<?php

namespace PageLock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fonctions utilitaires du plugin.
 *
 * Ces fonctions forment aussi l'API publique du plugin : un autre plugin
 * (ex. Playlist Manager) peut protéger ses propres pages en appelant
 * `PageLock\set_page_password()` sans dupliquer la logique de protection.
 */

/**
 * Post types pouvant être protégés.
 *
 * @return string[]
 */
function get_supported_post_types(): array {
	return (array) \apply_filters( 'plkm_supported_post_types', [ 'page' ] );
}

/**
 * Vérifie si la protection est activée sur la page (interrupteur de l'éditeur).
 */
function is_lock_enabled( int $post_id ): bool {
	return (bool) \get_post_meta( $post_id, '_plm_locked', true );
}

/**
 * Vérifie si une page est effectivement protégée : protection activée ET mot
 * de passe défini. Une page activée sans mot de passe reste publique plutôt
 * que de devenir inaccessible à tout le monde.
 */
function is_page_locked( int $post_id ): bool {
	return is_lock_enabled( $post_id ) && '' !== get_page_password_hash( $post_id );
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
 * Active la protection d'une page avec un nouveau mot de passe (hashé, jamais
 * stocké en clair). Changer le mot de passe invalide les cookies existants.
 */
function set_page_password( int $post_id, string $password ): void {
	\update_post_meta( $post_id, '_plm_password_hash', \wp_hash_password( $password ) );
	\update_post_meta( $post_id, '_plm_has_password', true );
	\update_post_meta( $post_id, '_plm_locked', true );
	\delete_post_meta( $post_id, '_plm_password' );
}

/**
 * Retire la protection d'une page et supprime son mot de passe.
 */
function remove_page_lock( int $post_id ): void {
	\delete_post_meta( $post_id, '_plm_locked' );
	\delete_post_meta( $post_id, '_plm_password_hash' );
	\delete_post_meta( $post_id, '_plm_has_password' );
	\delete_post_meta( $post_id, '_plm_password' );
}

/**
 * Indique si le visiteur courant doit saisir le mot de passe pour voir la page.
 * Les utilisateurs qui peuvent éditer la page la voient toujours.
 */
function visitor_needs_password( int $post_id ): bool {
	if ( ! is_page_locked( $post_id ) ) {
		return false;
	}
	if ( \current_user_can( 'edit_post', $post_id ) ) {
		return false;
	}
	return ! has_valid_access( $post_id );
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
	$hash        = get_page_password_hash( $post_id );
	$token       = \wp_hash( $hash . '|' . $post_id );
	$cookie_name = get_cookie_name( $post_id );
	$expire      = time() + (int) \apply_filters( 'plkm_cookie_lifetime', DAY_IN_SECONDS, $post_id );

	\setcookie( $cookie_name, $token, [
		'expires'  => $expire,
		'path'     => COOKIEPATH,
		'domain'   => COOKIE_DOMAIN,
		'secure'   => \is_ssl(),
		'httponly' => true,
		'samesite' => 'Lax',
	] );

	// Rend l'accès effectif dès la requête courante.
	$_COOKIE[ $cookie_name ] = $token;
}

/**
 * Adresse IP du visiteur, utilisée pour limiter les essais de mot de passe.
 * Derrière un proxy ou un CDN, filtrer `plkm_client_ip` pour lire le bon en-tête.
 */
function get_client_ip(): string {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return (string) \apply_filters( 'plkm_client_ip', $ip );
}

/**
 * Clé du compteur d'essais pour un couple IP / page.
 */
function get_attempts_key( int $post_id ): string {
	return 'plkm_attempts_' . md5( get_client_ip() . '|' . $post_id );
}

/**
 * Nombre maximal d'essais ratés avant blocage temporaire.
 */
function get_max_attempts(): int {
	return max( 1, (int) \apply_filters( 'plkm_max_attempts', 5 ) );
}

/**
 * Durée du blocage temporaire, en secondes.
 */
function get_lockout_duration(): int {
	return max( 60, (int) \apply_filters( 'plkm_lockout_duration', 15 * MINUTE_IN_SECONDS ) );
}

/**
 * Indique si le visiteur a épuisé ses essais sur cette page.
 */
function is_rate_limited( int $post_id ): bool {
	return (int) \get_transient( get_attempts_key( $post_id ) ) >= get_max_attempts();
}

/**
 * Enregistre un essai raté.
 */
function register_failed_attempt( int $post_id ): void {
	$key = get_attempts_key( $post_id );
	\set_transient( $key, (int) \get_transient( $key ) + 1, get_lockout_duration() );
}

/**
 * Réinitialise le compteur après un essai réussi.
 */
function clear_failed_attempts( int $post_id ): void {
	\delete_transient( get_attempts_key( $post_id ) );
}
