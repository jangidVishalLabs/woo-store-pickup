jQuery(function ($) {

    /* Check if Store Pickup shipping is selected */
    function isStorePickupSelected() {
        // Try multiple selectors for different WooCommerce versions
        let method = $('input[name^="shipping_method"]:checked').val();
        
        // Fallback: try without :checked if nothing is selected yet
        if (!method) {
            method = $('input[name^="shipping_method"][checked]').val();
        }
        
        // Fallback: check hidden input (some themes use this)
        if (!method) {
            method = $('input[name="shipping_method[0]"]').val();
        }
        
        return method && method.indexOf('wsp_store_pickup') !== -1;
    }

    /* Show / Hide pickup fields */
    function togglePickupFields() {
        const $fields = $('#wsp-pickup-fields');
        
        if (!$fields.length) {
            return;
        }

        if (isStorePickupSelected()) {
            $fields.slideDown();
        } else {
            $fields.slideUp();
        }
    }

    let pickupAjaxRunning = false;

    /* Load pickup stores via AJAX */
    function loadPickupStores() {
        const $storeSelect = $('#wsp_store_id');
        
        if (!$storeSelect.length) {
            return;
        }

        if (pickupAjaxRunning) {
            return;
        }

        pickupAjaxRunning = true;

        $.ajax({
            url: wc_checkout_params.ajax_url,
            type: 'POST',
            data: {
                action: 'wsp_get_pickup_stores'
            },
            beforeSend: function () {
                $storeSelect.prop('disabled', true).html('<option value="">Loading stores...</option>');
            },
            success: function (response) {
                if (response.success) {
                    $storeSelect.html(response.data);

                     if (response.data.indexOf('No stores available') !== -1) {
        $storeSelect.prop('disabled', true);
    } else {
        $storeSelect.prop('disabled', false);
    }
                } else {
                    $storeSelect.html('<option value="">No stores available</option>');
                }
            },
            error: function(xhr, status, error) {
                $storeSelect.html('<option value="">Error loading stores</option>');
            },
            complete: function () {
                pickupAjaxRunning = false;
                $storeSelect.prop('disabled', false);
            }
        });
    }

    /* Initialize - wait for checkout to be fully loaded */
    function initPickupFields() {
        // Wait a bit for WooCommerce to render shipping methods
        setTimeout(function() {
            togglePickupFields();
            
            if (isStorePickupSelected()) {
                loadPickupStores();
            }
        }, 500);
    }

    /* Run after WooCommerce checkout AJAX refresh */
    $(document.body).on('updated_checkout', function () {
        // Small delay to ensure shipping methods are rendered
        setTimeout(function() {
            togglePickupFields();

            if (isStorePickupSelected()) {
                loadPickupStores();
            } else {
                // Do nothing
            }
        }, 100);
    });

    /* Shipping method change */
    $(document.body).on('change', 'input[name^="shipping_method"]', function () {
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
        'select[name="billing_state"], select[name="shipping_state"], select[name="billing_country"], select[name="shipping_country"], input[name="billing_postcode"], input[name="shipping_postcode"]',
        function () {
            $(document.body).trigger('update_checkout');
        }
    );

    /* Initial state */
    initPickupFields();

});