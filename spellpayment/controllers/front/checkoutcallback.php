<?php

/**
 * Callback method listed in the class.
 *
 * @package Spellpayment
 */

require_once __DIR__ . '/../../lib/SpellPayment/SpellHelper.php';
require_once __DIR__ . '/../../lib/SpellPayment/PDPHelper.php';

use SpellPayment\SpellHelper;
use SpellPayment\PDPHelper;

require_once __DIR__ . '/../../lib/SpellPayment/Repositories/OrderIdToSpellUuid.php';

use SpellPayment\Repositories\OrderIdToSpellUuid;

/**
 * Controller for handle checkout callback
 */
class SpellpaymentCheckoutcallbackModuleFrontController extends \ModuleFrontController
{
    /**
     * Function for restoring cart value
     *
     * @param integer $cart_id Cart id to restore.
     * */
    private function restoreCart($cart_id)
    {
        $old_cart    = new \Cart($cart_id);
        $duplication = $old_cart->duplicate();
        if (!$duplication || !\Validate::isLoadedObject($duplication['cart'])) {
            return 'Sorry. We cannot renew your order.';
        } elseif (!$duplication['success']) {
            return 'Some items are no longer available, and we are unable to renew your order.';
        } else {
            $this->context->cookie->id_cart = $duplication['cart']->id;
            $context                        = $this->context;
            $context->cart                  = $duplication['cart'];
            \CartRule::autoAddToCart($context);
            $this->context->cookie->write();
            return null;
        }
    }

    /**
     * Function for create success page url
     *
     * @param Order $order Object of order class.
     */
    private function makeSuccessPageUrl($order)
    {
        $cart            = $this->context->cart;
        $customer        = new \Customer((int) ($cart->id_customer));

        if($customer->is_guest){
            $redirectLink = 'index.php?controller=guest-tracking';
            $redirectLink .= '&id_order='.$order->reference.'&email='.urlencode($customer->email);
            $this->context->customer->mylogout();
            return $redirectLink;
        }
        $redirect_params = array(
            'id_cart'   => (int) $order->id_cart,
            'id_module' => (int) $this->module->id,
            'id_order'  => $order->id,
            'key'       => $order->secure_key,
        );
        return $redirectLink = "index.php?controller=order-detail&id_order=".$order->id;
    }

    /**
     * Function for get total of cart value
     */
    private function getAmount()
    {
        return $this->context->cart->getOrderTotal(true, \Cart::BOTH);
    }

    /**
     * Function for processing one click payment
     */
    private function processOneClickPayment()
    {
        if (!isset($_REQUEST['cart_id'])) {
            return false;
        }
        $cart_id = $_REQUEST['cart_id'];
        $relation = OrderIdToSpellUuid::getByOrderId($cart_id);
        if (!$relation) {
            return false;
        }

        $spell_payment_uuid = $relation['spell_payment_uuid'];
        if (!$spell_payment_uuid) {
            return false;
        }
        list($configValues, $errors) = SpellHelper::getConfigFieldsValues();
        $spell = SpellHelper::getSpell($configValues);
        try {
            $purchases = $spell->purchases($spell_payment_uuid);
        } catch (Exception $exc) {
            return false;
        }
        if ($purchases['status'] !== "paid") {
            return false;
        }

        $client                                   = $purchases['client'];
        $full_name                                = $client['full_name'] ?$full_name                                = $client['full_name'] : "dummy dummy";
        $full_name                                = explode(" ", $full_name);
        $last_name                                = isset($full_name[1]) ? $full_name[1] : "dummy";
        $customer                                 = (new PDPHelper())->createAndLoginCustomer($client['email'], $full_name[0], $last_name);
        $this->context->cart->id_customer         = $customer->id;
        $id_address                               = PDPHelper::insertNewAddress($purchases['client'], $this->context);
        $this->context->cart->id_address_delivery = $id_address;
        $this->context->cart->id_address_invoice  = $id_address;
        $this->context->cart->update();
        $currency = new \Currency((int)($this->context->cart->id_currency));
        $this->module->validateOrder(
            $this->context->cart->id,
            \Configuration::get('SPELLPAYMENT_STATE_WAITING'),
            $this->getAmount(),
            'Klix.app payments',
            null,
            array(),
            (int)$currency->id,
            false,
            $this->context->customer->secure_key
        );
        $order = new \Order($this->module->currentOrder);
        $order->id_address_delivery = (int)$this->context->cart->id_address_delivery;
        $order->id_address_invoice = (int)$this->context->cart->id_address_invoice;
        $order->update();
        OrderIdToSpellUuid::update($order->id, $cart_id);
        return $order;
    }

