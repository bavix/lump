<?php

namespace Bavix\Lump\Exceptions;

use Bavix\Lump\Interfaces\ExceptionInterface;

class UnknownHelperException extends InvalidArgumentException implements ExceptionInterface
{
    protected $helperName;

    /**
     * @param string     $helperName
     * @param \Throwable $previous
     */
    public function __construct($helperName, \Throwable $previous = null)
    {
        $this->helperName = $helperName;
        $message          = sprintf('Unknown helper: %s', $helperName);
        parent::__construct($message, 0, $previous);
    }

    public function getHelperName()
    {
        return $this->helperName;
    }
}
