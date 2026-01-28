import { store, getContext, getElement } from "@wordpress/interactivity";

store("surecart/bulgaria-dual-pricing", {
  state: {
    /**
     * Get the selected display amount based on the selected variant or price.
     */
    get selectedDisplayAmount() {
      const { prices, selectedPrice } = getContext("surecart/product-page");
      const { state: productPageState } = store("surecart/product-page");
      const { bgnPrices, bgnVariants } = getContext(
        "surecart/bulgaria-dual-pricing"
      );
      const selectedBgnPrice = bgnPrices.find(
        (price) => price.id === selectedPrice.id
      );
      const selectedBgnVariant = bgnVariants.find(
        (variant) => variant.id === productPageState.selectedVariant.id
      );

      const bgnAmount = prices?.length > 1
        ? selectedBgnPrice?.bgn_display_amount
        : selectedBgnVariant?.bgn_display_amount || selectedBgnPrice?.bgn_display_amount;

      return bgnAmount ? ` (${bgnAmount})` : "";
    },
  },
});
