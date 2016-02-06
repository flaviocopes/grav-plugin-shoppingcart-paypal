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
                address: storejs.get('grav-shoppingcart-person-address'),
                shipping: storejs.get('grav-shoppingcart-shipping-method'),
                payment: storejs.get('grav-shoppingcart-payment-method'),
                token: storejs.get('grav-shoppingcart-order-token').token,
                amount: ShoppingCart.totalOrderPrice.toString(),
                gateway: ShoppingCart.gateway
            };

            jQuery.ajax({
                url: ShoppingCart.settings.baseURL + ShoppingCart.settings.urls.saveOrderURL + '?task=preparePayment',
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
