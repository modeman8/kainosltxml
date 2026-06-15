<?php
/**
 * Admin UI for the Kainos.lt feed.
 *
 * @package WooCommerceKainosLtFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and handles the WooCommerce submenu page.
 */
class WKLF_Admin {
	/**
	 * Generator dependency.
	 *
	 * @var WKLF_Feed_Generator
	 */
	private $generator;

	/**
	 * Constructor.
	 *
	 * @param WKLF_Feed_Generator $generator Feed generator.
	 */
	public function __construct( WKLF_Feed_Generator $generator ) {
		$this->generator = $generator;
	}

	/**
	 * Registers admin hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_post_wklf_generate_feed', array( $this, 'handle_manual_generation' ) );
		add_action( 'admin_post_wklf_save_settings', array( $this, 'handle_save_settings' ) );
	}

	/**
	 * Adds the Kainos.lt feed page under WooCommerce.
	 *
	 * @return void
	 */
	public function add_menu_page() {
		add_submenu_page(
			'woocommerce',
			esc_html__( 'Kainos.lt Feed', 'woocommerce-kainos-lt-feed' ),
			esc_html__( 'Kainos.lt Feed', 'woocommerce-kainos-lt-feed' ),
			'manage_woocommerce',
			'wklf-kainos-lt-feed',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handles settings saving.
	 *
	 * @return void
	 */
	public function handle_save_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to update this feed.', 'woocommerce-kainos-lt-feed' ) );
		}

		check_admin_referer( 'wklf_save_settings', 'wklf_settings_nonce' );

		$source          = isset( $_POST['manufacturer_source'] ) ? sanitize_key( wp_unslash( $_POST['manufacturer_source'] ) ) : 'fixed_value';
		$allowed_sources = array( 'fixed_value', 'product_attribute', 'product_meta' );

		if ( ! in_array( $source, $allowed_sources, true ) ) {
			$source = 'fixed_value';
		}

		$delivery_text = isset( $_POST['delivery_text'] ) ? sanitize_text_field( wp_unslash( $_POST['delivery_text'] ) ) : '0 - 2 d.d.';
		if ( function_exists( 'mb_substr' ) ) {
			$delivery_text = mb_substr( $delivery_text, 0, 22, 'UTF-8' );
		} else {
			$delivery_text = substr( $delivery_text, 0, 22 );
		}

		$settings = array(
			'manufacturer_source'         => $source,
			'fixed_manufacturer_value'    => isset( $_POST['fixed_manufacturer_value'] ) ? sanitize_text_field( wp_unslash( $_POST['fixed_manufacturer_value'] ) ) : '',
			'manufacturer_attribute_slug' => isset( $_POST['manufacturer_attribute_slug'] ) ? sanitize_title( wp_unslash( $_POST['manufacturer_attribute_slug'] ) ) : '',
			'manufacturer_meta_key'       => isset( $_POST['manufacturer_meta_key'] ) ? sanitize_key( wp_unslash( $_POST['manufacturer_meta_key'] ) ) : '',
			'delivery_time'               => isset( $_POST['delivery_time'] ) ? max( 0, absint( $_POST['delivery_time'] ) ) : 2,
			'delivery_text'               => $delivery_text,
		);

		update_option( WKLF_SETTINGS_OPTION, $settings, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'wklf-kainos-lt-feed',
					'wklfmsg' => 'settings_saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handles manual feed generation.
	 *
	 * @return void
	 */
	public function handle_manual_generation() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to generate this feed.', 'woocommerce-kainos-lt-feed' ) );
		}

		check_admin_referer( 'wklf_generate_feed', 'wklf_nonce' );

		$success      = $this->generator->generate();
		$redirect_url = add_query_arg(
			array(
				'page'    => 'wklf-kainos-lt-feed',
				'wklfmsg' => $success ? 'generated' : 'failed',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Renders the admin page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'woocommerce-kainos-lt-feed' ) );
		}

		$status   = $this->generator->get_status();
		$paths    = $this->generator->get_feed_paths();
		$settings = WKLF_Feed_Generator::get_settings();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Kainos.lt Feed', 'woocommerce-kainos-lt-feed' ); ?></h1>

			<?php $this->render_notice(); ?>

