<?php
defined('_JEXEC') or die('Restricted access');

if (!class_exists('vmPSPlugin'))
{
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

class plgVmPaymentOklink extends vmPSPlugin
{

    /**
     * @param $subject
     * @param $config
     */
    function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->_loggable   = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $varsToPush        = $this->getVarsToPush();
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }
    /**
     * Create the table for this plugin if it does not yet exist.
     *
     * @return
     */
    public function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Payment Oklink Table');
    }

    /**
     * Fields to create the payment table
     *
     * @return array
     */
    function getTableSQLFields()
    {
        $SQLfields = array(
            'id'                          => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id'         => 'int(1) UNSIGNED',
            'order_number'                => 'char(64)',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
            'payment_name'                => 'varchar(5000)',
            'payment_order_total'         => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
            'payment_currency'            => 'char(3)'
        );

        return $SQLfields;
    }

    /**
     * Display stored payment data for an order
     *
     * @param $virtuemart_order_id
     * @param $virtuemart_payment_id
     *
     * @return
     */
    function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_payment_id)
    {
        if (!$this->selectedThisByMethodId($virtuemart_payment_id))
        {
            return NULL; // Another method was selected, do nothing
        }

        if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id)))
        {
            return NULL;
        }

        $html = '<table class="adminlist">' . "\n";
        $html .= $this->getHtmlHeaderBE();
        $html .= $this->getHtmlRowBE('OKLINK_PAYMENT_NAME', $paymentTable->payment_name);
        $html .= $this->getHtmlRowBE('OKLINK_PAYMENT_TOTAL_CURRENCY', $paymentTable->payment_order_total . ' ' . $paymentTable->payment_currency);
        $html .= '</table>' . "\n";

        return $html;
    }

    /**
     * @param VirtueMartCart $cart
     * @param                $method
     * @param array          $cart_prices
     *
     * @return
     */
    // function getCosts(VirtueMartCart $cart, $method, $cart_prices)
    // {
    //     if (preg_match('/%$/', $method->cost_percent_total))
    //     {
    //         $cost_percent_total = substr($method->cost_percent_total, 0, -1);
    //     }
    //     else
    //     {
    //         $cost_percent_total = $method->cost_percent_total;
    //     }

    //     return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
    // }

    /**
     * Check if the payment conditions are fulfilled for this payment method
     *
     * @param $cart
     * @param $method
     * @param $cart_prices
     *
     * @return boolean
     */
    protected function checkConditions($cart, $method, $cart_prices)
    {
        // $this->convert($method);
        $this->convert_condition_amount($method);
        //      $params = new JParameter($payment->payment_params);
        $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

        $amount = $this->getCartAmount($cart_prices);

        $countries = array();
        if (!empty($method->countries))
        {
            if (!is_array($method->countries))
            {
                $countries[0] = $method->countries;
            }
            else
            {
                $countries = $method->countries;
            }
        }

        // probably did not gave his BT:ST address
        if (!is_array($address))
        {
            $address                          = array();
            $address['virtuemart_country_id'] = 0;
        }

        if (!isset($address['virtuemart_country_id']))
        {
            $address['virtuemart_country_id'] = 0;
        }
        if (count($countries) == 0 || in_array($address['virtuemart_country_id'], $countries))
        {
            return true;
        }

        return false;
    }    

    /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the standard method to create the tables
     *
     * @param $jplugin_id
     *
     * @return
     */
    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
    {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }   

    /*
     * plgVmonSelectedCalculatePricePayment
     * Calculate the price (value, tax_id) of the selected method
     * It is called by the calculator
     * This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
     *
     * @param VirtueMartCart $cart
     * @param array          $cart_prices
     * @param                $cart_prices_name
     *
     * @return
     */

    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
    {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    /**
     * @param $virtuemart_paymentmethod_id
     * @param $paymentCurrencyId
     *
     * @return
     */
    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId)
    {
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id)))
        {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element))
        {
            return false;
        }
        $this->getPaymentCurrency($method);

        $paymentCurrencyId = $method->payment_currency;
        return;
    }

    /**
     * plgVmOnCheckAutomaticSelectedPayment
     * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
     * The plugin must check first if it is the correct type
     *
     * @param VirtueMartCart $cart
     * @param array          $cart_prices
     * @param                $paymentCounter
     *
     * @return
     */
    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter)
    {
        return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
    }

    /**
     * This method is fired when showing the order details in the frontend.
     * It displays the method-specific data.
     *
     * @param $virtuemart_order_id
     * @param $virtuamart_paymentmethod_id
     * @param $payment_name
     */
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    /**
     * This method is fired when showing when priting an Order
     * It displays the the payment method-specific data.
     *
     * @param integer $_virtuemart_order_id The order ID
     * @param integer $method_id  method used for this order
     *
     * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
     */
    function plgVmonShowOrderPrintPayment($order_number, $method_id)
    {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    /**
     * @param $name
     * @param $id
     * @param $data
     *
     * @return
     */
    function plgVmDeclarePluginParamsPayment($name, $id, &$data)
    {
        return $this->declarePluginParams('payment', $name, $id, $data);
    }

    /**
     * @param $name
     * @param $id
     * @param $table
     *
     * @return
     */
    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

    /**
     * This event is fired by Offline Payment. It can be used to validate the payment data as entered by the user.
     *
     * @return
     */
    function plgVmOnPaymentNotification ()
    {
        if (!class_exists ('VirtueMartModelOrders'))
        {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }
        $oklink_data            = file_get_contents("php://input");
        $oklink_data            = json_decode($oklink_data);

        if (!isset($oklink_data['id']))
        {
            error_log('no invoice in data');
            return NULL;
        }

        $order_number = $oklink_data['custom'];
        if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber ($order_number)))
        {
            error_log('order not found '.$order_number);
            return NULL;
        }

        $modelOrder = VmModel::getModel ('orders');
        $order      = $modelOrder->getOrder($virtuemart_order_id);
        if (!$order)
        {
            bplog('order could not be loaded '.$virtuemart_order_id);
            return NULL;
        }

        $method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id);
        require_once(dirname(__FILE__).'/lib/Oklink.php');
        $client = Oklink::withApiKey($method->merchant_apikey, $method->merchant_apisecret);
        if (!$client.checkCallback())
        {
            bplog('api key invalid for order '.$order_number);
            return NULL;
        } 

        if ( $oklink_data['status'] != 'completed')
        {
            return NULL; // not the status we're looking for
        }

        $order['order_status'] = 'C'; // move to admin method option?
        $modelOrder->updateStatusForOneOrder ($virtuemart_order_id, $order, TRUE);                          
    }

    /**
     * @param $html
     *
     * @return bool|null|string
     */
    function plgVmOnPaymentResponseReceived (&$html)
    {
        if (!class_exists ('VirtueMartCart'))
        {
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
        }
        if (!class_exists ('shopFunctionsF'))
        {
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
        }
        if (!class_exists ('VirtueMartModelOrders'))
        {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }

        // the payment itself should send the parameter needed.
        $virtuemart_paymentmethod_id = JRequest::getInt ('pm', 0);
        $order_number                = JRequest::getString ('on', 0);
        $vendorId                    = 0;

        if (!($method = $this->getVmPluginMethod ($virtuemart_paymentmethod_id)))
        {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement ($method->payment_element))
        {
            return NULL;
        }

        if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber ($order_number)))
        {
            return NULL;
        }
        if (!($paymentTable = $this->getDataByOrderId ($virtuemart_order_id)))
        {
            // JError::raiseWarning(500, $db->getErrorMsg());
            return '';
        }
        $payment_name = $this->renderPluginName ($method);
        $html         = $this->_getPaymentResponseHtml ($paymentTable, $payment_name);

        //We delete the old stuff
        // get the correct cart / session
        $cart = VirtueMartCart::getCart ();
        $cart->emptyCart ();
        return TRUE;
    } 

    /**
     * This shows the plugin for choosing in the payment list of the checkout process.
     *
     * @param VirtueMartCart $cart
     * @param integer        $selected
     * @param                $htmlIn
     *
     * @return
     */
    function plgVmDisplayListFEPayment (VirtueMartCart $cart, $selected = 0, &$htmlIn)
    {
        $session = JFactory::getSession ();
        $errors  = $session->get ('errorMessages', 0, 'vm');

        if($errors != "")
        {
            $errors = unserialize($errors);
            $session->set ('errorMessages', "", 'vm');
        }
        else
        {
            $errors = array();
        }

        return $this->displayListFE ($cart, $selected, $htmlIn);
    }

    /**
     * @param $cart
     * @param $order
     *
     * @return
     */
    function plgVmConfirmedOrder($cart, $order)
    {

        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id)))
        {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element))
        {
            return false;
        }

        $lang     = JFactory::getLanguage();
        $filename = 'com_virtuemart';
        $lang->load($filename, JPATH_ADMINISTRATOR);
        $vendorId = 0;
        $html     = "";

        if (!class_exists('VirtueMartModelOrders'))
        {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }
        $this->getPaymentCurrency($method, true);

        // END printing out HTML Form code (Payment Extra Info)
        $q  = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
        $db = JFactory::getDBO();
        $db->setQuery($q);
        $currency_code_3 = $db->loadResult();


        $app = JFactory::getApplication();

        if( $currency_code_3 == 'USD' || $currency_code_3 == 'CNY' || $currency_code_3 == 'BTC'  ){
            $params = array(
                            'price'          => (float)$order['details']['BT']->order_total,
                            'price_currency' => $currency_code_3,
                            'name'           => 'Order #'.$order['details']['BT']->order_number,
                            'callback_url'   => (JROUTE::_ (JURI::root () . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component')),
                            'success_url'    => (JROUTE::_ (JURI::root () . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . '&Itemid=' . JRequest::getInt ('Itemid'))),
                );

            require_once(dirname(__FILE__).'/lib/Oklink.php');
            $client = Oklink::withApiKey($method->merchant_apikey, $method->merchant_apisecret);
            $result = $client->buttonsButton($params);
            $button_id = $result->button->id;
            header('Location:'.OklinkBase::WEB_BASE.'merchant/mPayOrderStemp1.do?buttonid='.$button_id);
        }else{
            $app->redirect(JRoute::_('index.php?option=com_virtuemart&view=cart', false), "only support USD or CNY or BTC");
        }
        exit;
    }         
}

defined('_JEXEC') or die('Restricted access');

/*
 * This class is used by VirtueMart Payment  Plugins
 * which uses JParameter
 * So It should be an extension of JElement
 * Those plugins cannot be configured througth the Plugin Manager anyway.
 */
if (!class_exists( 'VmConfig' ))
{
    require(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart'.DS.'helpers'.DS.'config.php');
}
if (!class_exists('ShopFunctions'))
{
    require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'shopfunctions.php');
}

// Check to ensure this file is within the rest of the framework
defined('JPATH_BASE') or die();