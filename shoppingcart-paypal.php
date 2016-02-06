<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Uri;
use RocketTheme\Toolbox\Event\Event;

class ShoppingcartPaypalPlugin extends Plugin
{
    protected $plugin_name = 'shoppingcart-paypal';

    protected $gateway;

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0]
        ];
    }

    /**
     */
    public function onTwigSiteVariables()
    {
        $this->grav['assets']->addJs('plugin://' . $this->plugin_name . '/gateways/paypal_express/script.js');
    }

    /**
     * Enable search only if url matches to the configuration.
     */
    public function onPluginsInitialized()
    {
        require_once __DIR__ . '/vendor/autoload.php';

        $this->config->set('plugins.shoppingcart', array_replace_recursive($this->config->get('plugins.shoppingcart'), $this->config->get('plugins.shoppingcart-paypal')));

        if (!$this->isAdmin()) {
            // Site
            $this->enable([
                'onTwigSiteVariables'          => ['onTwigSiteVariables', 0],
                'onShoppingCartPay'            => ['onShoppingCartPay', 0],
                'onShoppingCartPreparePayment' => ['onShoppingCartPreparePayment', 0],
                'onShoppingCartGateways'       => ['onShoppingCartGateways', 0]
            ]);

            /** @var Uri $uri */
            $uri = $this->grav['uri'];

            $paypalSuccessURL = '/shoppingcart/paypalexpress/success';

            //Handle PayPal Express Return
            if (strpos($uri->path(), $paypalSuccessURL) !== false) {
                $this->enable([
                    'onShoppingCartGotBackFromGateway' => ['onShoppingCartGotBackFromGateway', 0]
                ]);
                $this->grav->fireEvent('onShoppingCartGotBackFromGateway', new Event(['gateway' => 'paypal_express']));
            }

        }
    }

    public function onShoppingCartGateways($event)
    {
        require_once __DIR__ . '/gateways/paypal_express/gateway.php';

        $event->gateways['paypal'] = new ShoppingCartGatewayPayPalExpress();
    }

    /**
     *
     */
    protected function requireGateway()
    {
        $path = realpath(__DIR__ . '/../shoppingcart/classes/gateway.php');
        if (!file_exists($path)) {
            $path = realpath(__DIR__ . '/../grav-plugin-shoppingcart/classes/gateway.php');
        }
        require_once($path);
    }

    /**
     *
     */
    public function getGateway()
    {
        if (!$this->gateway) {
            $this->requireGateway();
            require_once __DIR__ . '/gateways/paypal_express/gateway.php';
            $this->gateway = new ShoppingCartGatewayPayPalExpress();
        }

        return $this->gateway;
    }

    /**
     * @param $event
     */
    public function onShoppingCartGotBackFromGateway($event)
    {
        $this->getGateway()->onShoppingCartGotBackFromGateway($event);
    }

    /**
     * @param $event
     */
    public function onShoppingCartPreparePayment($event)
    {
        $this->getGateway()->onShoppingCartPreparePayment($event);
    }

    /**
     * @param $event
     */
    public function onShoppingCartPay($event)
    {
        $this->getGateway()->onShoppingCartPay($event);
    }
}