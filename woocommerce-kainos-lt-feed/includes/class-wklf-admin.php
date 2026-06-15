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
	 * Handles manual feed generation.
	 *
	 * @return void
	 */
	public function handle_manual_generation() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to generate this feed.', 'woocommerce-kainos-lt-feed' ) );
		}

		check_admin_referer( 'wklf_generate_feed', 'wklf_nonce' );

		$success = $this->generator->generate();
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

		$status = $this->generator->get_status();
		$paths  = $this->generator->get_feed_paths();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Kainos.lt Feed', 'woocommerce-kainos-lt-feed' ); ?></h1>

			<?php $this->render_notice(); ?>

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

		if ( 'failed' === $message_key ) {
			?>
			<div class="notice notice-error is-dismissible"><p><?php echo esc_html__( 'Kainos.lt XML feed generation failed. Check the stored feed log option for details.', 'woocommerce-kainos-lt-feed' ); ?></p></div>
			<?php
		}
	}
}
