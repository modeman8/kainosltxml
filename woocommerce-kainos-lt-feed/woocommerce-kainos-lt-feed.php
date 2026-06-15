<?php
/**
 * Plugin Name: WooCommerce Kainos.lt Feed
 * Plugin URI: https://kainos.lt/
 * Description: Generates a Kainos.lt compatible XML product feed for WooCommerce and updates it automatically every 12 hours.
 * Version: 1.0.0
 * Author: WebMode.lt
 * Author URI: https://webmode.lt
 * Text Domain: woocommerce-kainos-lt-feed
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 *
 * @package WooCommerceKainosLtFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WKLF_VERSION', '1.0.0' );
define( 'WKLF_PLUGIN_FILE', __FILE__ );
define( 'WKLF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WKLF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WKLF_CRON_HOOK', 'wklf_generate_feed_event' );
define( 'WKLF_STATUS_OPTION', 'wklf_feed_status' );
define( 'WKLF_LOG_OPTION', 'wklf_feed_log' );
define( 'WKLF_SETTINGS_OPTION', 'wklf_feed_settings' );

require_once WKLF_PLUGIN_DIR . 'includes/class-wklf-plugin.php';
require_once WKLF_PLUGIN_DIR . 'includes/class-wklf-feed-generator.php';
require_once WKLF_PLUGIN_DIR . 'includes/class-wklf-admin.php';

/**
 * Bootstraps the plugin.
 *
 * @return WKLF_Plugin
 */
function wklf() {
	return WKLF_Plugin::instance();
}

register_activation_hook( __FILE__, array( 'WKLF_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WKLF_Plugin', 'deactivate' ) );

wklf();
