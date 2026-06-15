<?php
/**
 * Kainos.lt XML feed generator.
 *
 * @package WooCommerceKainosLtFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates and tracks the Kainos.lt feed file.
 */
class WKLF_Feed_Generator {
	/**
	 * Feed subdirectory inside uploads.
	 *
	 * @var string
	 */
	private $feed_dir = 'kainos-lt-feed';

	/**
	 * Feed file name.
	 *
	 * @var string
	 */
	private $feed_file = 'products.xml';

	/**
	 * Creates the version 1 test XML file.
	 *
	 * @return bool True on success, false otherwise.
	 */
	public function generate() {
		self::ensure_default_status();

		$paths = $this->get_feed_paths();

		if ( ! wp_mkdir_p( $paths['dir'] ) ) {
			$this->update_status( 'failed', __( 'Could not create feed directory.', 'woocommerce-kainos-lt-feed' ), 0 );
			self::log( __( 'Failed to create Kainos.lt feed directory.', 'woocommerce-kainos-lt-feed' ) );
			return false;
		}

		$xml     = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<products></products>\n";
		$written = file_put_contents( $paths['path'], $xml ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		if ( false === $written ) {
			$this->update_status( 'failed', __( 'Could not write feed file.', 'woocommerce-kainos-lt-feed' ), 0 );
			self::log( __( 'Failed to write Kainos.lt feed XML file.', 'woocommerce-kainos-lt-feed' ) );
			return false;
		}

		$this->update_status( 'success', __( 'Generated successfully.', 'woocommerce-kainos-lt-feed' ), 0 );
		self::log( __( 'Generated Kainos.lt test XML feed.', 'woocommerce-kainos-lt-feed' ) );

		return true;
	}

	/**
	 * Gets feed filesystem path and public URL.
	 *
	 * @return array{dir:string,path:string,url:string}
	 */
	public function get_feed_paths() {
		$uploads = wp_upload_dir();
		$baseurl = isset( $uploads['baseurl'] ) ? $uploads['baseurl'] : '';
		$basedir = isset( $uploads['basedir'] ) ? $uploads['basedir'] : '';

		return array(
			'dir'  => trailingslashit( $basedir ) . $this->feed_dir,
			'path' => trailingslashit( $basedir ) . $this->feed_dir . '/' . $this->feed_file,
			'url'  => trailingslashit( $baseurl ) . $this->feed_dir . '/' . $this->feed_file,
		);
	}

	/**
	 * Gets stored generation status.
	 *
	 * @return array<string,mixed>
	 */
	public function get_status() {
		self::ensure_default_status();
		$status = get_option( WKLF_STATUS_OPTION, array() );

		return wp_parse_args(
			$status,
			array(
				'last_generated' => '',
				'total_products'  => 0,
				'status'          => 'not_generated',
				'message'         => __( 'Feed has not been generated yet.', 'woocommerce-kainos-lt-feed' ),
			)
		);
	}

	/**
	 * Ensures the status option exists.
	 *
	 * @return void
	 */
	public static function ensure_default_status() {
		if ( false === get_option( WKLF_STATUS_OPTION, false ) ) {
			add_option(
				WKLF_STATUS_OPTION,
				array(
					'last_generated' => '',
					'total_products'  => 0,
					'status'          => 'not_generated',
					'message'         => __( 'Feed has not been generated yet.', 'woocommerce-kainos-lt-feed' ),
				),
				'',
				false
			);
		}
	}

	/**
	 * Adds a compact log entry to an option.
	 *
	 * @param string $message Log message.
	 * @return void
	 */
	public static function log( $message ) {
		$logs = get_option( WKLF_LOG_OPTION, array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		$logs[] = array(
			'time'    => current_time( 'mysql' ),
			'message' => sanitize_text_field( $message ),
		);

		$logs = array_slice( $logs, -50 );
		update_option( WKLF_LOG_OPTION, $logs, false );
	}

	/**
	 * Updates generation status.
	 *
	 * @param string $status Status code.
	 * @param string $message Human-readable message.
	 * @param int    $total_products Number of exported products.
	 * @return void
	 */
	private function update_status( $status, $message, $total_products ) {
		update_option(
			WKLF_STATUS_OPTION,
			array(
				'last_generated' => current_time( 'mysql' ),
				'total_products'  => absint( $total_products ),
				'status'          => sanitize_key( $status ),
				'message'         => sanitize_text_field( $message ),
			),
			false
		);
	}
}
