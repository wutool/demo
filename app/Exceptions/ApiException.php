<?php

namespace App\Exceptions;

use App\Util\Util;
use Exception;

class ApiException extends Exception
{
    public function __construct($errno, $args = array())
    {
        $args = is_array($args) ? $args : array($args);
        $error = empty($args) ? Util::getError($errno) : vsprintf(Util::getError($errno), $args);

        parent::__construct($error, $errno, null);
        $this->message = $error;
    }
}