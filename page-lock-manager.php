<?php
/**
 * Plugin Name:       Page Lock Manager
 * Plugin URI:        https://github.com/maxevstudio/page-lock-manager
 * Description:       Protégez vos pages WordPress par mot de passe avec une interface admin moderne intégrée à l'éditeur.
 * Version:           1.1.1
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Maxev
 * Author URI:        https://maxev.fr
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       page-lock-manager
 * Domain Path:       /languages
 * Update URI:        https://github.com/maxevstudio/page-lock-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Bypass du cache très tôt si un cookie plm_access_* est présent.
// Doit s'exécuter avant que les plugins de cache ne servent le HTML statique.
foreach ( $_COOKIE as $name => $value ) {
	if ( strpos( $name, 'plm_access_' ) === 0 ) {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		break;
	}
}

define( 'PLKM_VERSION', '1.1.1' );
define( 'PLKM_PLUGIN_FILE', __FILE__ );
define( 'PLKM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PLKM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PLKM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once PLKM_PLUGIN_DIR . 'includes/helpers.php';
require_once PLKM_PLUGIN_DIR . 'includes/class-cache.php';
require_once PLKM_PLUGIN_DIR . 'includes/class-meta.php';
require_once PLKM_PLUGIN_DIR . 'includes/class-rest.php';
require_once PLKM_PLUGIN_DIR . 'includes/class-admin-columns.php';
require_once PLKM_PLUGIN_DIR . 'includes/class-meta-box.php';
require_once PLKM_PLUGIN_DIR . 'includes/class-front-protection.php';
require_once PLKM_PLUGIN_DIR . 'includes/class-updater.php';
require_once PLKM_PLUGIN_DIR . 'includes/class-plugin.php';

PageLock\Plugin::get_instance();
