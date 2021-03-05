<?php

namespace SimpleMapper\Exception;

use Throwable;

class DbalNotDefinedException extends \RuntimeException
{
    public function __construct(string $message = "DBAL was not defined", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

}