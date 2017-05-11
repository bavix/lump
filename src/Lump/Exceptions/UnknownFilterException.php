<?php

namespace Bavix\Lump\Exceptions;

use Bavix\Exceptions\UnexpectedValue;
use Bavix\Lump\Interfaces\ExceptionInterface;

class UnknownFilterException extends UnexpectedValue implements ExceptionInterface
{

    protected $filterName;

    /**
     * @param string     $filterName
     * @param \Throwable $previous
     */
    public function __construct($filterName, \Throwable $previous = null)
    {
        $this->filterName = $filterName;
        $message          = sprintf('Unknown filter: %s', $filterName);
        parent::__construct($message, 0, $previous);
    }

    public function getFilterName()
    {
        return $this->filterName;
    }

}
