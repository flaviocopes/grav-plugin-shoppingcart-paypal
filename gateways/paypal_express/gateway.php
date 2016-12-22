<?php
namespace Grav\Plugin\ShoppingCart;

use RocketTheme\Toolbox\Event\Event;
use Omnipay\Omnipay;

/**
 * Class GatewayPayPalExpress
 * @package Grav\Plugin\ShoppingCart
 */
class GatewayPayPalExpress extends Gateway
{
    protected $name = 'paypal_express';

    /**
     * @param $gateway
     *
     * @return \Omnipay\Common\GatewayInterface|void
     */
    protected function setupGateway($gateway)
    {
        if (!$this->isCurrentGateway($gateway)) { return false; }

        $pluginConfig = $this->grav['config']->get('plugins.shoppingcart');
        $gatewayConfig = $pluginConfig['payment']['methods']['paypal_express'];

        $username = $gatewayConfig['username'];
        $password = $gatewayConfig['password'];
        $signature = $gatewayConfig['signature'];
        $test_mode = $pluginConfig['test_mode'];

        $gateway = Omnipay::create('PayPal_Express');
        $gateway->setUsername($username);
        $gateway->setPassword($password);
        $gateway->setSignature($signature);
        $gateway->setTestMode($test_mode);

        return $gateway;
    }

    /**
     * @param Event $event
     */
    public function onShoppingCartPreparePayment(Event $event)
    {
        $gatewayName = $event['gateway'];
        if (!$this->isCurrentGateway($gatewayName)) { return; }

        $pluginConfig = $this->grav['config']->get('plugins.shoppingcart');
        $currency = $pluginConfig['general']['currency'];

        $gateway = $this->setupGateway($gatewayName);

        $baseUrl = $this->grav['base_url_absolute'];
        $returnUrl = $baseUrl . '/shoppingcart/paypalexpress/success?';
        $cancelUrl = $baseUrl . '/shoppingcart/paypalexpress/cancelled?';

        $order = $this->getOrderFromEvent($event);

        $this->grav['session']->order = $order->toArray();

        $items = new \Omnipay\PayPal\PayPalItemBag();

        foreach ($order->products as $product) {
            $items->add(array(
                'name' => $product['product']['title'],
                'quantity' => $product['quantity'],
                'price' => $product['product']['price'],
            ));
        }

        $params = [
            'cancelUrl' => $cancelUrl,
            'returnUrl' => $returnUrl,
            'amount' =>  $order->amount,
            'shippingAmount' => $order->shipping['cost'],
            'currency' => $currency
        ];

        if ($pluginConfig['general']['product_taxes'] === 'excluded') {
            $params['taxAmount'] = $order->taxes;
        }

        $response = $gateway->purchase($params)->setItems($items)->send();

        echo $response->getRedirectUrl();
        exit();
    }

    /**
     * @param Event $event
     *
     * @event onShoppingCartPay signal pay for an order
     */
    public function onShoppingCartGotBackFromGateway(Event $event)
    {
        $gatewayName = $event['gateway'];
        if (!$this->isCurrentGateway($gatewayName)) { return; }

        $transactionReference = $_GET['token'];
        $payer_id = $_GET['PayerID'];
        $order = $this->grav['session']->order;

        $this->grav->fireEvent('onShoppingCartPay', new Event([ 'gateway' => $this->name,
                                                                'payer_id' => $payer_id,
                                                                'transactionReference' => $transactionReference,
                                                                'order' => $order]));
    }

    /**
     * Handle paying via this gateway
     *
     * @param Event $event
     *
     * @event onShoppingCartSaveOrder signal save the order
     * @event onShoppingCartRedirectToOrderPageUrl signal redirect to the order page
     *
     * @return mixed|void
     */
    public function onShoppingCartPay(Event $event)
    {
        $gatewayName = $event['gateway'];
        if (!$this->isCurrentGateway($gatewayName)) { return; }

        $order = $this->getOrderFromEvent($event);
        $gateway = $this->setupGateway($gatewayName);

        $pluginConfig = $this->grav['config']->get('plugins.shoppingcart');
        $currency = $pluginConfig['general']['currency'];

        $items = new \Omnipay\PayPal\PayPalItemBag();

        foreach ($order->products as $product) {
            $items->add(array(
                'name' => $product['product']['title'],
                'quantity' => $product['quantity'],
                'price' => $product['product']['price'],
            ));
        }

        $response = $gateway->completePurchase([
            'payer_id' => $event['payer_id'],
            'transactionReference' => $event['transactionReference'],
            'amount' => $order->amount,
            'shippingAmount' => $order->shipping['cost'],
            'currency' => $currency,
        ])->setItems($items)->send();

        if ($response->isSuccessful()) {
            // mark order as complete
            $this->grav->fireEvent('onShoppingCartSaveOrder', new Event(['gateway' => $this->name, 'order' => $order]));
            $this->grav->fireEvent('onShoppingCartRedirectToOrderPageUrl', new Event(['gateway' => $this->name, 'order' => $order]));
        } elseif ($response->isRedirect()) {
            $response->redirect();
        } else {
            // display error to customer
            throw new \RuntimeException("Payment not successful: " . $response->getMessage());
        }
    }
}


