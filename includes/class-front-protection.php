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
 * Le formulaire s'affiche dans une fenêtre au-dessus de la page floutée. Le
 * contenu réel n'est jamais envoyé au navigateur tant que le mot de passe n'a
 * pas été saisi : la zone de contenu floutée n'est qu'un gabarit factice.
 *
 * Quand Page Lock Manager protège une page, il prend le pas sur un éventuel
 * mot de passe natif défini dans « Visibilité ».
 */
class Front_Protection {

	/**
	 * Erreur à afficher dans le formulaire ('invalid' ou 'rate_limited').
	 */
	private string $error = '';

	/**
	 * Formulaire mis de côté pendant le passage de wpautop (voir protect_form_markup()).
	 */
	private string $stashed_form = '';

	public function __construct() {
		\add_filter( 'post_password_required', [ $this, 'filter_password_required' ], 10, 2 );
		\add_filter( 'the_password_form', [ $this, 'filter_password_form' ], 10, 2 );
		\add_filter( 'the_content', [ $this, 'protect_form_markup' ], 9 );
		\add_filter( 'the_content', [ $this, 'restore_form_markup' ], 99 );
		\add_filter( 'wp_robots', [ $this, 'noindex_locked_pages' ] );
		\add_filter( 'body_class', [ $this, 'body_class' ] );
		\add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		\add_action( 'template_redirect', [ $this, 'handle_form_submission' ] );
		\add_action( 'admin_bar_menu', [ $this, 'admin_bar_notice' ], 100 );
	}

	/**
	 * Indique à WordPress (et à tout le reste) que la page exige un mot de passe.
	 *
	 * @param bool              $required Résultat du contrôle natif.
	 * @param \WP_Post|int|null $post     Post contrôlé.
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
	 * @param string            $output Formulaire natif.
	 * @param \WP_Post|int|null $post   Post concerné (WordPress 5.8+).
	 */
	public function filter_password_form( $output, $post = null ): string {
		$post = \get_post( $post );
		if ( ! $post || ! is_page_locked( $post->ID ) ) {
			return (string) $output;
		}

		return $this->get_lock_form( $post->ID );
	}

	/**
	 * Le formulaire est renvoyé par get_the_content() puis passe par les
	 * filtres de the_content, dont wpautop (priorité 10) qui y injecterait des
	 * <p> et des <br>. On le remplace par un commentaire juste avant, et on le
	 * remet juste après.
	 */
	public function protect_form_markup( $content ) {
		$content = (string) $content;
		$start   = strpos( $content, '<div class="plm-gate"' );
		$end     = strpos( $content, '<!--/plm-gate-->' );
		if ( false === $start || false === $end ) {
			return $content;
		}
		$end               += strlen( '<!--/plm-gate-->' );
		$this->stashed_form = substr( $content, $start, $end - $start );
		return substr_replace( $content, '<!--plm-gate-placeholder-->', $start, $end - $start );
	}

	/**
	 * Remet le formulaire mis de côté par protect_form_markup().
	 */
	public function restore_form_markup( $content ) {
		if ( '' === $this->stashed_form ) {
			return $content;
		}
		$content            = str_replace( [ '<p><!--plm-gate-placeholder--></p>', '<!--plm-gate-placeholder-->' ], $this->stashed_form, (string) $content );
		$this->stashed_form = '';
		return $content;
	}

