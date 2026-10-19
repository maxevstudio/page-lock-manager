<?php

namespace PageLock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Metabox de protection pour l'éditeur classique (plugin Classic Editor ou
 * post type sans éditeur de blocs). Dans l'éditeur de blocs, c'est le panneau
 * React (src/editor) qui est affiché à la place.
 */
class Meta_Box {

	public function __construct() {
		\add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
		\add_action( 'save_post', [ $this, 'save' ], 10, 2 );
	}

	/**
	 * Déclare la metabox dans la colonne latérale.
	 */
	public function add_meta_box(): void {
		\add_meta_box(
			'plkm-protection',
			\__( 'Page Lock Manager', 'page-lock-manager' ),
			[ $this, 'render' ],
			get_supported_post_types(),
			'side',
			'default',
			[ '__back_compat_meta_box' => true ]
		);
	}

	/**
	 * Affiche la metabox.
	 */
	public function render( \WP_Post $post ): void {
		$enabled      = is_lock_enabled( $post->ID );
		$has_password = '' !== get_page_password_hash( $post->ID );

		\wp_nonce_field( 'plkm_meta_box_' . $post->ID, 'plkm_meta_box_nonce' );
		?>
		<p>
			<label>
				<input type="checkbox" name="plkm_locked" value="1" <?php \checked( $enabled ); ?> />
				<?php echo \esc_html__( 'Protéger cette page par mot de passe', 'page-lock-manager' ); ?>
			</label>
		</p>
		<p>
			<label for="plkm-password"><?php echo \esc_html__( 'Mot de passe', 'page-lock-manager' ); ?></label>
			<input
				type="password"
				id="plkm-password"
				name="plkm_password"
				class="widefat"
				autocomplete="new-password"
				placeholder="<?php echo $has_password ? '••••••••' : \esc_attr__( 'Saisir un mot de passe', 'page-lock-manager' ); ?>"
			/>
		</p>
		<p class="description">
			<?php
			echo $has_password
				? \esc_html__( 'Un mot de passe est déjà défini. Saisissez-en un nouveau pour le modifier.', 'page-lock-manager' )
				: \esc_html__( 'Ce mot de passe sera requis pour accéder à la page.', 'page-lock-manager' );
			?>
		</p>
		<?php if ( $enabled && ! $has_password ) : ?>
			<p style="color:#996800;">
				<?php echo \esc_html__( 'Aucun mot de passe défini : la page reste publique.', 'page-lock-manager' ); ?>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Enregistre la protection à la sauvegarde du formulaire classique.
	 * Les sauvegardes de l'éditeur de blocs passent par la classe Rest.
	 */
	public function save( int $post_id, \WP_Post $post ): void {
		if ( empty( $_POST['plkm_meta_box_nonce'] ) ) {
			return;
		}

		$nonce = \sanitize_text_field( \wp_unslash( $_POST['plkm_meta_box_nonce'] ) );
		if ( ! \wp_verify_nonce( $nonce, 'plkm_meta_box_' . $post_id ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || \wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, get_supported_post_types(), true ) || ! \current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( empty( $_POST['plkm_locked'] ) ) {
			remove_page_lock( $post_id );
			return;
		}

		$password = isset( $_POST['plkm_password'] ) ? \sanitize_text_field( \wp_unslash( $_POST['plkm_password'] ) ) : '';

		if ( '' !== $password ) {
			set_page_password( $post_id, $password );
		} else {
			\update_post_meta( $post_id, '_plm_locked', true );
		}
	}
}
