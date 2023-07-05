<?php

namespace SpellPayment;

use PrestaShop\Module\PrestashopCheckout\Exception\PsCheckoutException;
use PrestaShop\Module\PrestashopCheckout\Updater\CustomerUpdater;
use Customer;
use Configuration;

class PDPHelper extends \ModuleFrontController
{
    public static function insertNewAddress($address, $context)
    {
        $address = (object) $address;
        $address->full_name = $address->full_name ? $address->full_name : "Anonymous";
        $AddressObject = new \Address();
        $AddressObject->id_customer = $context->customer->id;
        $AddressObject->firstname = pSQL($address->full_name) ? pSQL($address->full_name) : "Anonymous";
        $AddressObject->lastname = pSQL($address->full_name) ? pSQL($address->full_name) : "Anonymous";
        $AddressObject->address1 = pSQL($address->shipping_street_address) ? pSQL($address->shipping_street_address) : "Anonymous";
        $AddressObject->company = pSQL($address->brand_name) ?pSQL($address->brand_name): "Anonymous";
        $AddressObject->vat_number = pSQL($address->tax_number) ? pSQL($address->tax_number): "Anonymous";
        $AddressObject->company_registration_number = pSQL($address->registration_number) ? pSQL($address->registration_number) : "Anonymous";
        $AddressObject->postcode = pSQL($address->shipping_zip_code) ? pSQL($address->shipping_zip_code) : "Anonymous";
        $AddressObject->city = pSQL($address->city) ? pSQL($address->city) : "Anonymous";
        $AddressObject->alias = $address->legal_name ? pSQL($address->legal_name) : pSQL($address->full_name);
        $is_available = false;
        foreach ($AddressObject as $obj) {
            if ($obj != "Anonymous") {
                $is_available = true;
            }
        }
        $AddressObject->id_country = $address->shipping_country ? \Country::getIdByName(null, (int)$address->shipping_country) : Configuration::get('PS_COUNTRY_DEFAULT');

        if ($is_available) {
            $query = new \DbQuery();
            $query->select('id_address');
            $query->from('address');
            $query->where('id_customer = ' . (int) $context->customer->id);
            $query->where('id_country = ' . $AddressObject->id_country);
            $query->where('deleted = 0');
            $address = \Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query, false);
            if ($address) {
                return $address;
            }
        }
        $AddressObject->add();
        $context->cookie->__set('custom_address_id', $AddressObject->id);
        return $AddressObject->id;
    }

    /**
     * Handle creation and customer login
     *
     * @param string $email
     * @param string $firstName
     * @param string $lastName
     *
     * @throws PrestaShopException
     *
     * @return Customer|false
     */
    public function createAndLoginCustomer(
        $email,
        $firstName = "dummy",
        $lastName = "dummy"
    ) {
        /** @var int $idCustomerExists */
        $idCustomerExists = \Customer::customerExists($email, true);
        if (0 === $idCustomerExists) {
            // @todo Extract factory in a Service.
            $customer = $this->createCustomer(
                $email,
                $firstName,
                $lastName
            );
        } else {
            $customer = new \Customer($idCustomerExists);
        }

        if (method_exists($this->context, 'updateCustomer')) {
            $this->context->updateCustomer($customer);
        } else {
            CustomerUpdater::updateContextCustomer($this->context, $customer);
        }
        return $customer;
    }

    /**
     * Create a customer
     *
     * @param string $email
     * @param string $firstName
     * @param string $lastName
     *
     * @return Customer
     *
     * @throws PsCheckoutException
     */
    private function createCustomer($email, $firstName, $lastName)
    {
        $customer = new Customer();
        $customer->email = $email;
        $customer->firstname = $firstName;
        $customer->lastname = $lastName;
        if (class_exists('PrestaShop\PrestaShop\Core\Crypto\Hashing')) {
            $crypto = new PrestaShop\PrestaShop\Core\Crypto\Hashing();
            $customer->passwd = $crypto->hash(
                time() . _COOKIE_KEY_,
                _COOKIE_KEY_
            );
        } else {
            $customer->passwd = md5(time() . _COOKIE_KEY_);
        }
        $customer->is_guest = true;
        $customer->id_default_group = (int) Configuration::get('PS_GUEST_GROUP');
        try {
            $customer->save();
        } catch (Exception $exception) {
            throw "Cannot save Customer";
        }
        return $customer;
    }
}
