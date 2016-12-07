(function() {

    /***********************************************************/
    /* Handle Proceed to Payment
    /***********************************************************/
    jQuery(function() {
        jQuery(document).on('proceedToPayment', function(event, ShoppingCart) {
            if (ShoppingCart.gateway != 'paypal_express') {
                return;
            }

            var order = {
                products: storejs.get('grav-shoppingcart-basket-data'),
                data: storejs.get('grav-shoppingcart-checkout-form-data'),
                shipping: storejs.get('grav-shoppingcart-shipping-method'),
                payment: 'paypal',
                token: storejs.get('grav-shoppingcart-order-token').token,
                taxes: ShoppingCart.taxesApplied.toString(),
                amount: ShoppingCart.totalOrderPrice.toString(),
                gateway: ShoppingCart.gateway
            };

            jQuery.ajax({
                url: ShoppingCart.settings.baseURL + ShoppingCart.settings.urls.save_order_url + '/task:preparePayment',
                data: order,
                type: 'POST'
            })
            .success(function(redirectUrl) {
                ShoppingCart.clearCart();
                window.location = redirectUrl;
            })
            .error(function() {
                alert('Payment not successful. Please contact us.');
            });
        });

    });

})();
