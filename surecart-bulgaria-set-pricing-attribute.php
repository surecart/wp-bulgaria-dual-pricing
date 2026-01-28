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
	 * EUR to BGN conversion rate.
	 * This is the fixed rate: 1 EUR = 1.95583 BGN
	 *
	 * @var float
	 */
	const EUR_TO_BGN_RATE = 1.95583;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'surecart/price/attributes_set', array( $this, 'set_pricing_attribute' ) );
		add_action( 'surecart/variant/attributes_set', array( $this, 'set_pricing_attribute' ) );
		add_filter( 'rest_post_dispatch', array( $this, 'modify_checkout_rest_response' ), 10, 3 );
		add_action( 'render_block', array( $this, 'update_selected_price' ), 10, 2 );
		add_action( 'render_block', array( $this, 'update_list_price' ), 10, 2 );
		add_action( 'render_block', array( $this, 'update_donation_amount' ), 10, 2 );
		add_action( 'render_block', array( $this, 'update_price_amount' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_block_assets' ) );
	}

	/**
	 * Enqueue the block assets.
	 *
	 * @return void
	 */
	public function enqueue_block_assets() {
		// Only enqueue the product page dual pricing script (for variant/price selection)
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
		if ( empty( $product->initial_price ) ) {
			return $block_content;
		}

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
									' (' . $additional_content . ')' .
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
		return $block_content . ' (' . $additional_content . ')';
	}

	/**
	 * Update the donation amount block with BGN pricing.
	 *
	 * @param string $block_content The block content.
	 * @param array  $block The block.
	 * @return string The block content.
	 */
	public function update_donation_amount( $block_content, $block ) {
		if ( 'surecart/product-donation-amount' !== $block['blockName'] ) {
			return $block_content;
		}

		// Get the amount from block attributes (in cents).
		$amount = $block['attrs']['amount'] ?? 0;
		if ( empty( $amount ) ) {
			return $block_content;
		}

		// Calculate BGN amount and format it.
		$bgn_amount  = $amount * self::EUR_TO_BGN_RATE;
		$bgn_display = Currency::format( $bgn_amount, 'bgn' );

		// Use WP_HTML_Tag_Processor to modify the label attribute.
		$processor = new WP_HTML_Tag_Processor( $block_content );

		if ( $processor->next_tag( 'sc-product-donation-amount-choice' ) ) {
			$current_label = $processor->get_attribute( 'label' );

			// Only update if there's an existing EUR label and it doesn't already contain BGN.
			if ( ! empty( $current_label ) && strpos( $current_label, 'BGN' ) === false ) {
				$new_label = $current_label . ' (' . $bgn_display . ')';
				$processor->set_attribute( 'label', $new_label );
				return $processor->get_updated_html();
			}
		}

		return $block_content;
	}

	/**
	 * Update the price amount block with BGN pricing (for price chooser options).
	 *
	 * @param string $block_content The block content.
	 * @param array  $block The block.
	 * @return string The block content.
	 */
	public function update_price_amount( $block_content, $block ) {
		if ( 'surecart/price-amount' !== $block['blockName'] && ! empty( $block_content ) ) {
			return $block_content;
		}

		// TODO: Do investigate why $block['context']['price'] is not available here.
		// stop if € or EUR is not found.
		if ( strpos( $block_content, '€' ) === false && strpos( $block_content, 'EUR' ) === false ) {
			return $block_content;
		}

		// Extract amount and currency.
		$amount = str_replace( array( '€', 'EUR' ), '', strip_tags( $block_content ) );
		$amount = str_replace( ',', '', $amount ); // Remove thousands separator if any

		$price = array(
			'amount'   => floatval( trim( $amount ) ) * 100,
			'currency' => 'eur',
		);

		// Handle both object and array formats.
		$amount   = is_object( $price ) ? ( $price->amount ?? null ) : ( $price['amount'] ?? null );
		$currency = is_object( $price ) ? ( $price->currency ?? null ) : ( $price['currency'] ?? null );

		if ( empty( $amount ) || empty( $currency ) ) {
			return $block_content;
		}

		// Only convert EUR prices.
		if ( 'eur' !== $currency ) {
			return $block_content;
		}

		// Calculate BGN amount and format it.
		$bgn_amount  = $amount * self::EUR_TO_BGN_RATE;
		$bgn_display = Currency::format( $bgn_amount, 'bgn' );

		// Append BGN to the block content inside the span.
		$processor = new WP_HTML_Tag_Processor( $block_content );

		if ( $processor->next_tag( 'span' ) ) {
			// Get the current HTML content.
			$html = $processor->get_updated_html();

			// Find the closing </span> and insert BGN before it.
			$closing_pos = strrpos( $html, '</span>' );
			if ( false !== $closing_pos ) {
				return substr( $html, 0, $closing_pos ) . ' (' . $bgn_display . ')' . substr( $html, $closing_pos );
			}
		}

		// Fallback: append after content.
		return $block_content . ' (' . $bgn_display . ')';
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
		$bgn_amount         = $price->amount * self::EUR_TO_BGN_RATE;
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
		$route = $request->get_route();

		// Handle checkout endpoints.
		if ( strpos( $route, '/surecart/v1/checkouts' ) !== false ) {
			return $this->modify_checkout_response( $response );
		}

		// Handle line item endpoints (checkout is expanded within line item).
		if ( strpos( $route, '/surecart/v1/line_items' ) !== false ) {
			return $this->modify_line_item_response( $response );
		}

		return $response;
	}

	/**
	 * Modify checkout REST response.
	 *
	 * @param \WP_REST_Response $response The response object.
	 * @return \WP_REST_Response
	 */
	private function modify_checkout_response( $response ) {
		$data = $response->get_data();

		if ( empty( $data ) || ( ! is_array( $data ) && ! is_object( $data ) ) ) {
			return $response;
		}

		$data = (array) $data;

		if ( empty( $data['currency'] ) || 'eur' !== $data['currency'] ) {
			return $response;
		}

		$data = $this->append_bgn_pricing( $data );
		$response->set_data( $data );

		return $response;
	}

	/**
	 * Modify line item REST response to update expanded checkout.
	 *
	 * @param \WP_REST_Response $response The response object.
	 * @return \WP_REST_Response
	 */
	private function modify_line_item_response( $response ) {
		$data = $response->get_data();

		if ( empty( $data ) || ( ! is_array( $data ) && ! is_object( $data ) ) ) {
			return $response;
		}

		$data = (array) $data;

		// Check if already processed (avoid double processing).
		if ( ! empty( $data['scratch_display_amount'] ) && strpos( $data['scratch_display_amount'], 'лв.' ) !== false ) {
			return $response;
		}

		// Process the top-level line item itself if it's EUR.
		if ( ! empty( $data['currency'] ) && 'eur' === $data['currency'] ) {
			$data = $this->append_bgn_to_line_item( $data );
		}

		// Check if checkout is expanded and is EUR.
		if ( ! empty( $data['checkout'] ) && is_array( $data['checkout'] ) ) {
			$checkout = (array) $data['checkout'];

			if ( ! empty( $checkout['currency'] ) && 'eur' === $checkout['currency'] ) {
				// Append BGN pricing to the expanded checkout.
				// Don't skip any line items - process all of them.
				$data['checkout'] = $this->append_bgn_pricing( $checkout, null );
			}
		}

		$response->set_data( $data );

		return $response;
	}

	/**
	 * Append BGN pricing to checkout display amounts.
	 *
	 * @param array  $data The checkout data.
	 * @param string $skip_line_item_id Optional line item ID to skip (already processed at top level).
	 * @return array
	 */
	private function append_bgn_pricing( $data, $skip_line_item_id = null ) {
		// Append BGN total.
		if ( ! empty( $data['total_amount'] ) ) {
			$bgn_total                    = $data['total_amount'] * self::EUR_TO_BGN_RATE;
			$bgn_display                  = Currency::format( $bgn_total, 'bgn' );
			$data['total_display_amount'] = ( $data['total_display_amount'] ?? '' ) . ' (' . $bgn_display . ')';
		}

		// Append BGN subtotal.
		if ( ! empty( $data['subtotal_amount'] ) ) {
			$bgn_subtotal                    = $data['subtotal_amount'] * self::EUR_TO_BGN_RATE;
			$bgn_display                     = Currency::format( $bgn_subtotal, 'bgn' );
			$data['subtotal_display_amount'] = ( $data['subtotal_display_amount'] ?? '' ) . ' (' . $bgn_display . ')';
		}

		// Append BGN amount due (for submit button).
		if ( ! empty( $data['amount_due'] ) ) {
			$bgn_amount_due                    = $data['amount_due'] * self::EUR_TO_BGN_RATE;
			$bgn_display                       = Currency::format( $bgn_amount_due, 'bgn' );
			$data['amount_due_display_amount'] = ( $data['amount_due_display_amount'] ?? '' ) . ' (' . $bgn_display . ')';
		}

		// Append BGN pricing to line items.
		if ( ! empty( $data['line_items']['data'] ) && is_array( $data['line_items']['data'] ) ) {
			foreach ( $data['line_items']['data'] as &$line_item ) {
				// Skip if this is the same line item we already processed at the top level.
				if ( $skip_line_item_id && ! empty( $line_item['id'] ) && $line_item['id'] === $skip_line_item_id ) {
					continue;
				}
				$line_item = $this->append_bgn_to_line_item( $line_item );
			}
		}

		return $data;
	}

	/**
	 * Append BGN pricing to a single line item.
	 *
	 * @param array $line_item The line item data.
	 * @return array
	 */
	private function append_bgn_to_line_item( $line_item ) {
        if ( ! is_array( $line_item ) ) {
            $line_item = (array) $line_item;
        }

        // Append BGN to scratch display amount (only if not already appended).
        if ( ! empty( $line_item['scratch_amount'] ) && ! empty( $line_item['scratch_display_amount'] ) ) {
            // Check if BGN is already appended.
            if ( strpos( $line_item['scratch_display_amount'], 'лв.' ) === false ) {
                $bgn_scratch                          = $line_item['scratch_amount'] * self::EUR_TO_BGN_RATE;
                $bgn_display                          = Currency::format( $bgn_scratch, 'bgn' );
                $line_item['scratch_display_amount']  = $line_item['scratch_display_amount'] . ' (' . $bgn_display . ')';
            }
        }

        // Append BGN to subtotal display amount (only if not already appended).
        if ( ! empty( $line_item['subtotal_amount'] ) && ! empty( $line_item['subtotal_display_amount'] ) ) {
            // Check if BGN is already appended.
            if ( strpos( $line_item['subtotal_display_amount'], 'лв.' ) === false ) {
                $bgn_subtotal                         = $line_item['subtotal_amount'] * self::EUR_TO_BGN_RATE;
                $bgn_display                          = Currency::format( $bgn_subtotal, 'bgn' );
                $line_item['subtotal_display_amount'] = $line_item['subtotal_display_amount'] . ' (' . $bgn_display . ')';
            }
        }

        // Append BGN to ad_hoc display amount (for donations, only if not already appended).
        if ( ! empty( $line_item['ad_hoc_amount'] ) && ! empty( $line_item['ad_hoc_display_amount'] ) ) {
            // Check if BGN is already appended.
            if ( strpos( $line_item['ad_hoc_display_amount'], 'лв.' ) === false ) {
                $bgn_ad_hoc                         = $line_item['ad_hoc_amount'] * self::EUR_TO_BGN_RATE;
                $bgn_display                        = Currency::format( $bgn_ad_hoc, 'bgn' );
                $line_item['ad_hoc_display_amount'] = $line_item['ad_hoc_display_amount'] . ' (' . $bgn_display . ')';
            }
        }

        return $line_item;
    }
}
