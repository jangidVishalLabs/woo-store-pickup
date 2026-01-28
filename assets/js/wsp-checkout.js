jQuery(function ($) {

    function isStorePickupSelected() {
        let selected = false;

        $('input[name^="shipping_method"]:checked').each(function () {
            if ($(this).val().indexOf('wsp_store_pickup') !== -1) {
                selected = true;
            }
        });

        return selected;
    }

    function togglePickupFields() {
        const $fields = $('#wsp-pickup-fields');

        if (!$fields.length) {
            return;
        }

        if (isStorePickupSelected()) {
            $fields.stop(true, true).slideDown();
        } else {
            $fields.stop(true, true).slideUp();
        }
    }

    function triggerCheckoutUpdate() {
        $(document.body).trigger('update_checkout');
    }

    /* ---------------------------------------------------------
     * Initial load
     * --------------------------------------------------------- */
    togglePickupFields();

    /* ---------------------------------------------------------
     * Shipping method change
     * --------------------------------------------------------- */
    $(document.body).on('change', 'input[name^="shipping_method"]', function () {
        togglePickupFields();
    });

    /* ---------------------------------------------------------
     * Address change → force zone recalculation
     * --------------------------------------------------------- */
    $(document.body).on(
        'change',
        'select[name="billing_state"], select[name="shipping_state"], ' +
        'input[name="billing_postcode"], input[name="shipping_postcode"], ' +
        'select[name="billing_country"], select[name="shipping_country"]',
        function () {
            triggerCheckoutUpdate();
        }
    );

    /* ---------------------------------------------------------
     * Checkout refreshed (zone changed)
     * --------------------------------------------------------- */
    $(document.body).on('updated_checkout', function () {

        togglePickupFields();

        // Reset store dropdown to avoid invalid store selection
        const $storeSelect = $('#wsp_store_id');
        if ($storeSelect.length) {
            $storeSelect.val('');
        }
    });

});
