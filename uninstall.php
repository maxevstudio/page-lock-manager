<?php
/**
 * Nettoyage à la désinstallation du plugin.
 *
 * @package PageLock
 */

// WordPress appelle ce fichier uniquement lors d'une désinstallation réelle
// (pas une simple désactivation) et définit toujours cette constante avant.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Supprime la protection de toutes les pages : après désinstallation, plus
// rien ne les protège de toute façon.
foreach ( [ '_plm_locked', '_plm_password', '_plm_password_hash', '_plm_has_password' ] as $plkm_meta_key ) {
	delete_post_meta_by_key( $plkm_meta_key );
}

delete_transient( 'plkm_github_release' );