    private function processPaymentResult()
    {
        if (isset($_REQUEST['cart_id']) && !isset($_REQUEST['order_id'])) {
            $order = $this->processOneClickPayment();
            $order_id = $order ? $order->id : null;
            if (!$order) {
                \Tools::redirect('cart?action=show');
                return null;
            }
        } else {
            $order_id = isset($_REQUEST['order_id']) ? $_REQUEST['order_id'] : null;
            if (!$order_id) {
                return array('status' => 400, 'message' => 'Parameter `order_id` is mandatory');
            }
        }

        if (!$relation = OrderIdToSpellUuid::getByOrderId($order_id)) {
            return array('status' => 404, 'message' => 'No known Klix.app payments found for order #' . $order_id);
        }

        $spell_payment_uuid = $relation['spell_payment_uuid'];
        $order = new \Order((int)$order_id);
        list($configValues, $errors) = SpellHelper::getConfigFieldsValues();
        $spell = SpellHelper::getSpell($configValues);

        try {
            $purchases = $spell->purchases($spell_payment_uuid);
        } catch (Exception $exc) {
            $order->setCurrentState(\Configuration::get('PS_OS_ERROR'));
            return array('status' => 502, 'message' => 'Failed to retrieve purchases from Klix.app payments - ' . $exc->getMessage());
        }

        $status = isset($purchases['status']) ? $purchases['status'] : null;
        $message = isset($purchases['transaction_data']['attempts']) ? end($purchases['transaction_data']['attempts'])['error']['message'] : '';

        if ($status !== 'paid') {
            $is_cancel = isset($_REQUEST['is_cancel']) ? $_REQUEST['is_cancel'] : false;
            if ($is_cancel) {
                $order->setCurrentState(\Configuration::get('PS_OS_CANCELED'));
            }
            return array(
                'status' => 302,
                'redirect_url' => $this->context->link->getPageLink(
                    'order',
                    true,
                    null,
                    array('id_order' => $order->id, 'secure_key' => $order->secure_key)
                ),
            );
        } else {
            if ($order->getCurrentState() != \Configuration::get('PS_OS_PAYMENT')) {
                // sends email email, so we want to ensure it's called just once on either redirect or API callback
                $order->setCurrentState(\Configuration::get('PS_OS_PAYMENT'));
            }
            $redirect_url = $this->makeSuccessPageUrl($order);
            \Tools::redirect($redirect_url);
            return array('status' => 302, 'redirect_url' => $redirect_url);
        }
    }

    public function initContent()
    {
        \Db::getInstance()->execute(
            "SELECT GET_LOCK('spell_payment', 15);"
        );
        $processed = $this->processPaymentResult();
        $status = $processed['status'];
        $message = isset($processed['message']) ? $processed['message'] : null;
        $restore_cart_id = isset($_REQUEST['restore_cart_id']) ? $_REQUEST['restore_cart_id'] : null;
        if ($status === 302 && !$restore_cart_id) {
            $redirect_url = $processed['redirect_url'];
            $is_api = isset($_REQUEST['is_module_callback']) ? $_REQUEST['is_module_callback'] : false;
            if ($is_api) {
                header('HTTP/1.1 200 OK');
                exit;
            } else {
                header('Location: ' . $redirect_url);
                exit;
            }
        } else {
            if ($restore_cart_id) {
                $restore_error = $this->restoreCart($restore_cart_id);
                if ($restore_error) {
                    \Tools::displayError($message . '. ' . $restore_error);
                } else {
                    \Tools::redirect($this->context->link->getPageLink(
                        'order',
                        null,
                        null,
                        ['error' => $message]
                    ));
                }
            } else {
                header('HTTP/1.1 ' . $status);
                print($message);
                exit;
            }
        }

        \Db::getInstance()->execute(
            "SELECT RELEASE_LOCK('spell_payment');"
        );

        exit;
    }
}
