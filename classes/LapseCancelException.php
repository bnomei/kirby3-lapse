<?php

namespace Bnomei;

class LapseCancelException extends \Exception
{
    public function __construct(string $message = 'Cancel Caching', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
