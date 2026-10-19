<?php

namespace PageLock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mises à jour automatiques depuis les releases GitHub du dépôt public.
 *
 * L'en-tête `Update URI` du plugin pointe vers github.com : WordPress ne
 * consulte donc jamais wordpress.org pour ce plugin (pas de collision avec un
 * homonyme) et appelle le filtre natif `update_plugins_github.com`, prévu
 * exactement pour ce cas.
 *
 * Aucun jeton ni secret : seule l'API publique des releases est utilisée.
 * La release doit contenir un asset ZIP dont le dossier racine est
 * `page-lock-manager/` (généré par `npm run package`, voir RELEASE.md).
 */
class Updater {

	const CACHE_KEY       = 'plkm_github_release';
	const CACHE_TTL       = 6 * HOUR_IN_SECONDS;
	const ERROR_CACHE_TTL = HOUR_IN_SECONDS;
	const SLUG            = 'page-lock-manager';

	public function __construct() {
		\add_filter( 'update_plugins_github.com', [ $this, 'check_update' ], 10, 3 );
		\add_filter( 'plugins_api', [ $this, 'plugin_information' ], 20, 3 );
		\add_filter( 'auto_update_plugin', [ $this, 'force_auto_update' ], 10, 2 );
		\add_action( 'upgrader_process_complete', [ $this, 'purge_cache' ], 10, 0 );
	}

	/**
	 * Dépôt GitHub `owner/repo` interrogé. Filtrable pour un fork.
	 */
	private function get_repo_slug(): string {
		return (string) \apply_filters( 'plkm_github_repo', 'maxevstudio/page-lock-manager' );
	}

	/**
	 * Récupère (et met en cache) la dernière release publiée.
	 *
	 * @return array{version: string, package: string, url: string, body: string}|null
	 */
	private function get_latest_release(): ?array {
		$cached = \get_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return $cached ?: null;
		}

		$response = \wp_remote_get(
			sprintf( 'https://api.github.com/repos/%s/releases/latest', $this->get_repo_slug() ),
			[
				'headers' => [
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'Page-Lock-Manager-Updater',
				],
				'timeout' => 10,
			]
		);

		if ( \is_wp_error( $response ) || 200 !== \wp_remote_retrieve_response_code( $response ) ) {
			\set_transient( self::CACHE_KEY, [], self::ERROR_CACHE_TTL );
			return null;
		}

		$body = json_decode( \wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['tag_name'] ) ) {
			\set_transient( self::CACHE_KEY, [], self::ERROR_CACHE_TTL );
			return null;
		}

		// Seul un asset ZIP construit par la CI est installable : le zipball
		// automatique de GitHub contient les sources et un dossier racine
		// au nom imprévisible.
		$package = '';
		foreach ( (array) ( $body['assets'] ?? [] ) as $asset ) {
			$url = (string) ( $asset['browser_download_url'] ?? '' );
			if ( '.zip' === substr( $url, -4 ) ) {
				$package = $url;
				break;
			}
		}

		if ( '' === $package ) {
			\set_transient( self::CACHE_KEY, [], self::ERROR_CACHE_TTL );
			return null;
		}

		$release = [
			'version' => ltrim( (string) $body['tag_name'], 'v' ),
			'package' => $package,
			'url'     => (string) ( $body['html_url'] ?? '' ),
			'body'    => (string) ( $body['body'] ?? '' ),
		];

		\set_transient( self::CACHE_KEY, $release, self::CACHE_TTL );

		return $release;
	}

	/**
	 * Répond au filtre `update_plugins_github.com` pour ce plugin uniquement.
	 *
	 * @param array|false $update      Mise à jour déjà trouvée par un autre plugin.
	 * @param array       $plugin_data En-têtes du plugin.
	 * @param string      $plugin_file Chemin relatif du fichier principal.
	 * @return array|false
	 */
	public function check_update( $update, $plugin_data, $plugin_file ) {
		if ( PLKM_PLUGIN_BASENAME !== $plugin_file ) {
			return $update;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $update;
		}

		// WordPress compare lui-même `version` à la version installée.
		return [
			'id'      => $plugin_data['UpdateURI'] ?? '',
			'slug'    => self::SLUG,
			'version' => $release['version'],
			'url'     => $release['url'],
			'package' => $release['package'],
		];
	}

	/**
	 * Contenu de la fenêtre « Voir les détails » de l'écran Extensions.
	 *
	 * @param false|object|array $result Résultat existant.
	 * @param string             $action Action demandée.
	 * @param object             $args   Arguments de la requête.
	 * @return false|object|array
	 */
	public function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $result;
		}

		return (object) [
			'name'          => 'Page Lock Manager',
			'slug'          => self::SLUG,
			'version'       => $release['version'],
			'author'        => '<a href="https://maxev.fr">Maxev</a>',
			'homepage'      => 'https://github.com/' . $this->get_repo_slug(),
			'download_link' => $release['package'],
			'requires'      => '6.6',
			'requires_php'  => '7.4',
			'sections'      => [
				'changelog' => \wpautop( \esc_html( $release['body'] ) ),
			],
		];
	}

	/**
	 * Active l'installation automatique des nouvelles releases (sans clic).
	 * Désactivable avec `add_filter( 'plkm_auto_update', '__return_false' );`.
	 *
	 * @param bool|null $update Décision existante.
	 * @param object    $item   Élément de mise à jour.
	 * @return bool|null
	 */
	public function force_auto_update( $update, $item ) {
		if ( isset( $item->plugin ) && PLKM_PLUGIN_BASENAME === $item->plugin ) {
			return (bool) \apply_filters( 'plkm_auto_update', true );
		}
		return $update;
	}

	/**
	 * Vide le cache après une mise à jour pour que le prochain contrôle
	 * reflète immédiatement l'état réel.
	 */
	public function purge_cache(): void {
		\delete_transient( self::CACHE_KEY );
	}
}
