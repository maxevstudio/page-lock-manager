<?php

namespace PageLock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gère les endpoints REST et le traitement du mot de passe lors de la sauvegarde.
 */
class Rest {

	public function __construct() {
		\add_action( 'rest_after_insert_page', [ $this, 'handle_password_on_save' ], 10, 2 );
		\add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Après la sauvegarde d'une page via REST, hashe le mot de passe si fourni.
	 */
	public function handle_password_on_save( \WP_Post $post, \WP_REST_Request $request ): void {
		$meta = $request->get_param( 'meta' );

		if ( ! is_array( $meta ) ) {
			return;
		}

		if ( isset( $meta['_plm_password'] ) && ! empty( $meta['_plm_password'] ) ) {
			$password = \sanitize_text_field( $meta['_plm_password'] );
			$hash     = \wp_hash_password( $password );
			\update_post_meta( $post->ID, '_plm_password_hash', $hash );
			\update_post_meta( $post->ID, '_plm_has_password', true );
			\delete_post_meta( $post->ID, '_plm_password' );
		}

		if ( isset( $meta['_plm_locked'] ) && ! $meta['_plm_locked'] ) {
			\delete_post_meta( $post->ID, '_plm_password_hash' );
			\delete_post_meta( $post->ID, '_plm_has_password' );
			\delete_post_meta( $post->ID, '_plm_password' );
		}
	}

	/**
	 * Enregistre les routes REST du plugin.
	 */
	public function register_routes(): void {
		\register_rest_route( 'plm/v1', '/verify/(?P<id>\d+)', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'verify_password' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'id'       => [
					'required'          => true,
					'validate_callback' => function ( $param ) {
						return is_numeric( $param );
					},
					'sanitize_callback' => 'absint',
				],
				'password' => [
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		] );
	}

	/**
	 * Vérifie le mot de passe soumis côté front.
	 */
	public function verify_password( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id  = $request->get_param( 'id' );
		$password = $request->get_param( 'password' );

		$post = \get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => \__( 'Page introuvable.', 'page-lock-manager' ),
			], 404 );
		}

		if ( ! is_page_locked( $post_id ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => \__( 'Cette page n\'est pas protégée.', 'page-lock-manager' ),
			], 400 );
		}

		if ( verify_page_password( $post_id, $password ) ) {
			set_access_cookie( $post_id );
			return new \WP_REST_Response( [
				'success' => true,
				'message' => \__( 'Accès autorisé.', 'page-lock-manager' ),
			], 200 );
		}

		return new \WP_REST_Response( [
			'success' => false,
			'message' => \__( 'Mot de passe incorrect.', 'page-lock-manager' ),
		], 403 );
	}
}
