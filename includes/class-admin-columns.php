<?php

namespace PageLock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ajoute une colonne "Protection" dans la liste des post types supportés.
 */
class Admin_Columns {

	public function __construct() {
		\add_action( 'init', [ $this, 'register_hooks' ], 20 );
		\add_action( 'admin_head', [ $this, 'column_styles' ] );
	}

	/**
	 * Branche la colonne sur chaque post type supporté.
	 */
	public function register_hooks(): void {
		foreach ( get_supported_post_types() as $post_type ) {
			\add_filter( "manage_{$post_type}_posts_columns", [ $this, 'add_column' ] );
			\add_action( "manage_{$post_type}_posts_custom_column", [ $this, 'render_column' ], 10, 2 );
		}
	}

	/**
	 * Ajoute la colonne après le titre.
	 */
	public function add_column( array $columns ): array {
		$new_columns = [];
		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;
			if ( 'title' === $key ) {
				$new_columns['plm_protection'] = \__( 'Protection', 'page-lock-manager' );
			}
		}
		return $new_columns;
	}

	/**
	 * Affiche le contenu de la colonne.
	 */
	public function render_column( string $column, int $post_id ): void {
		if ( 'plm_protection' !== $column ) {
			return;
		}

		if ( is_page_locked( $post_id ) ) {
			echo '<span class="plm-badge plm-badge--locked" title="' . \esc_attr__( 'Page protégée par mot de passe', 'page-lock-manager' ) . '">';
			echo '<span class="dashicons dashicons-lock"></span> ';
			echo \esc_html__( 'Protégée', 'page-lock-manager' );
			echo '</span>';
		} elseif ( is_lock_enabled( $post_id ) ) {
			echo '<span class="plm-badge plm-badge--warning" title="' . \esc_attr__( 'Protection activée mais aucun mot de passe défini : la page reste publique.', 'page-lock-manager' ) . '">';
			echo '<span class="dashicons dashicons-warning"></span> ';
			echo \esc_html__( 'Sans mot de passe', 'page-lock-manager' );
			echo '</span>';
		} else {
			echo '<span class="plm-badge plm-badge--open">—</span>';
		}
	}

	/**
	 * Styles pour la colonne.
	 */
	public function column_styles(): void {
		$screen = \get_current_screen();
		if ( ! $screen || 'edit' !== $screen->base || ! in_array( $screen->post_type, get_supported_post_types(), true ) ) {
			return;
		}
		?>
		<style>
			.column-plm_protection { width: 140px; }
			.plm-badge { display: inline-flex; align-items: center; gap: 4px; font-size: 13px; }
			.plm-badge--locked { color: #d63638; font-weight: 500; }
			.plm-badge--warning { color: #996800; }
			.plm-badge .dashicons { font-size: 16px; width: 16px; height: 16px; }
			.plm-badge--open { color: #a7aaad; }
		</style>
		<?php
	}
}
