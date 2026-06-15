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
	 * Generates the WooCommerce products XML feed.
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

		$products = $this->get_export_products();
		$xml      = $this->build_xml( $products );
		$written = file_put_contents( $paths['path'], $xml ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		if ( false === $written ) {
			$this->update_status( 'failed', __( 'Could not write feed file.', 'woocommerce-kainos-lt-feed' ), 0 );
			self::log( __( 'Failed to write Kainos.lt feed XML file.', 'woocommerce-kainos-lt-feed' ) );
			return false;
		}

		$total_products = count( $products );

		$this->update_status( 'success', __( 'Generated successfully.', 'woocommerce-kainos-lt-feed' ), $total_products );
		self::log(
			sprintf(
				/* translators: %d: Number of exported products. */
				__( 'Generated Kainos.lt XML feed with %d products.', 'woocommerce-kainos-lt-feed' ),
				$total_products
			)
		);

		return true;
	}

	/**
	 * Gets all published WooCommerce products that should be exported.
	 *
	 * Simple products are exported directly. Variable product parents are not exported;
	 * each published variation is exported as its own feed product instead.
	 *
	 * @return array<int,array<string,string|int>>
	 */
	private function get_export_products() {
		$export_products = array();
		$page            = 1;

		do {
			$query = wc_get_products(
				array(
					'status'   => 'publish',
					'type'     => array( 'simple', 'variable' ),
					'limit'    => 100,
					'page'     => $page,
					'paginate' => true,
					'return'   => 'objects',
				)
			);

			foreach ( $query->products as $product ) {
				if ( $product->is_type( 'variable' ) ) {
					$export_products = array_merge( $export_products, $this->get_variation_export_products( $product ) );
					continue;
				}

				$export_products[] = $this->format_export_product( $product );
			}

			$page++;
		} while ( $page <= $query->max_num_pages );

		return $export_products;
	}

	/**
	 * Gets formatted export rows for a variable product's published variations.
	 *
	 * @param WC_Product_Variable $product Parent variable product.
	 * @return array<int,array<string,string|int>>
	 */
	private function get_variation_export_products( $product ) {
		$export_products = array();

		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );

			if ( ! $variation || 'publish' !== $variation->get_status() ) {
				continue;
			}

			$export_products[] = $this->format_export_product( $variation, $product );
		}

		return $export_products;
	}

	/**
	 * Formats a WooCommerce product or variation for XML output.
	 *
	 * @param WC_Product      $product Product or variation.
	 * @param WC_Product|null $parent  Parent product for variations.
	 * @return array<string,string|int>
	 */
	private function format_export_product( $product, $parent = null ) {
		$category_product = $parent ? $parent : $product;

		return array(
			'id'          => $product->get_id(),
			'title'       => $parent ? $this->get_variation_title( $parent, $product ) : $product->get_name(),
			'item_price'  => $product->get_price(),
			'image_url'   => $this->get_product_image_url( $product, $parent ),
			'product_url' => $product->get_permalink(),
			'categories'  => implode( ', ', $this->get_category_paths( $category_product->get_id() ) ),
			'stock'       => $product->managing_stock() ? (int) $product->get_stock_quantity() : 999,
		);
	}

	/**
	 * Builds a variation title from parent title and selected attributes.
	 *
	 * @param WC_Product           $parent    Parent variable product.
	 * @param WC_Product_Variation $variation Product variation.
	 * @return string
	 */
	private function get_variation_title( $parent, $variation ) {
		$attributes = array();

		foreach ( $variation->get_attributes() as $attribute_name => $attribute_value ) {
			$attribute_value = $variation->get_attribute( $attribute_name );

			if ( '' === $attribute_value ) {
				continue;
			}

			$attributes[] = $attribute_value;
		}

		return implode( ' - ', array_filter( array_merge( array( $parent->get_name() ), $attributes ) ) );
	}

	/**
	 * Gets the product image URL, falling back from variation image to parent image.
	 *
	 * @param WC_Product      $product Product or variation.
	 * @param WC_Product|null $parent  Parent product for variations.
	 * @return string
	 */
	private function get_product_image_url( $product, $parent = null ) {
		$image_id = $product->get_image_id();

		if ( ! $image_id && $parent ) {
			$image_id = $parent->get_image_id();
		}

		return $image_id ? wp_get_attachment_url( $image_id ) : '';
	}

	/**
	 * Gets full WooCommerce category paths assigned to a product.
	 *
	 * @param int $product_id Product ID.
	 * @return array<int,string>
	 */
	private function get_category_paths( $product_id ) {
		$terms = get_the_terms( $product_id, 'product_cat' );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return array();
		}

		$paths = array();

		foreach ( $terms as $term ) {
			$ancestors = array_reverse( get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) );
			$names     = array();

			foreach ( $ancestors as $ancestor_id ) {
				$ancestor = get_term( $ancestor_id, 'product_cat' );
				if ( $ancestor && ! is_wp_error( $ancestor ) ) {
					$names[] = $ancestor->name;
				}
			}

			$names[] = $term->name;
			$paths[] = implode( '/', $names );
		}

		return array_values( array_unique( $paths ) );
	}

	/**
	 * Builds the Kainos.lt XML document.
	 *
	 * @param array<int,array<string,string|int>> $products Products to write.
	 * @return string
	 */
	private function build_xml( $products ) {
		$xml = new XMLWriter();
		$xml->openMemory();
		$xml->startDocument( '1.0', 'utf-8' );
		$xml->setIndent( true );
		$xml->startElement( 'products' );

		foreach ( $products as $product ) {
			$xml->startElement( 'product' );
			$xml->writeAttribute( 'id', (string) $product['id'] );

			foreach ( array( 'title', 'item_price', 'image_url', 'product_url', 'categories', 'stock' ) as $field ) {
				$xml->startElement( $field );
				$xml->writeCdata( (string) $product[ $field ] );
				$xml->endElement();
			}

			$xml->endElement();
		}

		$xml->endElement();
		$xml->endDocument();

		return $xml->outputMemory();
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
