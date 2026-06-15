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
	 * Gets default feed settings.
	 *
	 * @return array<string,string|int>
	 */
	public static function get_default_settings() {
		return array(
			'manufacturer_source'         => 'fixed_value',
			'fixed_manufacturer_value'    => '',
			'manufacturer_attribute_slug' => '',
			'manufacturer_meta_key'       => '',
			'delivery_time'               => 2,
			'delivery_text'               => '0 - 2 d.d.',
			'ean_source'                  => 'product_meta',
			'ean_meta_key'                => '',
			'ean_attribute_slug'          => '',
			'manufacturer_code_source'    => 'sku',
			'manufacturer_code_meta_key'  => '',
			'model_source'                => 'product_title',
			'model_meta_key'              => '',
			'export_products'             => 'all',
		);
	}

	/**
	 * Gets stored feed settings with defaults.
	 *
	 * @return array<string,string|int>
	 */
	public static function get_settings() {
		$settings = get_option( WKLF_SETTINGS_OPTION, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return wp_parse_args( $settings, self::get_default_settings() );
	}

	/**
	 * Ensures the settings option exists.
	 *
	 * @return void
	 */
	public static function ensure_default_settings() {
		if ( false === get_option( WKLF_SETTINGS_OPTION, false ) ) {
			add_option( WKLF_SETTINGS_OPTION, self::get_default_settings(), '', false );
		}
	}

	/**
	 * Generates the WooCommerce products XML feed.
	 *
	 * @return bool True on success, false otherwise.
	 */
	public function generate() {
		self::ensure_default_status();
		self::ensure_default_settings();

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
	 * @return array<int,array<string,mixed>>
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

			$settings = self::get_settings();

			foreach ( $query->products as $product ) {
				if ( $product->is_type( 'variable' ) ) {
					$export_products = array_merge( $export_products, $this->get_variation_export_products( $product ) );
					continue;
				}

				if ( 'in_stock' === $settings['export_products'] && ! $product->is_in_stock() ) {
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
	 * @return array<int,array<string,mixed>>
	 */
	private function get_variation_export_products( $product ) {
		$export_products = array();

		$settings = self::get_settings();

		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );

			if ( ! $variation || 'publish' !== $variation->get_status() ) {
				continue;
			}

			if ( 'in_stock' === $settings['export_products'] && ! $variation->is_in_stock() ) {
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
	 * @return array<string,mixed>
	 */
	private function format_export_product( $product, $parent = null ) {
		$category_product = $parent ? $parent : $product;
		$settings         = self::get_settings();

		$title = $parent ? $this->get_variation_title( $parent, $product ) : $product->get_name();

		return array(
			'id'                => $product->get_id(),
			'title'             => $title,
			'item_price'        => $this->format_price( wc_get_price_to_display( $product ) ),
			'manufacturer'      => $this->get_manufacturer( $product, $parent ),
			'ean_code'          => $this->get_ean_code( $product, $parent ),
			'manufacturer_code' => $this->get_manufacturer_code( $product, $parent ),
			'model'             => $this->get_model( $product, $parent, $title ),
			'image_url'         => $this->get_product_image_url( $product, $parent ),
			'additional_images' => $this->get_additional_image_urls( $product, $parent ),
			'product_url'       => $this->get_product_url( $product, $parent ),
			'categories'        => $this->get_category_paths( $category_product->get_id() ),
			'description'       => $this->get_product_description( $parent ? $parent : $product ),
			'stock'             => $this->get_stock_value( $product ),
			'delivery_time'     => absint( $settings['delivery_time'] ),
			'delivery_text'     => $this->trim_text( (string) $settings['delivery_text'], 22 ),
		);
	}

	/**
	 * Formats a price with a dot decimal separator and two decimals.
	 *
	 * @param float|string $price Price.
	 * @return string
	 */
	private function format_price( $price ) {
		return number_format( (float) $price, 2, '.', '' );
	}

	/**
	 * Gets manufacturer according to admin settings.
	 *
	 * @param WC_Product      $product Product or variation.
	 * @param WC_Product|null $parent  Parent product for variations.
	 * @return string
	 */
	private function get_manufacturer( $product, $parent = null ) {
		$settings = self::get_settings();
		$source   = isset( $settings['manufacturer_source'] ) ? (string) $settings['manufacturer_source'] : 'fixed_value';
		$value    = '';

		if ( 'product_attribute' === $source && ! empty( $settings['manufacturer_attribute_slug'] ) ) {
			$value = $this->get_attribute_value( $product, $parent, (string) $settings['manufacturer_attribute_slug'] );
		} elseif ( 'product_meta' === $source && ! empty( $settings['manufacturer_meta_key'] ) ) {
			$value = $this->get_meta_value( $product, $parent, (string) $settings['manufacturer_meta_key'] );
		} elseif ( ! empty( $settings['fixed_manufacturer_value'] ) ) {
			$value = (string) $settings['fixed_manufacturer_value'];
		}

		$value = $this->plain_text( $value );

		return '' !== $value ? $value : get_bloginfo( 'name' );
	}

	/**
	 * Gets EAN code according to admin settings.
	 *
	 * @param WC_Product      $product Product or variation.
	 * @param WC_Product|null $parent  Parent product for variations.
	 * @return string
	 */
	private function get_ean_code( $product, $parent = null ) {
		$settings = self::get_settings();
		$value    = '';

		if ( 'product_attribute' === $settings['ean_source'] && ! empty( $settings['ean_attribute_slug'] ) ) {
			$value = $this->get_attribute_value( $product, $parent, (string) $settings['ean_attribute_slug'] );
		} elseif ( ! empty( $settings['ean_meta_key'] ) ) {
			$value = $this->get_meta_value( $product, $parent, (string) $settings['ean_meta_key'] );
		}

		return $this->plain_text( $value );
	}

	/**
	 * Gets manufacturer code according to admin settings.
	 *
	 * @param WC_Product      $product Product or variation.
	 * @param WC_Product|null $parent  Parent product for variations.
	 * @return string
	 */
	private function get_manufacturer_code( $product, $parent = null ) {
		$settings = self::get_settings();
		$value    = '';

		if ( 'product_meta' === $settings['manufacturer_code_source'] && ! empty( $settings['manufacturer_code_meta_key'] ) ) {
			$value = $this->get_meta_value( $product, $parent, (string) $settings['manufacturer_code_meta_key'] );
		} else {
			$value = $product->get_sku();
		}

		return $this->plain_text( $value );
	}

	/**
	 * Gets model according to admin settings.
	 *
	 * @param WC_Product      $product Product or variation.
	 * @param WC_Product|null $parent  Parent product for variations.
	 * @param string          $title   Product title.
	 * @return string
	 */
	private function get_model( $product, $parent, $title ) {
		$settings = self::get_settings();
		$value    = $title;

		if ( 'sku' === $settings['model_source'] ) {
			$value = $product->get_sku();
		} elseif ( 'product_meta' === $settings['model_source'] && ! empty( $settings['model_meta_key'] ) ) {
			$value = $this->get_meta_value( $product, $parent, (string) $settings['model_meta_key'] );
		}

		return $this->plain_text( $value );
	}

	/**
	 * Gets a product attribute value from product or parent.
	 *
	 * @param WC_Product      $product Product or variation.
	 * @param WC_Product|null $parent  Parent product for variations.
	 * @param string          $slug    Attribute slug.
	 * @return string
	 */
	private function get_attribute_value( $product, $parent, $slug ) {
		$slug       = wc_sanitize_taxonomy_name( str_replace( 'attribute_', '', $slug ) );
		$candidates = array_unique( array( $slug, 'pa_' . preg_replace( '/^pa_/', '', $slug ) ) );

		foreach ( array( $product, $parent ) as $target ) {
			if ( ! $target ) {
				continue;
			}

			foreach ( $candidates as $candidate ) {
				$value = $target->get_attribute( $candidate );
				if ( '' !== $value ) {
					return $value;
				}
			}
		}

		return '';
	}

	/**
	 * Gets product meta value from product or parent.
	 *
	 * @param WC_Product      $product Product or variation.
	 * @param WC_Product|null $parent  Parent product for variations.
	 * @param string          $meta_key Meta key.
	 * @return string
	 */
	private function get_meta_value( $product, $parent, $meta_key ) {
		foreach ( array( $product, $parent ) as $target ) {
			if ( ! $target ) {
				continue;
			}

			$value = get_post_meta( $target->get_id(), $meta_key, true );
			if ( is_scalar( $value ) && '' !== (string) $value ) {
				return (string) $value;
			}
		}

		return '';
	}

	/**
	 * Gets stock value according to Kainos.lt feed rules.
	 *
	 * @param WC_Product $product Product or variation.
	 * @return int
	 */
	private function get_stock_value( $product ) {
		if ( ! $product->is_in_stock() ) {
			return 0;
		}

		if ( $product->managing_stock() && null !== $product->get_stock_quantity() ) {
			return max( 0, (int) $product->get_stock_quantity() );
		}

		return 999;
	}

	/**
	 * Builds a variation title from parent title and selected visible attribute values.
	 *
	 * @param WC_Product           $parent    Parent variable product.
	 * @param WC_Product_Variation $variation Product variation.
	 * @return string
	 */
	private function get_variation_title( $parent, $variation ) {
		$attributes = array();

		foreach ( $variation->get_variation_attributes() as $attribute_name => $attribute_value ) {
			$taxonomy = str_replace( 'attribute_', '', $attribute_name );
			$value    = $variation->get_attribute( $taxonomy );

			if ( '' === $value && taxonomy_exists( $taxonomy ) ) {
				$term = get_term_by( 'slug', $attribute_value, $taxonomy );
				if ( $term && ! is_wp_error( $term ) ) {
					$value = $term->name;
				}
			}

			if ( '' !== $value ) {
				$attributes[] = $this->plain_text( $value );
			}
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
	 * Gets additional gallery image URLs, excluding the main product image.
	 *
	 * @param WC_Product      $product Product or variation.
	 * @param WC_Product|null $parent  Parent product for variations.
	 * @return array<int,string>
	 */
	private function get_additional_image_urls( $product, $parent = null ) {
		$main_image_id = $product->get_image_id();
		$image_ids     = method_exists( $product, 'get_gallery_image_ids' ) ? $product->get_gallery_image_ids() : array();

		if ( empty( $image_ids ) && $parent ) {
			$image_ids = method_exists( $parent, 'get_gallery_image_ids' ) ? $parent->get_gallery_image_ids() : array();
		}

		if ( ! $main_image_id && $parent ) {
			$main_image_id = $parent->get_image_id();
		}

		$urls = array();
		foreach ( array_unique( array_map( 'absint', $image_ids ) ) as $image_id ) {
			if ( ! $image_id || $image_id === (int) $main_image_id ) {
				continue;
			}

			$url = wp_get_attachment_url( $image_id );
			if ( $url ) {
				$urls[] = esc_url_raw( $url );
			}
		}

		return array_values( array_unique( $urls ) );
	}

	/**
	 * Gets product URL, including variation attributes when possible.
	 *
	 * @param WC_Product      $product Product or variation.
	 * @param WC_Product|null $parent  Parent product for variations.
	 * @return string
	 */
	private function get_product_url( $product, $parent = null ) {
		if ( ! $parent ) {
			return $product->get_permalink();
		}

		$url        = $parent->get_permalink();
		$attributes = array_filter( $product->get_variation_attributes() );

		return empty( $attributes ) ? $url : add_query_arg( $attributes, $url );
	}

	/**
	 * Gets plain product description, preferring short description.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	private function get_product_description( $product ) {
		$description = $product->get_short_description();

		if ( '' === trim( wp_strip_all_tags( strip_shortcodes( $description ) ) ) ) {
			$description = $product->get_description();
		}

		return $this->plain_text( $description );
	}

	/**
	 * Converts content to UTF-8 plain text.
	 *
	 * @param string $value Text value.
	 * @return string
	 */
	private function plain_text( $value ) {
		$value = wp_strip_all_tags( strip_shortcodes( (string) $value ) );
		$value = html_entity_decode( $value, ENT_QUOTES, 'UTF-8' );
		$value = preg_replace( '/\s+/u', ' ', $value );
		$value = is_string( $value ) ? trim( $value ) : '';

		return function_exists( 'wp_check_invalid_utf8' ) ? wp_check_invalid_utf8( $value, true ) : $value;
	}

	/**
	 * Trims text to a maximum character length.
	 *
	 * @param string $value  Text value.
	 * @param int    $length Maximum length.
	 * @return string
	 */
	private function trim_text( $value, $length ) {
		$value = $this->plain_text( $value );

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $length, 'UTF-8' );
		}

		return substr( $value, 0, $length );
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
			$paths[] = implode( '/', array_map( array( $this, 'plain_text' ), $names ) );
		}

		return array_values( array_unique( $paths ) );
	}

	/**
	 * Builds the Kainos.lt XML document.
	 *
	 * @param array<int,array<string,mixed>> $products Products to write.
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

			foreach ( array( 'title', 'item_price', 'manufacturer', 'image_url', 'product_url' ) as $field ) {
				$this->write_cdata_element( $xml, $field, (string) $product[ $field ] );
			}

			foreach ( array( 'ean_code', 'manufacturer_code', 'model' ) as $field ) {
				$this->write_optional_cdata_element( $xml, $field, (string) $product[ $field ] );
			}

			if ( ! empty( $product['additional_images'] ) ) {
				$xml->startElement( 'additional_images' );
				foreach ( $product['additional_images'] as $image_url ) {
					$this->write_cdata_element( $xml, 'image', (string) $image_url );
				}
				$xml->endElement();
			}

			$xml->startElement( 'categories' );
			foreach ( $product['categories'] as $category ) {
				$this->write_cdata_element( $xml, 'category', (string) $category );
			}
			$xml->endElement();

			$this->write_cdata_element( $xml, 'description', (string) $product['description'] );
			$this->write_cdata_element( $xml, 'stock', (string) $product['stock'] );
			$xml->writeElement( 'delivery_time', (string) absint( $product['delivery_time'] ) );
			$this->write_cdata_element( $xml, 'delivery_text', (string) $product['delivery_text'] );

			$xml->endElement();
		}

		$xml->endElement();
		$xml->endDocument();

		return $xml->outputMemory();
	}

	/**
	 * Writes a CDATA element.
	 *
	 * @param XMLWriter $xml   XML writer.
	 * @param string    $name  Element name.
	 * @param string    $value Element value.
	 * @return void
	 */
	private function write_cdata_element( $xml, $name, $value ) {
		$value = function_exists( 'wp_check_invalid_utf8' ) ? wp_check_invalid_utf8( $value, true ) : $value;
		$value = str_replace( ']]>', ']]]]><![CDATA[>', $value );

		$xml->startElement( $name );
		$xml->writeRaw( '<![CDATA[' . $value . ']]>' );
		$xml->endElement();
	}

	/**
	 * Writes a CDATA element only when value is not empty.
	 *
	 * @param XMLWriter $xml   XML writer.
	 * @param string    $name  Element name.
	 * @param string    $value Element value.
	 * @return void
	 */
	private function write_optional_cdata_element( $xml, $name, $value ) {
		if ( '' === $value ) {
			return;
		}

		$this->write_cdata_element( $xml, $name, $value );
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
