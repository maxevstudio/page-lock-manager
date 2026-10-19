<?php

namespace PageLock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gère l'enregistrement des post meta du plugin.
 *
 * `_plm_password_hash` n'est volontairement jamais exposé dans l'API REST.
 */
class Meta {

	public function __construct() {
		\add_action( 'init', [ $this, 'register_meta' ] );
	}

	/**
	 * Seuls les utilisateurs pouvant éditer le post lisent/écrivent ces meta.
	 *
	 * @param bool   $allowed  Autorisation existante.
	 * @param string $meta_key Clé de la meta.
	 * @param int    $post_id  Post concerné.
	 */
	public function can_edit_meta( $allowed, $meta_key, $post_id ): bool {
		return \current_user_can( 'edit_post', (int) $post_id );
	}

	/**
	 * Enregistre les meta pour les post types supportés.
	 */
	public function register_meta(): void {
		$metas = [
			'_plm_locked'       => [ 'boolean', false, 'rest_sanitize_boolean' ],
			'_plm_password'     => [ 'string', '', 'sanitize_text_field' ],
			'_plm_has_password' => [ 'boolean', false, 'rest_sanitize_boolean' ],
		];

		foreach ( get_supported_post_types() as $post_type ) {
			foreach ( $metas as $key => [ $type, $default, $sanitize ] ) {
				\register_post_meta( $post_type, $key, [
					'type'              => $type,
					'single'            => true,
					'default'           => $default,
					'show_in_rest'      => true,
					'sanitize_callback' => $sanitize,
					'auth_callback'     => [ $this, 'can_edit_meta' ],
				] );
			}
		}
	}
}
