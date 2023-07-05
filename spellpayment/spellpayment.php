<?php

if (!defined('_PS_VERSION_')) {
    print('_PS_VERSION_ constant missing, quiting the Klix.app payments module');
    exit;
}

require_once(__DIR__ . '/lib/SpellPayment/SpellHelper.php');
require_once(__DIR__ . '/lib/SpellPayment/RefundHelper.php');

require_once(__DIR__ . '/lib/SpellPayment/Repositories/OrderIdToSpellUuid.php');

//use SpellPayment\Repositories\OrderIdToSpellUuid;

class SpellPayment extends PaymentModule
{
    public $name;
    public $tab;
    public $version;
    public $ps_versions_compliancy;
    public $author;
    public $controllers;
    public $need_instance;
    public $currencies;
    public $currencies_mode;
    public $bootstrap;
    public $display;

    public $displayName = 'Klix E-commerce gateway';
    public $description = 'Klix E-commerce gateway';
    public $confirmUninstall = 'Are you sure you want to delete Klix.app module?';

    public function __construct()
    {
        $this->name = 'spellpayment';
        $this->tab = 'payments_gateways';
        $this->version = '1.1.8';
        $this->ps_versions_compliancy = array('min' => '1.6.0.0', 'max' => _PS_VERSION_);
        $this->author = 'Klix.app';
        $this->controllers = array('validation');
        $this->need_instance = true;
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->bootstrap = true;
        $this->display = true;

        parent::__construct();

    }

    private function getDetectedCountry()
    {
        if (!$cart = $this->context->cart) {
            return null;
        }
        if (!$id_address_delivery = $cart->id_address_delivery) {
            return null;
        }
        $address_delivery = new Address($id_address_delivery);
        if (!$id_country = $address_delivery->id_country) {
            return null;
        }
        $address_delivery_country = new Country($id_country);
        $return = $address_delivery_country->iso_code ? $address_delivery_country->iso_code : null;
        return $return;
    }


    /** @throws Exception - if payment method was not configured properly- */
    private function collectCheckoutTplData($params)
    {
        list($configValues, $errors) = SpellPayment\SpellHelper::getConfigFieldsValues();

        $currency = Context::getContext()->currency->iso_code;
        $spell = SpellPayment\SpellHelper::getSpell($configValues);
        $payment_methods = $spell->paymentMethods(
            $currency,
            SpellPayment\SpellHelper::parseLanguage(Context::getContext()->language->iso_code)
        );
        $msgItem = $payment_methods['__all__'][0] ? $payment_methods['__all__'][0] : null;
        if ('authentication_failed' === ($msgItem['code'])) {
            $string = isset($msgItem['message']) ? $msgItem['message'] : '(no message)';
            $msg = 'Spell authentication_failed - ' .$string;
            throw new Exception($msg);
        }

        $payment_method_selection_enabled = $configValues['SPELLPAYMENT_METHOD_SELECTION_ENABLED'];
        $country_options = SpellPayment\SpellHelper::getCountryOptions($payment_methods);

        $payment_method_title = 'Select payment method';
        $payment_method_description = 'Choose payment method on next page';
        $action = $this->context->link->getModuleLink(
            $this->name,
            'spellpayment',
            array(),
            true
        );
        $title = $payment_method_selection_enabled ? $payment_method_title : $payment_method_description;
        return array(
            'title' => $title,
            'payment_method_selection_enabled' => $payment_method_selection_enabled,
            'payment_methods_api_data' => $payment_methods,
            'country_options' => $country_options,
            'by_method' => SpellPayment\SpellHelper::collectByMethod($payment_methods['by_country']),
            'action_url' => $action,
            '$params' => $params,
            'selected_country' => SpellPayment\SpellHelper::getPreselectedCountry($this->getDetectedCountry(), $country_options),
        );
    }

    private function translateCountryNames(&$payment_methods)
    {
        foreach ($payment_methods['country_names'] as $countryCode => $name) {
            $payment_methods['country_names'][$countryCode] = $name;
        }
    }
    /**
     * order state is some sort of unique identifier for our
     * module required to register payments during checkout
     */
    private function ensureOrderState()
    {
        $stateId = Configuration::get('SPELLPAYMENT_STATE_WAITING');
        if ($stateId === false) {
            $order_state = new OrderState();
        } else {
            $order_state = new OrderState($stateId);
        }

        $translations = array(
            'en' => 'Awaiting payment',
            'lt' => 'Laukiama apmokėjimo',
            'ru' => 'Ожидание оплаты',
            'ee' => 'Ootab makseid',
            'lv' => 'Maksājuma gaidīšana'
        );

        $order_state->name = array();

        $languages = Language::getLanguages();
        foreach ($languages as $language) {
            $order_state->name[$language['id_lang']] = $translations[$language['iso_code']];
        }

        $order_state->template = 'payment';
        $order_state->module_name = $this->name;
        $order_state->color       = "RoyalBlue";
        $order_state->unremovable = true;
        $order_state->hidden      = false;
        $order_state->logable     = false;
        $order_state->delivery    = false;
        $order_state->shipped     = false;
        $order_state->paid        = false;
        $order_state->deleted     = false;
        $order_state->invoice     = false;
        $order_state->send_email  = false;
        $order_state->save();

        Configuration::updateValue("SPELLPAYMENT_STATE_WAITING", $order_state->id);
    }

