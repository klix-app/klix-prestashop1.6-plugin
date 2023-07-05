<?php

namespace SpellPayment\Repositories;

class OrderIdToSpellUuid
{
    private static $TABLE;

    private static function normRow($row)
    {
        return array(
            'order_id' => $row['order_id'],
            'spell_payment_uuid' => $row['spell_payment_uuid'],
        );
    }

    public static function recreate()
    {
        self::$TABLE = _DB_PREFIX_ . 'spellpayment_ps_order_id_to_spell_id';
        \Db::getInstance()->execute('DROP TABLE IF EXISTS ' . self::$TABLE);
        \Db::getInstance()->execute('CREATE TABLE ' . self::$TABLE . ' (
            order_id INT NOT NULL,
            spell_payment_uuid CHAR(36) NOT NULL,
            UNIQUE KEY order_id (order_id)
        )');
    }

    public static function drop()
    {
        self::$TABLE = _DB_PREFIX_ . 'spellpayment_ps_order_id_to_spell_id';
        \Db::getInstance()->execute('DROP TABLE IF EXISTS ' . self::$TABLE);
    }

    public static function addNew($row)
    {
        self::$TABLE =  'spellpayment_ps_order_id_to_spell_id';
        \Db::getInstance()->insert(self::$TABLE, self::normRow($row), false, false, \Db::REPLACE, true);
    }

    public static function update($order_id, $cart_id)
    {
        self::$TABLE = 'spellpayment_ps_order_id_to_spell_id';
        \Db::getInstance()->update(self::$TABLE, array('order_id' => $order_id), 'order_id = ' . (int)$cart_id);
    }

    /** @return array = self::normRow() */
    public static function getByOrderId($order_id)
    {
        self::$TABLE = _DB_PREFIX_ . 'spellpayment_ps_order_id_to_spell_id';
        $sql = 'SELECT * FROM ' . self::$TABLE . ' WHERE order_id = ' . ((int)$order_id);
        $result = \Db::getInstance()->executeS($sql);
        return isset($result[0]) ? $result[0] : null;

    }
}
