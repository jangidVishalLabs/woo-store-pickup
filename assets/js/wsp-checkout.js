jQuery(function ($) {

    /* Check if Store Pickup shipping is selected */
    function isStorePickupSelected() {
        const method = $('input[name^="shipping_method"]:checked').val();
        console.log('WSP JS: Checking shipping method:', method);
        return method && method.indexOf('wsp_store_pickup') !== -1;
    }

    /* Show / Hide pickup fields */
    function togglePickupFields() {
        const $fields = $('#wsp-pickup-fields');
        console.log('WSP JS: Toggle fields, exists:', $fields.length > 0);
        
        if (!$fields.length) return;

        if (isStorePickupSelected()) {
            console.log('WSP JS: Showing pickup fields');
            $fields.slideDown();
        } else {
            console.log('WSP JS: Hiding pickup fields');
            $fields.slideUp();
        }
    }

    let pickupAjaxRunning = false;

    /* Load pickup stores via AJAX */
    function loadPickupStores() {
        console.log('WSP JS: loadPickupStores called');
        
        const $storeSelect = $('#wsp_store_id');
        
        if (!$storeSelect.length) {
            console.log('WSP JS: Store select element not found!');
            return;
        }

        if (pickupAjaxRunning) {
            console.log('WSP JS: AJAX already running, skipping');
            return;
        }

        console.log('WSP JS: Starting AJAX request');
        pickupAjaxRunning = true;

        $.ajax({
            url: wc_checkout_params.ajax_url,
            type: 'POST',
            data: {
                action: 'wsp_get_pickup_stores'
            },
            beforeSend: function () {
                console.log('WSP JS: AJAX beforeSend - disabling select');
                $storeSelect.prop('disabled', true);
            },
            success: function (response) {
                console.log('WSP JS: AJAX success', response);
                if (response.success) {
                    $storeSelect.html(response.data);
                    console.log('WSP JS: Updated select options');
                } else {
                    console.log('WSP JS: Response not successful');
                }
            },
            error: function(xhr, status, error) {
                console.error('WSP JS: AJAX error', status, error);
            },
            complete: function () {
                console.log('WSP JS: AJAX complete');
                pickupAjaxRunning = false;
                $storeSelect.prop('disabled', false);
            }
        });
    }

    /* Run after WooCommerce checkout AJAX refresh */
    $(document.body).on('updated_checkout', function () {
        console.log('WSP JS: updated_checkout event fired');
        
        togglePickupFields();

        if (!isStorePickupSelected()) {
            console.log('WSP JS: Store pickup not selected, skipping AJAX');
            return;
        }

        loadPickupStores();
    });

    /* Shipping method change */
    $(document.body).on('change', 'input[name^="shipping_method"]', function () {
        console.log('WSP JS: Shipping method changed');
        togglePickupFields();
        
        const isPickup = isStorePickupSelected();
        $('#wsp_store_id').prop('disabled', !isPickup);
        
        if (isPickup) {
            loadPickupStores();
        }
    });

    /* Address change → force checkout refresh */
    $(document.body).on(
        'change',
        'select[name="billing_state"], select[name="shipping_state"], select[name="billing_country"], select[name="shipping_country"]',
        function () {
            console.log('WSP JS: Address field changed, triggering update_checkout');
            $(document.body).trigger('update_checkout');
        }
    );

    /* Initial state */
    console.log('WSP JS: Script loaded, setting initial state');
    togglePickupFields();
    
    // Load stores on initial page load if pickup is selected
    if (isStorePickupSelected()) {
        console.log('WSP JS: Initial load with pickup selected');
        loadPickupStores();
    }

});