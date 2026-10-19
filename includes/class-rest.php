<?php

namespace PageLock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Traite le mot de passe lors de la sauvegarde depuis l'éditeur de blocs.
 *
 * L'éditeur envoie le mot de passe en clair dans la meta `_plm_password` ; il
 * est hashé puis supprimé dans la même requête.
 */
class Rest {

	public function __construct() {
		\add_action( 'init', [ $this, 'register_hooks' ], 20 );
	}

	/**
	 * Branche le traitement sur chaque post type supporté (après `init`, pour
	 * que le filtre `plkm_supported_post_types` d'un thème soit pris en compte).
	 */
	public function register_hooks(): void {
		foreach ( get_supported_post_types() as $post_type ) {
			\add_action( "rest_after_insert_{$post_type}", [ $this, 'handle_password_on_save' ], 10, 2 );
		}
	}

	/**
	 * Après la sauvegarde via REST, hashe le mot de passe si fourni.
	 */
	public function handle_password_on_save( \WP_Post $post, \WP_REST_Request $request ): void {
		$meta = $request->get_param( 'meta' );

		if ( ! is_array( $meta ) ) {
			return;
		}

		if ( isset( $meta['_plm_locked'] ) && ! $meta['_plm_locked'] ) {
			remove_page_lock( $post->ID );
			return;
		}

		if ( ! empty( $meta['_plm_password'] ) ) {
			set_page_password( $post->ID, \sanitize_text_field( $meta['_plm_password'] ) );
		}

		// Ne jamais laisser de mot de passe en clair en base.
		\delete_post_meta( $post->ID, '_plm_password' );
	}
}
