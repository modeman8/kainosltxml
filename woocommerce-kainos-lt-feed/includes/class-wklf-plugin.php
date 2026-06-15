<?php
/**
 * Core plugin class.
 *
 * @package WooCommerceKainosLtFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates plugin services and lifecycle hooks.
 */
class WKLF_Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var WKLF_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Feed generator instance.
	 *
	 * @var WKLF_Feed_Generator
	 */
	private $generator;

	/**
	 * Admin page instance.
	 *
	 * @var WKLF_Admin
	 */
	private $admin;

	/**
	 * Gets the singleton instance.
	 *
	 * @return WKLF_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->generator = new WKLF_Feed_Generator();
		$this->admin     = new WKLF_Admin( $this->generator );

		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( WKLF_CRON_HOOK, array( $this->generator, 'generate' ) );
	}

	/**
	 * Initializes plugin integrations after plugins are loaded.
	 *
	 * @return void
	 */
	public function init() {
		if ( ! self::is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			WKLF_Feed_Generator::log( __( 'WooCommerce is not active. Kainos.lt feed generation is unavailable.', 'woocommerce-kainos-lt-feed' ) );
			return;
		}

		$this->admin->init();
	}

	/**
	 * Runs on plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		self::schedule_cron();
		WKLF_Feed_Generator::ensure_default_status();
		WKLF_Feed_Generator::ensure_default_settings();
		WKLF_Feed_Generator::log( __( 'Plugin activated and Kainos.lt feed cron scheduled.', 'woocommerce-kainos-lt-feed' ) );
	}

	/**
	 * Runs on plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( WKLF_CRON_HOOK );
		WKLF_Feed_Generator::log( __( 'Plugin deactivated and Kainos.lt feed cron cleared.', 'woocommerce-kainos-lt-feed' ) );
	}

	/**
	 * Schedules feed generation every 12 hours.
	 *
	 * @return void
	 */
	private static function schedule_cron() {
		if ( ! wp_next_scheduled( WKLF_CRON_HOOK ) ) {
			wp_schedule_event( time(), 'twicedaily', WKLF_CRON_HOOK );
		}
	}

	/**
	 * Checks whether WooCommerce is active and loaded.
	 *
	 * @return bool
	 */
	public static function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Displays an admin notice when WooCommerce is missing.
	 *
	 * @return void
	 */
	public function woocommerce_missing_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p><?php echo esc_html__( 'WooCommerce Kainos.lt Feed requires WooCommerce to be active.', 'woocommerce-kainos-lt-feed' ); ?></p>
		</div>
		<?php
	}
}
