<?php

namespace PageLock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gère la protection du contenu côté front.
 */
class Front_Protection {

	public function __construct() {
		\add_filter( 'the_content', [ $this, 'filter_content' ], 1 );
		\add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
		\add_action( 'template_redirect', [ $this, 'handle_form_submission' ] );
		\add_action( 'template_redirect', [ $this, 'disable_cache_for_locked_pages' ], 0 );
	}

	/**
	 * Empêche la mise en cache des pages protégées.
	 */
	public function disable_cache_for_locked_pages(): void {
		if ( ! \is_singular() || \is_admin() ) {
			return;
		}

		$post_id = \get_the_ID();
		if ( ! $post_id || ! is_page_locked( $post_id ) ) {
			return;
		}

		// Constante universelle respectée par la plupart des plugins de cache
		// (WP Fastest Cache, WP Super Cache, W3 Total Cache, LiteSpeed, etc.)
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		// Headers no-cache pour les CDN et caches serveur.
		\nocache_headers();
	}

	/**
	 * Filtre le contenu de la page pour injecter le formulaire de mot de passe.
	 */
	public function filter_content( string $content ): string {
		if ( ! \is_singular() || \is_admin() ) {
			return $content;
		}

		$post_id = \get_the_ID();
		if ( ! $post_id || ! is_page_locked( $post_id ) ) {
			return $content;
		}

		// Les admins connectés qui peuvent éditer la page voient le contenu.
		if ( \current_user_can( 'edit_post', $post_id ) ) {
			return $content;
		}

		// Vérifie le cookie d'accès.
		if ( has_valid_access( $post_id ) ) {
			return $content;
		}

		// Bloque le contenu et affiche le formulaire.
		return $this->get_lock_form( $post_id );
	}

	/**
	 * Traite la soumission du formulaire côté serveur (fallback sans JS).
	 */
	public function handle_form_submission(): void {
		if ( ! \is_singular() || \is_admin() ) {
			return;
		}

		if ( empty( $_POST['plm_password'] ) || empty( $_POST['plm_post_id'] ) || empty( $_POST['plm_nonce'] ) ) {
			return;
		}

		$post_id  = \absint( $_POST['plm_post_id'] );
		$nonce    = \sanitize_text_field( \wp_unslash( $_POST['plm_nonce'] ) );
		$password = \sanitize_text_field( \wp_unslash( $_POST['plm_password'] ) );

		if ( ! \wp_verify_nonce( $nonce, 'plm_verify_' . $post_id ) ) {
			return;
		}

		if ( verify_page_password( $post_id, $password ) ) {
			set_access_cookie( $post_id );
			\wp_safe_redirect( \get_permalink( $post_id ) );
			exit;
		}

		// Stocke l'erreur pour l'affichage.
		$GLOBALS['plm_error'] = true;
	}

	/**
	 * Génère le formulaire HTML de saisie du mot de passe.
	 */
	private function get_lock_form( int $post_id ): string {
		$error = ! empty( $GLOBALS['plm_error'] );
		$nonce = \wp_create_nonce( 'plm_verify_' . $post_id );

		ob_start();
		?>
		<div class="plm-lock-form" id="plm-lock-form">
			<div class="plm-lock-form__inner">
				<div class="plm-lock-form__icon">
					<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
						<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
						<path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
					</svg>
				</div>
				<h2 class="plm-lock-form__title"><?php echo \esc_html__( 'Cette page est protégée', 'page-lock-manager' ); ?></h2>
				<p class="plm-lock-form__desc"><?php echo \esc_html__( 'Veuillez saisir le mot de passe pour accéder au contenu de cette page.', 'page-lock-manager' ); ?></p>

				<?php if ( $error ) : ?>
					<div class="plm-lock-form__error" role="alert">
						<?php echo \esc_html__( 'Mot de passe incorrect. Veuillez réessayer.', 'page-lock-manager' ); ?>
					</div>
				<?php endif; ?>

				<form method="post" class="plm-lock-form__form" id="plm-form">
					<input type="hidden" name="plm_post_id" value="<?php echo \esc_attr( $post_id ); ?>" />
					<input type="hidden" name="plm_nonce" value="<?php echo \esc_attr( $nonce ); ?>" />
					<div class="plm-lock-form__field">
						<label for="plm-password" class="screen-reader-text"><?php echo \esc_html__( 'Mot de passe', 'page-lock-manager' ); ?></label>
						<input
							type="password"
							id="plm-password"
							name="plm_password"
							placeholder="<?php echo \esc_attr__( 'Mot de passe', 'page-lock-manager' ); ?>"
							required
							autocomplete="current-password"
						/>
					</div>
					<button type="submit" class="plm-lock-form__submit">
						<?php echo \esc_html__( 'Accéder à la page', 'page-lock-manager' ); ?>
					</button>
				</form>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Enqueue les styles et le script front si nécessaire.
	 */
	public function enqueue_styles(): void {
		if ( ! \is_singular() ) {
			return;
		}

		$post_id = \get_the_ID();
		if ( ! $post_id || ! is_page_locked( $post_id ) ) {
			return;
		}

		if ( \current_user_can( 'edit_post', $post_id ) || has_valid_access( $post_id ) ) {
			return;
		}

		wp_enqueue_style(
			'plkm-front',
			PLKM_PLUGIN_URL . 'assets/css/front.css',
			[],
			PLKM_VERSION
		);

		$asset_file = PLKM_PLUGIN_DIR . 'build/front.asset.php';
		if ( file_exists( $asset_file ) ) {
			$asset = require $asset_file;
			wp_enqueue_script(
				'plkm-front',
				PLKM_PLUGIN_URL . 'build/front.js',
				$asset['dependencies'] ?? [],
				$asset['version'] ?? PLKM_VERSION,
				true
			);
		}
	}
}
