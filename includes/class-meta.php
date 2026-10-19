<?php

namespace PageLock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gère l'enregistrement des post meta du plugin.
 */
class Meta {

	public function __construct() {
		\add_action( 'init', [ $this, 'register_meta' ] );
	}

	/**
	 * Enregistre les meta pour les pages.
	 */
	public function register_meta(): void {
		$post_types = \apply_filters( 'plkm_supported_post_types', [ 'page' ] );

		foreach ( $post_types as $post_type ) {
			\register_post_meta( $post_type, '_plm_locked', [
				'type'              => 'boolean',
				'single'            => true,
				'default'           => false,
				'show_in_rest'      => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => function () {
					return \current_user_can( 'edit_pages' );
				},
			] );

			\register_post_meta( $post_type, '_plm_password', [
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => function () {
					return \current_user_can( 'edit_pages' );
				},
			] );

			\register_post_meta( $post_type, '_plm_has_password', [
				'type'              => 'boolean',
				'single'            => true,
				'default'           => false,
				'show_in_rest'      => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => function () {
					return \current_user_can( 'edit_pages' );
				},
			] );
		}
	}
}
