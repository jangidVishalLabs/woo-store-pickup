jQuery(function ($) {

    function isStorePickupSelected() {
        return $('input[name^="shipping_method"]:checked')
            .val()
            ?.includes('wsp_store_pickup');
    }

    function togglePickupFields() {
        const $fields = $('#wsp-pickup-fields');
        if (!$fields.length) return;

        isStorePickupSelected()
            ? $fields.slideDown()
            : $fields.slideUp();
    }

    let lastShippingMethods = '';

    function triggerCheckoutUpdate() {
        $(document.body).trigger('update_checkout');
    }

    /* Address change → AJAX zone recalculation */
    $(document.body).on(
    'change',
    'select[name="billing_state"], select[name="shipping_state"], select[name="billing_country"], select[name="shipping_country"]',
    function () {
        $(document.body).trigger('update_checkout');
    }
);


    /* Shipping method change */
    $(document.body).on(
        'change',
        'input[name^="shipping_method"]',
        togglePickupFields
    );
    $(document.body).on('change', 'input[name^="shipping_method"]', function () {
    $('#wsp_store_id').prop('disabled', !isStorePickupSelected());
});


    /* After AJAX completes */
$(document.body).on('updated_checkout', function () {

    togglePickupFields();

    const $storeSelect = $('#wsp_store_id');

    if (!$storeSelect.length || !isStorePickupSelected()) {
        return;
    }

    $.ajax({
        url: wc_checkout_params.ajax_url,
        type: 'POST',
        data: {
            action: 'wsp_get_pickup_stores'
        },
        beforeSend() {
            $storeSelect.prop('disabled', true);
        },
        success(response) {
            if (response.success) {
                $storeSelect.html(response.data);
            }
        },
        complete() {
            $storeSelect.prop('disabled', false);
        }
    });
});


    /* Initial */
    togglePickupFields();
});
