<?php

namespace Bavix\Lump\Exceptions;

use Bavix\Lump\Interfaces\ExceptionInterface;

class UnknownTemplateException extends InvalidArgumentException implements ExceptionInterface
{
    protected $templateName;

    /**
     * @param string     $templateName
     * @param \Throwable $previous
     */
    public function __construct($templateName, \Throwable $previous = null)
    {
        $this->templateName = $templateName;
        $message            = sprintf('Unknown template: %s', $templateName);
        parent::__construct($message, 0, $previous);
    }

    public function getTemplateName()
    {
        return $this->templateName;
    }
}
