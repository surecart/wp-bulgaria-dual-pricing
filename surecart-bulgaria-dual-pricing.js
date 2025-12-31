import { store, getContext, getElement } from "@wordpress/interactivity";

store("surecart/bulgaria-dual-pricing", {
  state: {
    /**
     * Get the selected display amount based on the selected variant or price.
     */
    get selectedDisplayAmount() {
      const { prices, selectedPrice } = getContext("surecart/product-page");
      const { state: productPageState } = store("surecart/product-page");
      const { euroPrices, euroVariants } = getContext(
        "surecart/bulgaria-dual-pricing"
      );
      const selectedEuroPrice = euroPrices.find(
        (price) => price.id === selectedPrice.id
      );
      const selectedEuroVariant = euroVariants.find(
        (variant) => variant.id === productPageState.selectedVariant.id
      );

      if (prices?.length > 1) {
        return selectedEuroPrice?.euro_display_amount || "";
      }

      return (
        selectedEuroVariant?.euro_display_amount ||
        selectedEuroPrice?.euro_display_amount ||
        ""
      );
    },
  },
});
