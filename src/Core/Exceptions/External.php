<?php

namespace Core\Exceptions;

use Throwable;

class External extends \Exception
{
    private $errors = [];
    public function __construct($message, $code, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function setErrors($errors)
    {
        $this->errors = $errors;
    }

    public function getErrors()
    {
        return $this->errors;
    }
}
