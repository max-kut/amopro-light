<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 27.02.18
 * Time: 17:05
 */

namespace AmoPRO\Exceptions;


use AmoPRO\Logger;
use Throwable;

class BaseException extends \Exception
{
    public function __construct($message, Logger &$log = null)
    {
        if(!is_null($log)){
            $log->debug->error($message, [$this->getTraceAsString()]);
        }
        parent::__construct($message, 0, null);
    }
}