			<h2><?php echo esc_html__( 'Feed status', 'woocommerce-kainos-lt-feed' ); ?></h2>
			<table class="widefat striped" style="max-width: 800px;">
				<tbody>
					<tr>
						<th scope="row"><?php echo esc_html__( 'XML feed URL', 'woocommerce-kainos-lt-feed' ); ?></th>
						<td><a href="<?php echo esc_url( $paths['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $paths['url'] ); ?></a></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Last generated date', 'woocommerce-kainos-lt-feed' ); ?></th>
						<td><?php echo esc_html( $status['last_generated'] ? $status['last_generated'] : __( 'Never', 'woocommerce-kainos-lt-feed' ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Total exported products', 'woocommerce-kainos-lt-feed' ); ?></th>
						<td><?php echo esc_html( (string) absint( $status['total_products'] ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Generation status', 'woocommerce-kainos-lt-feed' ); ?></th>
						<td><?php echo esc_html( $status['status'] . ' - ' . $status['message'] ); ?></td>
					</tr>
				</tbody>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 20px;">
				<input type="hidden" name="action" value="wklf_generate_feed" />
				<?php wp_nonce_field( 'wklf_generate_feed', 'wklf_nonce' ); ?>
				<?php submit_button( esc_html__( 'Generate XML Now', 'woocommerce-kainos-lt-feed' ), 'primary', 'submit', false ); ?>
			</form>

			<h2><?php echo esc_html__( 'Feed settings', 'woocommerce-kainos-lt-feed' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wklf_save_settings" />
				<?php wp_nonce_field( 'wklf_save_settings', 'wklf_settings_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="manufacturer_source"><?php echo esc_html__( 'Manufacturer source', 'woocommerce-kainos-lt-feed' ); ?></label></th>
						<td>
							<select id="manufacturer_source" name="manufacturer_source">
								<option value="fixed_value" <?php selected( $settings['manufacturer_source'], 'fixed_value' ); ?>><?php echo esc_html__( 'Fixed value', 'woocommerce-kainos-lt-feed' ); ?></option>
								<option value="product_attribute" <?php selected( $settings['manufacturer_source'], 'product_attribute' ); ?>><?php echo esc_html__( 'Product attribute', 'woocommerce-kainos-lt-feed' ); ?></option>
								<option value="product_meta" <?php selected( $settings['manufacturer_source'], 'product_meta' ); ?>><?php echo esc_html__( 'Product meta', 'woocommerce-kainos-lt-feed' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="fixed_manufacturer_value"><?php echo esc_html__( 'Fixed manufacturer value', 'woocommerce-kainos-lt-feed' ); ?></label></th>
						<td><input type="text" class="regular-text" id="fixed_manufacturer_value" name="fixed_manufacturer_value" value="<?php echo esc_attr( $settings['fixed_manufacturer_value'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="manufacturer_attribute_slug"><?php echo esc_html__( 'Manufacturer attribute slug', 'woocommerce-kainos-lt-feed' ); ?></label></th>
						<td><input type="text" class="regular-text" id="manufacturer_attribute_slug" name="manufacturer_attribute_slug" value="<?php echo esc_attr( $settings['manufacturer_attribute_slug'] ); ?>" placeholder="pa_brand" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="manufacturer_meta_key"><?php echo esc_html__( 'Manufacturer meta key', 'woocommerce-kainos-lt-feed' ); ?></label></th>
						<td><input type="text" class="regular-text" id="manufacturer_meta_key" name="manufacturer_meta_key" value="<?php echo esc_attr( $settings['manufacturer_meta_key'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="delivery_time"><?php echo esc_html__( 'Delivery time in days', 'woocommerce-kainos-lt-feed' ); ?></label></th>
						<td><input type="number" min="0" id="delivery_time" name="delivery_time" value="<?php echo esc_attr( (string) absint( $settings['delivery_time'] ) ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="delivery_text"><?php echo esc_html__( 'Delivery text', 'woocommerce-kainos-lt-feed' ); ?></label></th>
						<td><input type="text" maxlength="22" class="regular-text" id="delivery_text" name="delivery_text" value="<?php echo esc_attr( $settings['delivery_text'] ); ?>" /> <p class="description"><?php echo esc_html__( 'Maximum 22 characters.', 'woocommerce-kainos-lt-feed' ); ?></p></td>
					</tr>
				</table>
				<?php submit_button( esc_html__( 'Save settings', 'woocommerce-kainos-lt-feed' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders manual generation result notice.
	 *
	 * @return void
	 */
	private function render_notice() {
		if ( empty( $_GET['wklfmsg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$message_key = sanitize_key( wp_unslash( $_GET['wklfmsg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'generated' === $message_key ) {
			?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Kainos.lt XML feed generated.', 'woocommerce-kainos-lt-feed' ); ?></p></div>
			<?php
			return;
		}

		if ( 'settings_saved' === $message_key ) {
			?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Kainos.lt XML feed settings saved.', 'woocommerce-kainos-lt-feed' ); ?></p></div>
			<?php
			return;
		}

		if ( 'failed' === $message_key ) {
			?>
			<div class="notice notice-error is-dismissible"><p><?php echo esc_html__( 'Kainos.lt XML feed generation failed. Check the stored feed log option for details.', 'woocommerce-kainos-lt-feed' ); ?></p></div>
			<?php
		}
	}
}
