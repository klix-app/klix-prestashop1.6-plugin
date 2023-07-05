<?php
/**
 * @since 1.5.0
 */
class SpellpaymentPaymentModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $display_column_left = false;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        $cart = $this->context->cart;
        $this->context->smarty->assign($this->collectCheckoutTplData());

        $this->setTemplate('payment_execution.tpl');
    }

    private function collectCheckoutTplData()
    {
        $configValuesErrors = SpellPayment\SpellHelper::getConfigFieldsValues();
        $configValues = $configValuesErrors[0];
        $errors = $configValuesErrors[1];

        $currency = Context::getContext()->currency->iso_code;
        $spell = SpellPayment\SpellHelper::getSpell($configValues);
        $payment_methods = $spell->paymentMethods(
            $currency,
            SpellPayment\SpellHelper::parseLanguage(Context::getContext()->language->iso_code)
        );
        $msgItem = isset($payment_methods['__all__'][0]) ? $payment_methods['__all__'][0] : null;
        if (!isset($msgItem['code']) || $msgItem['code'] === 'authentication_failed') {
            $msg = 'Spell authentication_failed - ' . (isset($msgItem['message']) ? $msgItem['message'] : '(no message)');
            throw new Exception($msg);
        }

        $payment_method_selection_enabled = $configValues['SPELLPAYMENT_METHOD_SELECTION_ENABLED'];
        $country_options = SpellPayment\SpellHelper::getCountryOptions($payment_methods);

        $payment_method_title = 'Select payment method';
        $payment_method_description = 'Choose payment method on next page';

        return [
            'title' => $payment_method_selection_enabled ? $payment_method_title : $payment_method_description,
            'payment_method_selection_enabled' => $payment_method_selection_enabled,
            'payment_methods_api_data' => $payment_methods,
            'country_options' => $country_options,
            'by_method' => SpellPayment\SpellHelper::collectByMethod($payment_methods['by_country']),
            'selected_country' => SpellPayment\SpellHelper::getPreselectedCountry($this->getDetectedCountry(), $country_options),
        ];
    }

    private function getDetectedCountry()
    {
        $cart = $this->context->cart;
        if (!$cart) {
            return null;
        }
        $id_address_delivery = $cart->id_address_delivery;
        if (!$id_address_delivery) {
            return null;
        }
        $address_delivery = new Address($id_address_delivery);
        $id_country = $address_delivery->id_country;
        if (!$id_country) {
            return null;
        }
        $address_delivery_country = new Country($id_country);
        return $address_delivery_country->iso_code ? $address_delivery_country->iso_code : null;
    }
}
