<?php

namespace Bavix\Lump\Exceptions;

use Bavix\Exceptions\Logic;
use Bavix\Lump\Interfaces\ExceptionInterface;

class SyntaxException extends Logic implements ExceptionInterface
{
    protected $token;

    /**
     * @param string     $msg
     * @param array      $token
     * @param \Throwable $previous
     */
    public function __construct($msg, array $token, \Throwable $previous = null)
    {
        $this->token = $token;
        parent::__construct($msg, 0, $previous);
    }

    /**
     * @return array
     */
    public function getToken()
    {
        return $this->token;
    }
}
