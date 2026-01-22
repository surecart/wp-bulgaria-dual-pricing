/**
 * Bulgaria Dual Pricing for SureCart Checkout
 *
 * This script observes checkout price elements and appends BGN pricing.
 * It handles the dynamic re-rendering of SureCart stencil web components.
 */
(function() {
    'use strict';

    // Get the conversion rate from PHP (single source of truth).
    const EUR_TO_BGN = window.scBulgariaDualPricing?.eurToBgnRate || 1.95583;
    const BGN_CLASS = 'sc-bgn-price';

    /**
     * Format amount in BGN currency.
     * @param {number} amountInCents - Amount in cents
     * @returns {string} Formatted BGN amount (e.g., "BGN 19.56")
     */
    function formatBGN(amountInCents) {
        const amount = amountInCents / 100;
        return 'BGN ' + amount.toFixed(2);
    }

    /**
     * Extract EUR amount in cents from a price string.
     * @param {string} priceText - Price text like "€10" or "€10.00"
     * @returns {number|null} Amount in cents or null if not found
     */
    function extractEurCents(priceText) {
        if (!priceText) return null;

        // Remove any existing BGN text
        const cleanText = priceText.replace(/BGN[\s\d.,]+/gi, '').trim();

        // Match EUR amounts: €10, €10.00, 10€, 10,00€, EUR 10, etc.
        const match = cleanText.match(/€\s*([\d.,]+)|(\d+[.,]?\d*)\s*€|(EUR|eur)\s*([\d.,]+)/);
        if (!match) return null;

        let amountStr = match[1] || match[2] || match[4];
        if (!amountStr) return null;

        // Normalize decimal separator
        amountStr = amountStr.replace(',', '.');
        const amount = parseFloat(amountStr);

        if (isNaN(amount)) return null;

        return Math.round(amount * 100);
    }

    /**
     * Add BGN price span after an element's text content.
     * @param {Element} element - The element containing the EUR price
     */
    function appendBGNToElement(element) {
        if (!element || element.querySelector('.' + BGN_CLASS)) return;

        const text = element.textContent;
        const eurCents = extractEurCents(text);

        if (!eurCents) return;

        const bgnCents = Math.round(eurCents * EUR_TO_BGN);
        const bgnFormatted = formatBGN(bgnCents);

        const bgnSpan = document.createElement('span');
        bgnSpan.className = BGN_CLASS;
        bgnSpan.textContent = ' (' + bgnFormatted + ')';
        bgnSpan.style.cssText = 'white-space: nowrap;';

        element.appendChild(bgnSpan);
    }

    /**
     * Process sc-total elements (used in totals and submit button).
     */
    function processScTotalElements() {
        document.querySelectorAll('sc-total').forEach(el => {
            // sc-total has shadow: false, so content is directly accessible
            if (!el.querySelector('.' + BGN_CLASS)) {
                const text = el.textContent;
                const eurCents = extractEurCents(text);

                if (eurCents) {
                    const bgnCents = Math.round(eurCents * EUR_TO_BGN);
                    const bgnFormatted = formatBGN(bgnCents);

                    // Check if BGN already present
                    if (!text.includes('BGN')) {
                        const bgnSpan = document.createElement('span');
                        bgnSpan.className = BGN_CLASS;
                        bgnSpan.textContent = ' (' + bgnFormatted + ')';
                        el.appendChild(bgnSpan);
                    }
                }
            }
        });
    }

    /**
     * Process line item prices inside shadow DOM.
     */
    function processLineItemPrices() {
        // sc-line-items has shadow DOM
        document.querySelectorAll('sc-line-items').forEach(lineItems => {
            const shadow = lineItems.shadowRoot;
            if (!shadow) return;

            // Find sc-product-line-item elements
            shadow.querySelectorAll('sc-product-line-item').forEach(item => {
                const itemShadow = item.shadowRoot;
                if (!itemShadow) return;

                // Find price elements
                const priceSlot = itemShadow.querySelector('[part="price__amount"], .line-item__price-amount, slot[name="price"]');
                if (priceSlot) {
                    const priceContainer = priceSlot.closest('.price') || priceSlot.parentElement;
                    if (priceContainer && !priceContainer.querySelector('.' + BGN_CLASS)) {
                        appendBGNToElement(priceContainer);
                    }
                }
            });
        });
    }

    /**
     * Process line item total elements inside shadow DOM.
     */
    function processLineItemTotals() {
        document.querySelectorAll('sc-line-item-total').forEach(totalEl => {
            const shadow = totalEl.shadowRoot;
            if (!shadow) return;

            // Find sc-line-item elements inside
            shadow.querySelectorAll('sc-line-item').forEach(lineItem => {
                const itemShadow = lineItem.shadowRoot;
                if (!itemShadow) return;

                // Find price slot content
                const priceSlot = itemShadow.querySelector('slot[name="price"]');
                if (priceSlot) {
                    const assigned = priceSlot.assignedNodes();
                    assigned.forEach(node => {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            // Look for sc-total inside
                            const scTotal = node.querySelector ? node.querySelector('sc-total') : null;
                            if (scTotal && !scTotal.querySelector('.' + BGN_CLASS)) {
                                const text = scTotal.textContent;
                                const eurCents = extractEurCents(text);
                                if (eurCents && !text.includes('BGN')) {
                                    const bgnCents = Math.round(eurCents * EUR_TO_BGN);
                                    const bgnFormatted = formatBGN(bgnCents);
                                    const bgnSpan = document.createElement('span');
                                    bgnSpan.className = BGN_CLASS;
                                    bgnSpan.textContent = ' (' + bgnFormatted + ')';
                                    scTotal.appendChild(bgnSpan);
                                }
                            }

                            // Also check for direct price text
                            if (!node.querySelector('.' + BGN_CLASS) && !node.querySelector('sc-total')) {
                                const text = node.textContent;
                                if (text && text.includes('€') && !text.includes('BGN')) {
                                    appendBGNToElement(node);
                                }
                            }
                        }
                    });
                }
            });
        });
    }

    /**
     * Process order summary prices.
     */
    function processOrderSummary() {
        document.querySelectorAll('sc-order-summary').forEach(summary => {
            const shadow = summary.shadowRoot;
            if (!shadow) return;

            // Find all price-related elements
            shadow.querySelectorAll('[slot="price"], .price, sc-format-number').forEach(el => {
                if (!el.querySelector('.' + BGN_CLASS)) {
                    const text = el.textContent;
                    if (text && text.includes('€') && !text.includes('BGN')) {
                        appendBGNToElement(el);
                    }
                }
            });
        });
    }

    /**
     * Main function to process all checkout prices.
     */
    function processAllPrices() {
        processScTotalElements();
        processLineItemPrices();
        processLineItemTotals();
        processOrderSummary();
    }

    /**
     * Set up mutation observer to watch for DOM changes.
     */
    function setupObserver() {
        const checkout = document.querySelector('sc-checkout');
        if (!checkout) return;

        const observer = new MutationObserver((mutations) => {
            // Debounce the processing
            clearTimeout(window.bgnPriceTimeout);
            window.bgnPriceTimeout = setTimeout(processAllPrices, 100);
        });

        observer.observe(checkout, {
            childList: true,
            subtree: true,
            characterData: true
        });

        // Also observe shadow roots when they become available
        const observeShadowRoots = () => {
            document.querySelectorAll('sc-line-items, sc-line-item-total, sc-order-summary').forEach(el => {
                if (el.shadowRoot && !el._bgnObserved) {
                    el._bgnObserved = true;
                    observer.observe(el.shadowRoot, {
                        childList: true,
                        subtree: true,
                        characterData: true
                    });
                }
            });
        };

        // Initial shadow root observation
        setTimeout(observeShadowRoots, 500);
        setTimeout(observeShadowRoots, 1000);
        setTimeout(observeShadowRoots, 2000);
    }

    /**
     * Initialize the dual pricing system.
     */
    function init() {
        // Wait for checkout to be ready
        const checkReady = setInterval(() => {
            const checkout = document.querySelector('sc-checkout');
            if (checkout) {
                clearInterval(checkReady);

                // Initial processing with delays to handle async rendering
                setTimeout(processAllPrices, 500);
                setTimeout(processAllPrices, 1000);
                setTimeout(processAllPrices, 1500);
                setTimeout(processAllPrices, 2000);
                setTimeout(processAllPrices, 3000);

                // Set up observer for ongoing changes
                setupObserver();

                // Also process on various events
                document.addEventListener('click', () => {
                    setTimeout(processAllPrices, 300);
                });

                // Listen for custom SureCart events if available
                checkout.addEventListener('scUpdateCheckout', () => {
                    setTimeout(processAllPrices, 300);
                });
            }
        }, 100);

        // Timeout after 10 seconds
        setTimeout(() => clearInterval(checkReady), 10000);
    }

    // Start when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
