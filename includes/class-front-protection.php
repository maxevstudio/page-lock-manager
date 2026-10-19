<?php

namespace PageLock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gère la protection du contenu côté front.
 *
 * La protection se branche sur le mécanisme natif « protégé par mot de passe »
 * de WordPress via le filtre `post_password_required` : tout ce qui respecte
 * déjà la protection native la respecte aussi pour nos pages, sans code
 * spécifique — the_content, extraits, recherche, flux RSS, commentaires,
 * API REST (`content.rendered` vide) et les constructeurs comme Elementor.
 * Le formulaire natif est ensuite remplacé par le nôtre via `the_password_form`.
 *
 * Quand Page Lock Manager protège une page, il prend le pas sur un éventuel
 * mot de passe natif défini dans « Visibilité ».
 */
class Front_Protection {

	/**
	 * Erreur à afficher dans le formulaire ('invalid' ou 'rate_limited').
	 */
	private string $error = '';

	public function __construct() {
		\add_filter( 'post_password_required', [ $this, 'filter_password_required' ], 10, 2 );
		\add_filter( 'the_password_form', [ $this, 'filter_password_form' ], 10, 2 );
		\add_filter( 'wp_robots', [ $this, 'noindex_locked_pages' ] );
		\add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
		\add_action( 'template_redirect', [ $this, 'handle_form_submission' ] );
		\add_action( 'template_redirect', [ $this, 'disable_cache_for_locked_pages' ], 0 );
	}

	/**
	 * Indique à WordPress (et à tout le reste) que la page exige un mot de passe.
	 *
	 * @param bool                  $required Résultat du contrôle natif.
	 * @param \WP_Post|int|null     $post     Post contrôlé.
	 */
	public function filter_password_required( $required, $post ): bool {
		$post = \get_post( $post );
		if ( ! $post || ! is_page_locked( $post->ID ) ) {
			return (bool) $required;
		}

		return visitor_needs_password( $post->ID );
	}

	/**
	 * Remplace le formulaire natif par celui du plugin sur nos pages.
	 *
	 * @param string                $output Formulaire natif.
	 * @param \WP_Post|int|null     $post   Post concerné (WordPress 5.8+).
	 */
	public function filter_password_form( $output, $post = null ): string {
		$post = \get_post( $post );
		if ( ! $post || ! is_page_locked( $post->ID ) ) {
			return (string) $output;
		}

		return $this->get_lock_form( $post->ID );
	}

	/**
	 * Empêche l'indexation des pages protégées.
	 */
	public function noindex_locked_pages( array $robots ): array {
		if ( \is_singular() && is_page_locked( (int) \get_queried_object_id() ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}

	/**
	 * Empêche la mise en cache des pages protégées.
	 */
	public function disable_cache_for_locked_pages(): void {
		if ( ! \is_singular() || \is_admin() ) {
			return;
		}

		$post_id = (int) \get_queried_object_id();
		if ( ! $post_id || ! is_page_locked( $post_id ) ) {
			return;
		}

		// Constante universelle respectée par la plupart des plugins de cache
		// (WP Rocket, WP Fastest Cache, WP Super Cache, W3 Total Cache, LiteSpeed…).
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		// Headers no-cache pour les CDN et caches serveur.
		\nocache_headers();
	}

	/**
	 * Traite la soumission du formulaire côté serveur.
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

		if ( $post_id !== (int) \get_queried_object_id() || ! is_page_locked( $post_id ) ) {
			return;
		}

		if ( ! \wp_verify_nonce( $nonce, 'plm_verify_' . $post_id ) ) {
			return;
		}

		if ( is_rate_limited( $post_id ) ) {
			$this->error = 'rate_limited';
			return;
		}

		if ( verify_page_password( $post_id, $password ) ) {
			clear_failed_attempts( $post_id );
			set_access_cookie( $post_id );
			\wp_safe_redirect( \get_permalink( $post_id ) );
			exit;
		}

		register_failed_attempt( $post_id );
		$this->error = is_rate_limited( $post_id ) ? 'rate_limited' : 'invalid';
	}

	/**
	 * Message d'erreur correspondant à la dernière soumission.
	 */
	private function get_error_message(): string {
		if ( 'rate_limited' === $this->error ) {
			return sprintf(
				/* translators: %d: durée du blocage en minutes. */
				\__( 'Trop de tentatives. Veuillez réessayer dans %d minutes.', 'page-lock-manager' ),
				(int) ceil( get_lockout_duration() / MINUTE_IN_SECONDS )
			);
		}
		if ( 'invalid' === $this->error ) {
			return \__( 'Mot de passe incorrect. Veuillez réessayer.', 'page-lock-manager' );
		}
		return '';
	}

	/**
	 * Génère le formulaire HTML de saisie du mot de passe.
	 */
	private function get_lock_form( int $post_id ): string {
		$error = $this->get_error_message();
		$nonce = \wp_create_nonce( 'plm_verify_' . $post_id );

		ob_start();
		?>
		<div class="plm-lock-form" id="plm-lock-form">
			<div class="plm-lock-form__inner">
				<div class="plm-lock-form__icon">
					<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
						<path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
					</svg>
				</div>
				<h2 class="plm-lock-form__title"><?php echo \esc_html__( 'Cette page est protégée', 'page-lock-manager' ); ?></h2>
				<p class="plm-lock-form__desc"><?php echo \esc_html__( 'Veuillez saisir le mot de passe pour accéder au contenu de cette page.', 'page-lock-manager' ); ?></p>

				<?php if ( $error ) : ?>
					<div class="plm-lock-form__error" role="alert">
						<?php echo \esc_html( $error ); ?>
					</div>
				<?php endif; ?>

				<form method="post" action="<?php echo \esc_url( \get_permalink( $post_id ) ); ?>" class="plm-lock-form__form" id="plm-form">
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
		return (string) ob_get_clean();
	}

	/**
	 * Enqueue les styles et le script front si nécessaire.
	 */
	public function enqueue_styles(): void {
		if ( ! \is_singular() ) {
			return;
		}

		$post_id = (int) \get_queried_object_id();
		if ( ! $post_id || ! visitor_needs_password( $post_id ) ) {
			return;
		}

		\wp_enqueue_style(
			'plkm-front',
			PLKM_PLUGIN_URL . 'assets/css/front.css',
			[],
			PLKM_VERSION
		);

		$asset_file = PLKM_PLUGIN_DIR . 'build/front.asset.php';
		if ( file_exists( $asset_file ) ) {
			$asset = require $asset_file;
			\wp_enqueue_script(
				'plkm-front',
				PLKM_PLUGIN_URL . 'build/front.js',
				$asset['dependencies'] ?? [],
				$asset['version'] ?? PLKM_VERSION,
				true
			);
		}
	}
}