    /** called by Prestashop when you install the module */
    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        $this->ensureOrderState();
        SpellPayment\Repositories\OrderIdToSpellUuid::recreate();

        Configuration::updateValue('SPELLPAYMENT_METHOD_SELECTION_ENABLED', true);
        Configuration::updateValue('SPELLPAYMENT_ONE_CLICK_PAYMENT_ENABLED', true);

        return parent::install()
            && $this->registerHook('payment')
            && $this->registerHook('displayPaymentEU')
            && $this->registerHook('paymentOptions')
            && $this->registerHook('displayProductButtons')
            && $this->registerHook('displayNavFullWidth')
            && $this->registerHook('displayFooter')
            && $this->registerHook('displayShoppingCartFooter')
            && $this->registerHook('Header')
            && $this->registerHook('actionOrderSlipAdd')
            && $this->registerHook('paymentReturn');
    }

    public function hookDisplayNavFullWidth()
    {
        if (false == Configuration::get('SPELLPAYMENT_ONE_CLICK_PAYMENT_ENABLED', false)) {
            return;
        }
        if ("order" === $this->context->controller->php_self) {
            $url = $this->context->link->getModuleLink(
                $this->name,
                'pdpcheckout',
                array('cart_id' => $this->context->cart->id)
            );
            $one_click_button_enabled = Configuration::get('SPELLPAYMENT_ONE_CLICK_PAYMENT_ENABLED', false);
            $this->context->smarty->assign(array(
                'url' => $url,
                'this_path_bw' => $this->_path,
                'one_click_button_enabled' => $one_click_button_enabled
            ));
            return $this->display(__FILE__, 'paynow.tpl');
        }
    }

    public function hookDisplayShoppingCartFooter($params)
    {
        if (false == Configuration::get('SPELLPAYMENT_ONE_CLICK_PAYMENT_ENABLED', false)) {
            return;
        }
        $one_click_button_enabled = Configuration::get('SPELLPAYMENT_ONE_CLICK_PAYMENT_ENABLED', false);
        $url = $this->context->link->getModuleLink(
            $this->name,
            'pdpcheckout',
            ['cart_id' => $this->context->cart->id]
        );
        $this->context->smarty->assign([
            'url' => $url,
            'this_path_bw' => $this->_path,
            'one_click_button_enabled' => $one_click_button_enabled
        ]);
        return $this->display(__FILE__, 'paynow.tpl');
    }

    public function hookDisplayFooter($params)
    {
        return $this->display(__FILE__, 'footer.tpl');
    }

    public function hookDisplayProductButtons($params)
    {
        if (Configuration::get('SPELLPAYMENT_ACTIVE_MODE', false) == false) {
            return '';
        }

        $product_price = str_replace('€', '', $params['product']->price);
        $product_price = str_replace(',', '.', $params['product']->price);
        $product_price = floatval($product_price);
        $product_price = $product_price * 100;

        $brand_id = Configuration::get('SPELLPAYMENT_SHOP_ID');
        $language = SpellPayment\SpellHelper::parseLanguage(Context::getContext()->language->iso_code);
        $one_click_button_enabled = Configuration::get('SPELLPAYMENT_ONE_CLICK_PAYMENT_ENABLED', false);
        if (is_array($params['product'])) {
            $url = $this->context->link->getModuleLink(
                $this->name,
                'pdpcheckout',
                array('product_id' => $params['product']['id'])
            );
        } else {
            if (!isset($params['product']->id)) {
                return '';
            }
            $url = $this->context->link->getModuleLink(
                $this->name,
                'pdpcheckout',
                array('product_id' => $params['product']->id)
            );
        }
        $this->smarty->assign(array(
            'url' => $url,
            'product_price' => $product_price,
            'brand_id' => $brand_id,
            'this_path_bw' => $this->_path,
            'language' => $language,
            'id_product_attribute' => $params['product']->cache_default_attribute,
            'one_click_button_enabled' => $one_click_button_enabled
        ));
        return $this->display(__FILE__, 'paynow.tpl');
    }

    public function uninstall()
    {
        SpellPayment\Repositories\OrderIdToSpellUuid::drop();

        return $this->unregisterHook('paymentOptions')
            && $this->unregisterHook('payment')
            && $this->unregisterHook('displayProductButtons')
            && $this->unregisterHook('displayNavFullWidth')
            && $this->unregisterHook('displayFooter')
            && $this->unregisterHook('displayShoppingCartFooter')
            && $this->unregisterHook('paymentReturn')
            && $this->unregisterHook('Header')
            && $this->unregisterHook('actionOrderSlipAdd')
            && parent::uninstall();
    }

    /** Load the configuration form */
    public function getContent()
    {
        $status = null;

        // If values have been submitted in the form, process.
        if (((bool)Tools::isSubmit('submitSpellpaymentModule')) == true) {
            list($configValues, $errors) = SpellPayment\SpellHelper::getConfigFieldsValues();
            if (!$errors) {
                foreach ($configValues as $name => $value) {
                    Configuration::updateValue($name, trim($value));
                }
                $status = 'SAVED_SUCCESSFULLY';
            } else {
                $status = 'CHANGES_NOT_SAVED';
            }
        }

        return $this->renderForm($status);
    }

    /** called by Prestashop on admin configuration page */
    public function getConfigForm()
    {
        return SpellPayment\SpellHelper::getConfigForm();
    }

    public function renderForm($status = null)
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = 'en';
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitSpellpaymentModule';

        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        list($configValues, $errors) = SpellPayment\SpellHelper::getConfigFieldsValues();
        $helper->tpl_vars = array(
            'fields_value' => $configValues,
            'languages' => array(
                array(
                    'id_lang' => 'en',
                    'iso_code' => 'en',
                    'name' => 'English',
                    'is_default' => true,
                ),
            ),
            'id_language' => 'en',
        );
        $errorsHeaderHtml = '';
        $statusHtml = '';
        if ($status === 'SAVED_SUCCESSFULLY') {
            $statusHtml = '<div style="color: #00e400">Changes Saved Successfully</div>';
        } else if ($status === 'CHANGES_NOT_SAVED') {
            $statusHtml = '<div style="color: red">Changes Not Saved</div>';
            $errorsHeaderHtml = '<div style="color: red">' . implode('<br/>', array_map('htmlspecialchars', $errors)) . '</div>';
        }

        $configForm = SpellPayment\SpellHelper::getConfigForm();

        foreach ($configForm['form']['input'] as $key => $value) {
            $configForm['form']['input'][$key]['label'] = $value['label'];
        }

        return $statusHtml . $errorsHeaderHtml . $helper->generateForm(array($configForm));
    }

    /** @return \PrestaShop\PrestaShop\Core\Payment\PaymentOption[] */
    public function hookDisplayPaymentEU($params)
    {
        if (!$this->active) {
            return array();
        }

        if (false == Configuration::get('SPELLPAYMENT_ACTIVE_MODE', false)) {
            return array();
        }

        $paymentOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $paymentOption->setModuleName($this->name)
            ->setCallToActionText("spell")
            ->setAction($action);

        return array($paymentOption);
    }

    public function hookPayment($params)
    {
        if (!$this->active) {
            return array();
        }

        if (false == Configuration::get('SPELLPAYMENT_ACTIVE_MODE', false)) {
            return array();
        }


		$this->smarty->assign($this->collectCheckoutTplData($params));
		return $this->display(__FILE__, 'payment.tpl');
    }

    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name) {
            $this->context->controller->addJS($this->_path . 'views/js/back.js');
        }
    }

    public function hookDisplayPaymentReturn($params)
    {
        if ($this->active == false) {
            return false;
        }

        return 'Thanks for using Klix.app payments';
    }
    public function hookHeader($params)
    {
        if (!Configuration::get('SPELLPAYMENT_ACTIVE_MODE')) {
            return '';
        }
        return '<script type="module" src="https://klix.blob.core.windows.net/public/pay-later-widget/build/klix-pay-later-widget.esm.js"></script>
        <script nomodule="" src="https://klix.blob.core.windows.net/public/pay-later-widget/build/klix-pay-later-widget.js"></script>';
    }

    /**
     * Process the refund
     *
     * @param array $params
     *
     * @return bool
     */
    public function hookActionOrderSlipAdd(array $params)
    {
        $spellRefund = new SpellPayment\RefundHelper();
        $order = $params['order'];
        $productList = $params['productList'];
        $payment_id = $spellRefund->isRefundAllow($order, $productList);
        if (!$payment_id) {
            return false;
        }
        return $spellRefund->processRefund($order, $productList, $payment_id);
    }

}
