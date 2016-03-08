<?php
namespace Grav\Plugin;

use RocketTheme\Toolbox\Event\Event;
use Omnipay\Omnipay;

/**
 * Class ShoppingCartGatewayPayPalExpress
 * @package Grav\Plugin
 */
class ShoppingCartGatewayPayPalExpress extends ShoppingCartGateway
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

        $params = [
            'cancelUrl'=> $cancelUrl,
            'returnUrl'=> $returnUrl,
            'amount' =>  $order->amount,
            'currency' => $currency,
            //'description' => 'Test Purchase for 12.99'
        ];

        $response = $gateway->purchase($params)->send();

        echo $response->getRedirectUrl();
        exit();
    }

    /**
     * @param Event $event
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
     * @return mixed|void
     */
    public function onShoppingCartPay(Event $event)
    {
        $gatewayName = $event['gateway'];
        if (!$this->isCurrentGateway($gatewayName)) { return; }

        $order = $this->getOrderFromEvent($event);
        $gateway = $this->setupGateway($gatewayName);

        $response = $gateway->completePurchase([
            'payer_id' => $event['payer_id'],
            'transactionReference' => $event['transactionReference'],
            'amount' => $order->amount,
            'currency' => $order->currency,
        ])->send();

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


