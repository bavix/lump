<?php

namespace Bavix\Lump;

abstract class Template
{
    /**
     * @var Lump
     */
    protected $lump;

    /**
     * @var bool
     */
    protected $strictCallables = false;

    /**
     * lump Template constructor.
     *
     * @param Lump $lump
     */
    public function __construct(Lump $lump)
    {
        $this->lump = $lump;
    }

    /**
     * lump Template instances can be treated as a function and rendered by simply calling them.
     *
     *     $m = new Engine;
     *     $tpl = $m->loadTemplate('Hello, {{ name }}!');
     *     echo $tpl(array('name' => 'World')); // "Hello, World!"
     *
     * @see Template::render
     *
     * @param mixed $context Array or object rendering context (default: array())
     *
     * @return string Rendered template
     */
    public function __invoke($context = array())
    {
        return $this->render($context);
    }

    /**
     * Render this template given the rendering context.
     *
     * @param mixed $context Array or object rendering context (default: array())
     *
     * @return string Rendered template
     */
    public function render($context = array())
    {
        return $this->renderInternal(
            $this->prepareContextStack($context)
        );
    }

    /**
     * Internal rendering method implemented by lump Template concrete subclasses.
     *
     * This is where the magic happens :)
     *
     * NOTE: This method is not part of the lump.php public API.
     *
     * @param Context $context
     * @param string  $indent (default: '')
     *
     * @return string Rendered template
     */
    abstract public function renderInternal(\Bavix\Lump\Context $context, $indent = '');

    /**
     * Tests whether a value should be iterated over (e.g. in a section context).
     *
     * In most languages there are two distinct array types: list and hash (or whatever you want to call them). Lists
     * should be iterated, hashes should be treated as objects. lump follows this paradigm for Ruby, Javascript,
     * Java, Python, etc.
     *
     * PHP, however, treats lists and hashes as one primitive type: array. So lump.php needs a way to distinguish
     * between between a list of things (numeric, normalized array) and a set of variables to be used as section context
     * (associative array). In other words, this will be iterated over:
     *
     *     $items = array(
     *         array('name' => 'foo'),
     *         array('name' => 'bar'),
     *         array('name' => 'baz'),
     *     );
     *
     * ... but this will be used as a section context block:
     *
     *     $items = array(
     *         1        => array('name' => 'foo'),
     *         'banana' => array('name' => 'bar'),
     *         42       => array('name' => 'baz'),
     *     );
     *
     * @param mixed $value
     *
     * @return bool True if the value is 'iterable'
     */
    protected function isIterable($value)
    {
        switch (gettype($value))
        {
            case 'object':
                return $value instanceof \Traversable;

            case 'array':
                $i = 0;
                foreach ($value as $k => $v)
                {
                    if ($k !== $i++)
                    {
                        return false;
                    }
                }

                return true;

            default:
                return false;
        }
    }

    /**
     * Helper method to prepare the Context stack.
     *
     * Adds the lump HelperCollection to the stack's top context frame if helpers are present.
     *
     * @param mixed $context Optional first context frame (default: null)
     *
     * @return Context
     */
    protected function prepareContextStack($context = null)
    {
        $stack = new Context();

        $helpers = $this->lump->getHelpers();
        if (!$helpers->isEmpty())
        {
            $stack->push($helpers);
        }

        if (!empty($context))
        {
            $stack->push($context);
        }

        return $stack;
    }

    /**
     * Resolve a context value.
     *
     * Invoke the value if it is callable, otherwise return the value.
     *
     * @param mixed   $value
     * @param Context $context
     *
     * @return string
     */
    protected function resolveValue($value, Context $context)
    {
        if (($this->strictCallables ? is_object($value) : !is_string($value)) && is_callable($value))
        {
            return $this->lump
                ->loadLambda((string)call_user_func($value))
                ->renderInternal($context);
        }

        return $value;
    }
}