	/**
	 * ID de la page courante si le visiteur doit saisir le mot de passe, sinon 0.
	 */
	private function get_gated_page_id(): int {
		if ( ! \is_singular() || \is_admin() ) {
			return 0;
		}
		$post_id = (int) \get_queried_object_id();
		return $post_id && visitor_needs_password( $post_id ) ? $post_id : 0;
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
	 * Classe `plm-locked` sur le body : bloque le défilement derrière la fenêtre.
	 */
	public function body_class( array $classes ): array {
		if ( $this->get_gated_page_id() ) {
			$classes[] = 'plm-locked';
		}
		return $classes;
	}

	/**
	 * Rappelle aux utilisateurs connectés que la page est protégée (ils la
	 * voient sans mot de passe parce qu'ils peuvent l'éditer).
	 */
	public function admin_bar_notice( \WP_Admin_Bar $admin_bar ): void {
		if ( \is_admin() || ! \is_singular() ) {
			return;
		}
		$post_id = (int) \get_queried_object_id();
		if ( ! $post_id || ! is_page_locked( $post_id ) || ! \current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$admin_bar->add_node( [
			'id'    => 'plkm-locked',
			'title' => '<span class="ab-icon dashicons dashicons-lock" style="top:2px"></span>' . \esc_html__( 'Page protégée', 'page-lock-manager' ),
			'meta'  => [
				'title' => \__( 'Les visiteurs doivent saisir le mot de passe. Vous voyez la page car vous pouvez la modifier.', 'page-lock-manager' ),
			],
		] );
	}

	/**
	 * Traite la soumission du formulaire côté serveur. Répond en JSON quand la
	 * requête vient du script front (`plm_ajax`), sinon redirige (sans JS).
	 *
	 * Pas de nonce : le formulaire est public, et une page verrouillée servie
	 * par un cache (CDN, proxy) contiendrait un nonce expiré qui bloquerait
	 * les visiteurs. Le formulaire natif de WordPress n'en utilise pas non plus.
	 */
	public function handle_form_submission(): void {
		if ( ! \is_singular() || \is_admin() || empty( $_POST['plm_post_id'] ) || ! isset( $_POST['plm_password'] ) ) {
			return;
		}

		$post_id  = \absint( $_POST['plm_post_id'] );
		$password = \sanitize_text_field( \wp_unslash( $_POST['plm_password'] ) );
		$is_ajax  = ! empty( $_POST['plm_ajax'] );

		if ( $post_id !== (int) \get_queried_object_id() || ! is_page_locked( $post_id ) ) {
			return;
		}

		exclude_current_request_from_cache();

		if ( is_rate_limited( $post_id ) ) {
			$this->error = 'rate_limited';
		} elseif ( '' !== $password && verify_page_password( $post_id, $password ) ) {
			clear_failed_attempts( $post_id );
			set_access_cookie( $post_id );

			if ( $is_ajax ) {
				\wp_send_json_success();
			}
			\wp_safe_redirect( \get_permalink( $post_id ) );
			exit;
		} else {
			register_failed_attempt( $post_id );
			$this->error = is_rate_limited( $post_id ) ? 'rate_limited' : 'invalid';
		}

		if ( $is_ajax ) {
			\wp_send_json_error( [ 'message' => $this->get_error_message() ], 'rate_limited' === $this->error ? 429 : 403 );
		}
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
	 * Génère le gabarit flouté et la fenêtre de saisie du mot de passe.
	 */
	private function get_lock_form( int $post_id ): string {
		$error = $this->get_error_message();

		ob_start();
		?>
		<div class="plm-gate" id="plm-gate">
			<div class="plm-gate__placeholder" aria-hidden="true">
				<span class="plm-gate__line plm-gate__line--title"></span>
				<span class="plm-gate__line"></span>
				<span class="plm-gate__line"></span>
				<span class="plm-gate__line plm-gate__line--short"></span>
				<span class="plm-gate__block"></span>
				<span class="plm-gate__line"></span>
				<span class="plm-gate__line plm-gate__line--short"></span>
			</div>

			<div class="plm-gate__overlay">
				<div class="plm-gate__dialog" role="dialog" aria-modal="true" aria-labelledby="plm-gate-title" aria-describedby="plm-gate-desc">
					<div class="plm-gate__icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
							<rect x="4" y="11" width="16" height="10" rx="2.5"></rect>
							<path d="M8 11V7.5a4 4 0 0 1 8 0V11"></path>
						</svg>
					</div>
					<h2 class="plm-gate__title" id="plm-gate-title"><?php echo \esc_html__( 'Cette page est protégée', 'page-lock-manager' ); ?></h2>
					<p class="plm-gate__desc" id="plm-gate-desc"><?php echo \esc_html__( 'Saisissez le mot de passe pour accéder au contenu.', 'page-lock-manager' ); ?></p>

					<form method="post" action="<?php echo \esc_url( \get_permalink( $post_id ) ); ?>" class="plm-gate__form" id="plm-form">
						<input type="hidden" name="plm_post_id" value="<?php echo \esc_attr( $post_id ); ?>" />
						<label for="plm-password" class="screen-reader-text"><?php echo \esc_html__( 'Mot de passe', 'page-lock-manager' ); ?></label>
						<input
							type="password"
							id="plm-password"
							class="plm-gate__input"
							name="plm_password"
							placeholder="<?php echo \esc_attr__( 'Mot de passe', 'page-lock-manager' ); ?>"
							required
							autofocus
							autocomplete="current-password"
						/>
						<div class="plm-gate__error" role="alert"<?php echo $error ? '' : ' hidden'; ?>><?php echo \esc_html( $error ); ?></div>
						<button type="submit" class="plm-gate__submit">
							<?php echo \esc_html__( 'Accéder à la page', 'page-lock-manager' ); ?>
						</button>
					</form>
				</div>
			</div>
		</div>
		<!--/plm-gate-->
		<?php
		return trim( (string) ob_get_clean() );
	}

	/**
	 * Enqueue les styles et le script front si le visiteur doit saisir le mot de passe.
	 */
	public function enqueue_assets(): void {
		if ( ! $this->get_gated_page_id() ) {
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
				[ 'in_footer' => true, 'strategy' => 'defer' ]
			);
			\wp_localize_script( 'plkm-front', 'plkmFront', [
				'networkError' => \__( 'Connexion impossible. Veuillez réessayer.', 'page-lock-manager' ),
			] );
		}
	}
}
