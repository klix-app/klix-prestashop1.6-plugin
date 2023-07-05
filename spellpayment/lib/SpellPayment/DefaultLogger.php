<?php

namespace SpellPayment;

class DefaultLogger
{
    private $log;

    /** @param \Psr\Log\LoggerInterface $log */
    public function __construct($log = null)
    {
        $this->log = $log;
    }

    /** @param string $msg */
    public function log($msg)
    {
        if($this->log){
            $this->log->info($msg);
        }
        \PrestaShopLogger::addLog(
            $msg,
            3,
            null,
            'Packages'
        );
    }
}
