<?php

namespace PageLock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe principale du plugin — singleton.
 */
class Plugin {

	private static ?Plugin $instance = null;

	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		new Meta();
		new Rest();
		new Admin_Columns();
		new Meta_Box();
		new Front_Protection();
		new Updater();

		\add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
	}

	/**
	 * Charge les assets React pour l'éditeur de blocs.
	 */
	public function enqueue_editor_assets(): void {
		$screen = \get_current_screen();
		if ( ! $screen ) {
			return;
		}

		if ( ! in_array( $screen->post_type, get_supported_post_types(), true ) ) {
			return;
		}

		$asset_file = PLKM_PLUGIN_DIR . 'build/editor.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		\wp_enqueue_script(
			'plkm-editor',
			PLKM_PLUGIN_URL . 'build/editor.js',
			$asset['dependencies'] ?? [],
			$asset['version'] ?? PLKM_VERSION,
			true
		);

		\wp_enqueue_style(
			'plkm-editor',
			PLKM_PLUGIN_URL . 'assets/css/editor.css',
			[ 'wp-components' ],
			PLKM_VERSION
		);
	}
}
