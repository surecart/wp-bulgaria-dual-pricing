<?php
/**
 * Set the pricing attribute for the product.
 *
 * @package SureCartBulgariaDualPricing
 */

use SureCart\Support\Currency;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Set the pricing attribute for the product.
 */
class SureCartBulgariaSetPricingAttribute {

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'surecart/price/attributes_set', array( $this, 'set_pricing_attribute' ) );
		add_action( 'surecart/variant/attributes_set', array( $this, 'set_pricing_attribute' ) );
		add_action( 'surecart/line_item/attributes_set', array( $this, 'set_line_item_attribute' ) );
		add_filter( 'rest_post_dispatch', array( $this, 'modify_checkout_rest_response' ), 10, 3 );
		add_action( 'render_block', array( $this, 'update_selected_price' ), 10, 2 );
		add_action( 'render_block', array( $this, 'update_list_price' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_block_assets' ) );
	}

	/**
	 * Enqueue the block assets.
	 *
	 * @return void
	 */
	public function enqueue_block_assets() {
		wp_register_script_module( 'surecart-bulgaria-dual-pricing', plugin_dir_url( __FILE__ ) . 'surecart-bulgaria-dual-pricing.js', array( '@surecart/product-page' ) );
	}

	/**
	 * Get the context for the block.
	 *
	 * @return array The context.
	 */
	public function get_context() {
		$product = sc_get_product();

		return array(
			'bgnPrices'   => array_map(
				fn( $price ) => $price->only(
					array(
						'id',
						'bgn_display_amount',
					)
				),
				$product->active_prices ?? array()
			),
			'bgnVariants' => array_map(
				fn( $variant ) => $variant->only(
					array(
						'id',
						'bgn_display_amount',
					)
				),
				$product->variants->data ?? array()
			),
		);
	}

	/**
	 * Render the block.
	 *
	 * @param string $block_content The block content.
	 * @param array  $block The block.
	 * @return string The block content.
	 */
	public function update_selected_price( $block_content, $block ) {
		if ( 'surecart/product-selected-price-amount' !== $block['blockName'] ) {
			return $block_content;
		}

		// Enqueue the script only when this block is rendered.
		wp_enqueue_script_module( 'surecart-bulgaria-dual-pricing' );

		// Build the additional content to inject.
		ob_start();
		?>
		<span data-wp-interactive='{ "namespace": "surecart/bulgaria-dual-pricing" }'>
			<span
				<?php
				echo wp_kses_data(
					wp_interactivity_data_wp_context(
						$this->get_context()
					)
				);
				?>
				data-wp-text="state.selectedDisplayAmount"></span>
		</span>
		<?php
		$additional_content = ob_get_clean();

		// Use WP_HTML_Tag_Processor to inject content inside the wrapper.
		$processor = new WP_HTML_Tag_Processor( $block_content );

		// Find the outermost span (the block wrapper).
		if ( $processor->next_tag( 'span' ) ) {
			// Set a bookmark at the opening tag.
			$processor->set_bookmark( 'wrapper' );

			// Navigate to find the closing tag of the wrapper.
			$depth = 0;
			while ( $processor->next_token() ) {
				if ( '#tag' === $processor->get_token_type() && 'SPAN' === $processor->get_tag() ) {
					if ( $processor->is_tag_closer() ) {
						if ( 0 === $depth ) {
							// We found the closing tag of our wrapper.
							// Get content up to this point.
							$html_before_closing = $processor->get_updated_html();

							// Find the position of the last </span>.
							$closing_tag_pos = strrpos( $html_before_closing, '</span>' );

							if ( false !== $closing_tag_pos ) {
								// Insert our content before the closing tag.
								return substr( $html_before_closing, 0, $closing_tag_pos ) .
									$additional_content .
									substr( $html_before_closing, $closing_tag_pos );
							}
							break;
						}
						--$depth;
					} else {
						++$depth;
					}
				}
			}
		}

		// Fallback: if processor fails, append after the block.
		return $block_content . $additional_content;
	}

	/**
	 * Update the list price block with BGN pricing.
	 *
	 * @param string $block_content The block content.
	 * @param array  $block The block.
	 * @return string The block content.
	 */
	public function update_list_price( $block_content, $block ) {
		if ( 'surecart/product-list-price' !== $block['blockName'] ) {
			return $block_content;
		}

		$product = sc_get_product();

		$additional_content = $product->initial_price->bgn_display_amount;

		// Use WP_HTML_Tag_Processor to inject content inside the wrapper.
		$processor = new WP_HTML_Tag_Processor( $block_content );

		// Find the outermost div (the block wrapper).
		if ( $processor->next_tag( 'div' ) ) {
			// Set a bookmark at the opening tag.
			$processor->set_bookmark( 'wrapper' );

			// Navigate to find the closing tag of the wrapper.
			$depth = 0;
			while ( $processor->next_token() ) {
				if ( '#tag' === $processor->get_token_type() && 'DIV' === $processor->get_tag() ) {
					if ( $processor->is_tag_closer() ) {
						if ( 0 === $depth ) {
							// We found the closing tag of our wrapper.
							// Get content up to this point.
							$html_before_closing = $processor->get_updated_html();

							// Find the position of the last </div>.
							$closing_tag_pos = strrpos( $html_before_closing, '</div>' );

							if ( false !== $closing_tag_pos ) {
								// Insert our content before the closing tag.
								return substr( $html_before_closing, 0, $closing_tag_pos ) .
									' ' . $additional_content .
									substr( $html_before_closing, $closing_tag_pos );
							}
							break;
						}
						--$depth;
					} else {
						++$depth;
					}
				}
			}
		}

		// Fallback: if processor fails, append after the block.
		return $block_content . ' ' . $additional_content;
	}

	/**
	 * Set the pricing attribute for the price.
	 *
	 * @param \SureCart\Models\Price $price The price model.
	 * @return void
	 */
	public function set_pricing_attribute( $price ) {
		if ( 'eur' !== $price->currency ) {
			return;
		}
		$bgn_amount         = $price->amount * 1.95583; // Convert EUR to BGN using fixed rate: 1 EUR = 1.95583 BGN.
		$bgn_display_amount = empty( $bgn_amount ) ? '' : Currency::format( $bgn_amount, 'bgn' );
		$price->setAttribute( 'bgn_display_amount', $bgn_display_amount );
	}

	/**
	 * Modify the REST response to append BGN pricing to checkout display amounts.
	 *
	 * @param \WP_REST_Response $response The response object.
	 * @param \WP_REST_Server   $server   The REST server.
	 * @param \WP_REST_Request  $request  The request object.
	 * @return \WP_REST_Response
	 */
	public function modify_checkout_rest_response( $response, $server, $request ) {
		// Only modify checkout endpoints.
		if ( strpos( $request->get_route(), '/surecart/v1/checkouts' ) === false ) {
			return $response;
		}

		$data = $response->get_data();

		if ( empty( $data ) || ( ! is_array( $data ) && ! is_object( $data ) ) ) {
			return $response;
		}

		// Convert to array for easier manipulation.
		$data = (array) $data;

		// Only modify EUR checkouts.
		if ( empty( $data['currency'] ) || 'eur' !== $data['currency'] ) {
			return $response;
		}

		$data = $this->append_bgn_pricing( $data );
		$response->set_data( $data );

		return $response;
	}

	/**
	 * Append BGN pricing to checkout display amounts.
	 *
	 * @param array $data The checkout data.
	 * @return array
	 */
	private function append_bgn_pricing( $data ) {
		// Append BGN total.
		if ( ! empty( $data['total_amount'] ) ) {
			$bgn_total                    = $data['total_amount'] * 1.95583;
			$bgn_display                  = Currency::format( $bgn_total, 'bgn' );
			$data['total_display_amount'] = ( $data['total_display_amount'] ?? '' ) . ' ' . $bgn_display;
		}

		// Append BGN subtotal.
		if ( ! empty( $data['subtotal_amount'] ) ) {
			$bgn_subtotal                    = $data['subtotal_amount'] * 1.95583;
			$bgn_display                     = Currency::format( $bgn_subtotal, 'bgn' );
			$data['subtotal_display_amount'] = ( $data['subtotal_display_amount'] ?? '' ) . ' ' . $bgn_display;
		}

		return $data;
	}

	/**
	 * Set the line item attribute for BGN display.
	 * Appends BGN amount directly to ad_hoc_display_amount for web component compatibility.
	 *
	 * @param \SureCart\Models\LineItem $line_item The line item model.
	 * @return void
	 */
	public function set_line_item_attribute( $line_item ) {
		// Get currency from the expanded checkout or price.
		$currency = $line_item->checkout->currency ?? $line_item->price->currency ?? null;
		if ( 'eur' !== $currency ) {
			return;
		}

		// Convert ad_hoc_amount if present.
		if ( ! empty( $line_item->ad_hoc_amount ) ) {
			$bgn_amount = $line_item->ad_hoc_amount * 1.95583;
			$bgn_display = ' ' . Currency::format( $bgn_amount, 'bgn' );
			$line_item->setAttribute( 'ad_hoc_display_amount', $line_item->ad_hoc_display_amount . $bgn_display );
		}

		// Convert subtotal_amount if present.
		if ( ! empty( $line_item->subtotal_amount ) ) {
			$bgn_subtotal = $line_item->subtotal_amount * 1.95583;
			$bgn_subtotal_display = ' ' . Currency::format( $bgn_subtotal, 'bgn' );
			$line_item->setAttribute( 'subtotal_display_amount', $line_item->subtotal_display_amount . $bgn_subtotal_display );
		}
	}
}
