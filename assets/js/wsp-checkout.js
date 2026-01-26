jQuery(function ($) {

    function togglePickupFields() {

        let isPickup = false;

        $('input[name^="shipping_method"]').each(function () {
            if (
                $(this).is(':checked') &&
                $(this).val().indexOf('wsp_store_pickup') !== -1
            ) {
                isPickup = true;
            }
        });

        if (isPickup) {
            $('#wsp-pickup-fields').slideDown();
        } else {
            $('#wsp-pickup-fields').slideUp();
        }
    }

    // Initial check
    togglePickupFields();

    // Re-run after checkout updates
    $(document.body).on('updated_checkout', function () {
        togglePickupFields();
    });

});
