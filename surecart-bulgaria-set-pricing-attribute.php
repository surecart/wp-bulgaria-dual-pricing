<?php
/**
 * Set the pricing attribute for the product.
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
		add_action( 'surecart/checkout/attributes_set', array( $this, 'set_checkout_total_attribute' ) );
		add_action( 'render_block', array( $this, 'update_selected_price' ), 10, 2 );
		add_action( 'render_block', array( $this, 'update_list_price' ), 10, 2 );
		add_action( 'render_block', array( $this, 'update_checkout_total' ), 10, 2 );
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

	/*
	* Set the pricing attribute for the price.
	*
	* @param \SureCart\Models\Price $price The price model.
	* @return void
	*/
	public function set_pricing_attribute( $price ) {
        if ( 'eur' !== $price->currency ) {
            return $price->display_amount;
        }
        $bgn_amount = $price->amount * 1.95583; // Convert EUR to BGN using fixed rate: 1 EUR = 1.95583 BGN.
		$bgn_display_amount = empty( $bgn_amount ) ? '' : Currency::format( $bgn_amount, 'bgn' );
		$price->setAttribute( 'bgn_display_amount', $bgn_display_amount );
	}

	/**
	 * Set the checkout total attribute for BGN display.
	 *
	 * @param \SureCart\Models\Checkout $checkout The checkout model.
	 * @return void
	 */
	public function set_checkout_total_attribute( $checkout ) {
		if ( 'eur' !== $checkout->currency ) {
			return;
		}
		$bgn_total = $checkout->total_amount * 1.95583; // Convert EUR to BGN using fixed rate: 1 EUR = 1.95583 BGN.
		$bgn_total_display_amount = empty( $bgn_total ) ? '' : Currency::format( $bgn_total, 'bgn' );
		$checkout->setAttribute( 'bgn_total_display_amount', $bgn_total_display_amount );
	}

	/**
	 * Update the checkout total block to show dual pricing.
	 *
	 * @param string $block_content The block content.
	 * @param array  $block The block.
	 * @return string The block content.
	 */
	public function update_checkout_total( $block_content, $block ) {
		if ( 'surecart/checkout-total' !== $block['blockName'] ) {
			return $block_content;
		}

		$checkout = sc_get_checkout();
		if ( ! $checkout || 'eur' !== $checkout->currency ) {
			return $block_content;
		}

		$additional_content = ' ' . $checkout->bgn_total_display_amount;

		// Use WP_HTML_Tag_Processor to inject content inside the wrapper.
		$processor = new WP_HTML_Tag_Processor( $block_content );

		// Find the element containing total_display_amount and append BGN amount.
		// Look for the span with the total amount class.
		if ( $processor->next_tag( 'span' ) ) {
			// Navigate to find the closing tag of the wrapper.
			$depth = 0;
			while ( $processor->next_token() ) {
				if ( '#tag' === $processor->get_token_type() && 'SPAN' === $processor->get_tag() ) {
					if ( $processor->is_tag_closer() ) {
						if ( 0 === $depth ) {
							// We found the closing tag of our wrapper.
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
}
