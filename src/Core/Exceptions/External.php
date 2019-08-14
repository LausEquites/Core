<?php

namespace Core\Exceptions;

use Throwable;

class External extends \Exception
{
    public function __construct($message, $code, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}