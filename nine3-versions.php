<?php
/**
 * Plugin Name: 93digital Versions
 * Plugin URI: https://93digital.co.uk/
 * Description: Adds ability to quickly clone page hierarchy trees and eases page sorting/versioning.
 * Version: 1.0
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Author: 93digital
 * Author URI: https://93digital.co.uk/
 * License: GPLv2 or later
 * Text Domain: nine3versions
 *
 * @package nine3versions
 */

namespace nine3versions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Base filepath and URL constants, without a trailing slash.
define( 'NINE3_VERSIONS_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'NINE3_VERSIONS_URI', plugins_url( plugin_basename( __DIR__ ) ) );

/**
 * 'spl_autoload_register' callback function.
 * Autoloads all the required plugin classes, found in the /classes directory (relative to the plugin's root).
 *
 * @param string $class The name of the class being instantiated inculding its namespaces.
 */
function autoloader( $class ) {
	// $class returns the classname including any namespaces - this removes the namespace so we can locate the class's file.
	$raw_class = explode( '\\', $class );
	$filename  = str_replace( '_', '-', strtolower( end( $raw_class ) ) );

	$filepath = __DIR__ . '/class/class-' . $filename . '.php';

	if ( file_exists( $filepath ) ) {
		include_once $filepath;
	}
}
spl_autoload_register( __NAMESPACE__ . '\autoloader' );

/**
 * Init class.
 */
$nine3_versions    = new Versions();
$GLOBALS['nine3v'] = $nine3_versions;
