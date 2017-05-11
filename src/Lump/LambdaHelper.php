<?php

namespace Bavix\Lump;

class LambdaHelper
{
    private $lump;
    private $context;
    private $delims;

    /**
     * lump Lambda Helper constructor.
     *
     * @param Lump    $lump    lump engine instance
     * @param Context $context Rendering context
     * @param string  $delims  Optional custom delimiters, in the format `{{= <% %> =}}`. (default: null)
     */
    public function __construct(Lump $lump, Context $context, $delims = null)
    {
        $this->lump = $lump;
        $this->context  = $context;
        $this->delims   = $delims;
    }

    /**
     * Render a string as a lump template with the current rendering context.
     *
     * @param string $string
     *
     * @return string Rendered template
     */
    public function render($string)
    {
        return $this->lump
            ->loadLambda((string)$string, $this->delims)
            ->renderInternal($this->context);
    }

    /**
     * Render a string as a lump template with the current rendering context.
     *
     * @param string $string
     *
     * @return string Rendered template
     */
    public function __invoke($string)
    {
        return $this->render($string);
    }

    /**
     * Get a Lambda Helper with custom delimiters.
     *
     * @param string $delims Custom delimiters, in the format `{{= <% %> =}}`
     *
     * @return LambdaHelper
     */
    public function withDelimiters($delims)
    {
        return new self($this->lump, $this->context, $delims);
    }
}
