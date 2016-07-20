<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Uri;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class ShoppingcartPaypalPlugin
 * @package Grav\Plugin
 */
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
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
            'onPagesInitialized' => ['onPagesInitialized', 0]
        ];
    }

    /**
     */
    public function onTwigSiteVariables()
    {
        $this->grav['assets']->addJs('plugin://' . $this->plugin_name . '/gateways/paypal_express/script.js');
    }

    /**
     */
    public function mergeShoppingCartPluginConfig()
    {
        $config = $this->config->get('plugins.' . $this->plugin_name);
        unset($config['enabled']);
        $this->config->set('plugins.shoppingcart', array_replace_recursive($this->config->get('plugins.shoppingcart'), $config));
    }

    /**
     * Enable search only if url matches to the configuration.
     *
     * @event onShoppingCartGotBackFromGateway signal I got back from the Gateway
     */
    public function onPluginsInitialized()
    {
        require_once __DIR__ . '/vendor/autoload.php';

        if (!$this->isAdmin()) {
            $this->mergeShoppingCartPluginConfig();

            //OpenSSL >= 1.0.1 Required
            if (OPENSSL_VERSION_NUMBER < 0x1000100f) {
                throw new \RuntimeException("PayPal Plugin Error. Your OpenSSL Version is too old. PayPal Sandbox needs at least OpenSSL 1.0.1 because of recent changes on their side. Please update your OpenSSL version, or ask your hosting provider to update it.");
            }

            // Site
            $this->enable([
                'onTwigSiteVariables'          => ['onTwigSiteVariables', 0],
                'onShoppingCartPay'            => ['onShoppingCartPay', 0],
                'onShoppingCartPreparePayment' => ['onShoppingCartPreparePayment', 0],
            ]);
        }
    }

    public function onPagesInitialized()
    {
        if (!$this->isAdmin()) {

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
            $this->gateway = new ShoppingCart\GatewayPayPalExpress();
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
