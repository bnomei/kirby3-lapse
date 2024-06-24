<?php

namespace Bnomei;

class LapseCancelException extends \Exception
{
    public function __construct($message = 'Cancel Caching', $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
