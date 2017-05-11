<?php

namespace Bavix\Lump;

use Bavix\Lump\Exceptions;

class Compiler
{
    private $pragmas;
    private $defaultPragmas = [];
    private $sections;
    private $blocks;
    private $source;
    private $indentNextLine;
    private $customEscape;
    private $entityFlags;
    private $charset;
    private $strictCallables;

    /**
     * Compile a lump token parse tree into PHP source code.
     *
     * @param string $source          lump Template source code
     * @param string $tree            Parse tree of lump tokens
     * @param string $name            lump Template class name
     * @param bool   $customEscape    (default: false)
     * @param string $charset         (default: 'UTF-8')
     * @param bool   $strictCallables (default: false)
     * @param int    $entityFlags     (default: ENT_COMPAT)
     *
     * @return string Generated PHP source code
     */
    public function compile($source, array $tree, $name, $customEscape = false, $charset = 'UTF-8', $strictCallables = false, $entityFlags = ENT_COMPAT)
    {
        $this->pragmas         = $this->defaultPragmas;
        $this->sections        = [];
        $this->blocks          = [];
        $this->source          = $source;
        $this->indentNextLine  = true;
        $this->customEscape    = $customEscape;
        $this->entityFlags     = $entityFlags;
        $this->charset         = $charset;
        $this->strictCallables = $strictCallables;

        return $this->writeCode($tree, $name);
    }

    /**
     * Enable pragmas across all templates, regardless of the presence of pragma
     * tags in the individual templates.
     *
     * @internal Users should set global pragmas in Engine, not here :)
     *
     * @param string[] $pragmas
     */
    public function setPragmas(array $pragmas)
    {
        $this->pragmas = [];
        foreach ($pragmas as $pragma)
        {
            $this->pragmas[$pragma] = true;
        }
        $this->defaultPragmas = $this->pragmas;
    }

    /**
     * Helper function for walking the lump token parse tree.
     *
     * @throws Exceptions\SyntaxException upon encountering unknown token types
     *
     * @param array $tree  Parse tree of lump tokens
     * @param int   $level (default: 0)
     *
     * @return string Generated PHP source code
     */
    private function walk(array $tree, $level = 0)
    {
        $code = '';
        $level++;
        foreach ($tree as $node)
        {
            switch ($node[Tokenizer::TYPE])
            {
                case Tokenizer::T_PRAGMA:
                    $this->pragmas[$node[Tokenizer::NAME]] = true;
                    break;

                case Tokenizer::T_SECTION:
                    $code .= $this->section(
                        $node[Tokenizer::NODES],
                        $node[Tokenizer::NAME],
                        isset($node[Tokenizer::FILTERS]) ? $node[Tokenizer::FILTERS] : [],
                        $node[Tokenizer::INDEX],
                        $node[Tokenizer::END],
                        $node[Tokenizer::OTAG],
                        $node[Tokenizer::CTAG],
                        $level
                    );
                    break;

                case Tokenizer::T_INVERTED:
                    $code .= $this->invertedSection(
                        $node[Tokenizer::NODES],
                        $node[Tokenizer::NAME],
                        isset($node[Tokenizer::FILTERS]) ? $node[Tokenizer::FILTERS] : [],
                        $level
                    );
                    break;

                case Tokenizer::T_PARTIAL:
                    $code .= $this->partial(
                        $node[Tokenizer::NAME],
                        isset($node[Tokenizer::INDENT]) ? $node[Tokenizer::INDENT] : '',
                        $level
                    );
                    break;

                case Tokenizer::T_PARENT:
                    $code .= $this->parent(
                        $node[Tokenizer::NAME],
                        isset($node[Tokenizer::INDENT]) ? $node[Tokenizer::INDENT] : '',
                        $node[Tokenizer::NODES],
                        $level
                    );
                    break;

                case Tokenizer::T_BLOCK_ARG:
                    $code .= $this->blockArg(
                        $node[Tokenizer::NODES],
                        $node[Tokenizer::NAME],
                        $node[Tokenizer::INDEX],
                        $node[Tokenizer::END],
                        $node[Tokenizer::OTAG],
                        $node[Tokenizer::CTAG],
                        $level
                    );
                    break;

                case Tokenizer::T_BLOCK_VAR:
                    $code .= $this->blockVar(
                        $node[Tokenizer::NODES],
                        $node[Tokenizer::NAME],
                        $node[Tokenizer::INDEX],
                        $node[Tokenizer::END],
                        $node[Tokenizer::OTAG],
                        $node[Tokenizer::CTAG],
                        $level
                    );
                    break;

                case Tokenizer::T_COMMENT:
                    break;

                case Tokenizer::T_ESCAPED:
                case Tokenizer::T_UNESCAPED:
                case Tokenizer::T_UNESCAPED_2:
                    $code .= $this->variable(
                        $node[Tokenizer::NAME],
                        isset($node[Tokenizer::FILTERS]) ? $node[Tokenizer::FILTERS] : [],
                        $node[Tokenizer::TYPE] === Tokenizer::T_ESCAPED,
                        $level
                    );
                    break;

                case Tokenizer::T_TEXT:
                    $code .= $this->text($node[Tokenizer::VALUE], $level);
                    break;

                default:
                    throw new Exceptions\SyntaxException(sprintf('Unknown token type: %s', $node[Tokenizer::TYPE]), $node);
            }
        }

        return $code;
    }

    const KLASS = '<?php

        class %s extends \\' . __NAMESPACE__ . '\\Template
        {
            private $lambdaHelper;%s

            public function renderInternal(\\' . __NAMESPACE__ . '\\Context $context, $indent = \'\')
            {
                $this->lambdaHelper = new \\' . __NAMESPACE__ . '\\LambdaHelper($this->lump, $context);
                $buffer = \'\';
                $blocksContext = [];
        %s

                return $buffer;
            }
        %s
        %s
        }';

    const KLASS_NO_LAMBDAS = '<?php

        class %s extends \\' . __NAMESPACE__ . '\\Template
        {%s
            public function renderInternal(\\' . __NAMESPACE__ . '\\Context $context, $indent = \'\')
            {
                $buffer = \'\';
                $blocksContext = [];
        %s

                return $buffer;
            }
        }';

    const STRICT_CALLABLE = 'protected $strictCallables = true;';

    /**
     * Generate lump Template class PHP source.
     *
     * @param array  $tree Parse tree of lump tokens
     * @param string $name lump Template class name
     *
     * @return string Generated PHP source code
     */
    private function writeCode($tree, $name)
    {
        $code     = $this->walk($tree);
        $sections = implode("\n", $this->sections);
        $blocks   = implode("\n", $this->blocks);
        $klass    = empty($this->sections) && empty($this->blocks) ? self::KLASS_NO_LAMBDAS : self::KLASS;

        $callable = $this->strictCallables ? $this->prepare(self::STRICT_CALLABLE) : '';

        return sprintf($this->prepare($klass, 0, false, true), $name, $callable, $code, $sections, $blocks);
    }

    const BLOCK_VAR = '
        $blockFunction = $context->findInBlock(%s);
        if (is_callable($blockFunction)) {
            $buffer .= $blockFunction($context);
        } else {%s
        }
    ';

    /**
     * Generate lump Template inheritance block variable PHP source.
     *
     * @param array  $nodes Array of child tokens
     * @param string $id    Section name
     * @param int    $start Section start offset
     * @param int    $end   Section end offset
     * @param string $otag  Current lump opening tag
     * @param string $ctag  Current lump closing tag
     * @param int    $level
     *
     * @return string Generated PHP source code
     */
    private function blockVar($nodes, $id, $start, $end, $otag, $ctag, $level)
    {
        $id = var_export($id, true);

        return sprintf($this->prepare(self::BLOCK_VAR, $level), $id, $this->walk($nodes, $level));
    }

    const BLOCK_ARG = '$blocksContext[%s] = [$this, \'block%s\'];';

    /**
     * Generate lump Template inheritance block argument PHP source.
     *
     * @param array  $nodes Array of child tokens
     * @param string $id    Section name
     * @param int    $start Section start offset
     * @param int    $end   Section end offset
     * @param string $otag  Current lump opening tag
     * @param string $ctag  Current lump closing tag
     * @param int    $level
     *
     * @return string Generated PHP source code
     */
    private function blockArg($nodes, $id, $start, $end, $otag, $ctag, $level)
    {
        $key    = $this->block($nodes);
        $keystr = var_export($key, true);
        $id     = var_export($id, true);

        return sprintf($this->prepare(self::BLOCK_ARG, 1), $id, $key);
    }

    const BLOCK_FUNCTION = '
        public function block%s($context)
        {
            $indent = $buffer = \'\';
            $blocksContext = [];%s

            return $buffer;
        }
    ';

    /**
     * Generate lump Template inheritance block function PHP source.
     *
     * @param array $nodes Array of child tokens
     *
     * @return string key of new block function
     */
    private function block($nodes)
    {
        $code = $this->walk($nodes, 0);
        $key  = ucfirst(sha1($code));

        if (!isset($this->blocks[$key]))
        {
            $this->blocks[$key] = sprintf($this->prepare(self::BLOCK_FUNCTION, 0), $key, $code);
        }

        return $key;
    }

    const SECTION_CALL = '
        // %s section
        $value = $context->%s(%s);%s
        $buffer .= $this->section%s($context, $indent, $value);
    ';

    const SECTION = '
        private function section%s(\\' . __NAMESPACE__ . '\\Context $context, $indent, $value)
        {
            $buffer = \'\';
            $blocksContext = [];

            if (%s) {
                $source = %s;
                $result = $value($source, %s);
                if (\strpos($result, \'{{\') === false) {
                    $buffer .= $result;
                } else {
                    $buffer .= $this->lump
                        ->loadLambda((string) $result%s)
                        ->renderInternal($context);
                }
            } elseif (!empty($value)) {
                $values = $this->isIterable($value) ? $value : [$value];
                foreach ($values as $value) {
                    $context->push($value);
                    %s
                    $context->pop();
                }
            }

            return $buffer;
        }
    ';

    /**
     * Generate lump Template section PHP source.
     *
     * @param array    $nodes   Array of child tokens
     * @param string   $id      Section name
     * @param string[] $filters Array of filters
     * @param int      $start   Section start offset
     * @param int      $end     Section end offset
     * @param string   $otag    Current lump opening tag
     * @param string   $ctag    Current lump closing tag
     * @param int      $level
     * @param bool     $arg     (default: false)
     *
     * @return string Generated section PHP source code
     */
    private function section($nodes, $id, $filters, $start, $end, $otag, $ctag, $level, $arg = false)
    {
        $source   = var_export(substr($this->source, $start, $end - $start), true);
        $callable = $this->getCallable();

        if ($otag !== '{{' || $ctag !== '}}')
        {
            $delimTag = var_export(sprintf('{{= %s %s =}}', $otag, $ctag), true);
            $helper   = sprintf('$this->lambdaHelper->withDelimiters(%s)', $delimTag);
            $delims   = ', ' . $delimTag;
        }
        else
        {
            $helper = '$this->lambdaHelper';
            $delims = '';
        }

        $key = ucfirst(sha1($delims . "\n" . $source));

        if (!isset($this->sections[$key]))
        {
            $this->sections[$key] = sprintf($this->prepare(self::SECTION), $key, $callable, $source, $helper, $delims, $this->walk($nodes, 2));
        }

        if ($arg === true)
        {
            return $key;
        }

        $method  = $this->getFindMethod($id);
        $id      = var_export($id, true);
        $filters = $this->getFilters($filters, $level);

        return sprintf($this->prepare(self::SECTION_CALL, $level), $id, $method, $id, $filters, $key);
    }

    const INVERTED_SECTION = '
        // %s inverted section
        $value = $context->%s(%s);%s
        if (empty($value)) {
            %s
        }
    ';

    /**
     * Generate lump Template inverted section PHP source.
     *
     * @param array    $nodes   Array of child tokens
     * @param string   $id      Section name
     * @param string[] $filters Array of filters
     * @param int      $level
     *
     * @return string Generated inverted section PHP source code
     */
    private function invertedSection($nodes, $id, $filters, $level)
    {
        $method  = $this->getFindMethod($id);
        $id      = var_export($id, true);
        $filters = $this->getFilters($filters, $level);

        return sprintf($this->prepare(self::INVERTED_SECTION, $level), $id, $method, $id, $filters, $this->walk($nodes, $level));
    }

    const PARTIAL_INDENT = ', $indent . %s';
    const PARTIAL        = '
        if ($partial = $this->lump->loadPartial(%s)) {
            $buffer .= $partial->renderInternal($context%s);
        }
    ';

    /**
     * Generate lump Template partial call PHP source.
     *
     * @param string $id     Partial name
     * @param string $indent Whitespace indent to apply to partial
     * @param int    $level
     *
     * @return string Generated partial call PHP source code
     */
    private function partial($id, $indent, $level)
    {
        if ($indent !== '')
        {
            $indentParam = sprintf(self::PARTIAL_INDENT, var_export($indent, true));
        }
        else
        {
            $indentParam = '';
        }

        return sprintf(
            $this->prepare(self::PARTIAL, $level),
            var_export($id, true),
            $indentParam
        );
    }

    const PARENT = '
        %s

        if ($parent = $this->lump->loadPartial(%s)) {
            $context->pushBlockContext($blocksContext);
            $buffer .= $parent->renderInternal($context, $indent);
            $context->popBlockContext();
        }
    ';

    /**
     * Generate lump Template inheritance parent call PHP source.
     *
     * @param string $id       Parent tag name
     * @param string $indent   Whitespace indent to apply to parent
     * @param array  $children Child nodes
     * @param int    $level
     *
     * @return string Generated PHP source code
     */
    private function parent($id, $indent, array $children, $level)
    {
        $realChildren = array_filter($children, array(__CLASS__, 'onlyBlockArgs'));

        return sprintf(
            $this->prepare(self::PARENT, $level),
            $this->walk($realChildren, $level),
            var_export($id, true),
            var_export($indent, true)
        );
    }

    /**
     * Helper method for filtering out non-block-arg tokens.
     *
     * @param array $node
     *
     * @return bool True if $node is a block arg token
     */
    private static function onlyBlockArgs(array $node)
    {
        return $node[Tokenizer::TYPE] === Tokenizer::T_BLOCK_ARG;
    }

    const VARIABLE = '
        $value = $this->resolveValue($context->%s(%s), $context);%s
        $buffer .= %s%s;
    ';

    /**
     * Generate lump Template variable interpolation PHP source.
     *
     * @param string   $id      Variable name
     * @param string[] $filters Array of filters
     * @param bool     $escape  Escape the variable value for output?
     * @param int      $level
     *
     * @return string Generated variable interpolation PHP source
     */
    private function variable($id, $filters, $escape, $level)
    {
        $method  = $this->getFindMethod($id);
        $id      = ($method !== 'last') ? var_export($id, true) : '';
        $filters = $this->getFilters($filters, $level);
        $value   = $escape ? $this->getEscape() : '$value';

        return sprintf($this->prepare(self::VARIABLE, $level), $method, $id, $filters, $this->flushIndent(), $value);
    }

    const FILTER = '
        $filter = $context->%s(%s);
        if (!(%s)) {
            throw new \\' . __NAMESPACE__ . '\\Exceptions\UnknownFilterException(%s);
        }
        $value = $filter($value);%s
    ';

    /**
     * Generate lump Template variable filtering PHP source.
     *
     * @param string[] $filters Array of filters
     * @param int      $level
     *
     * @return string Generated filter PHP source
     */
    private function getFilters(array $filters, $level)
    {
        if (empty($filters))
        {
            return '';
        }

        $name     = array_shift($filters);
        $method   = $this->getFindMethod($name);
        $filter   = ($method !== 'last') ? var_export($name, true) : '';
        $callable = $this->getCallable('$filter');
        $msg      = var_export($name, true);

        return sprintf($this->prepare(self::FILTER, $level), $method, $filter, $callable, $msg, $this->getFilters($filters, $level));
    }

    const LINE = '$buffer .= "\n";';
    const TEXT = '$buffer .= %s%s;';

    /**
     * Generate lump Template output Buffer call PHP source.
     *
     * @param string $text
     * @param int    $level
     *
     * @return string Generated output Buffer call PHP source
     */
    private function text($text, $level)
    {
        $indentNextLine       = (substr($text, -1) === "\n");
        $code                 = sprintf($this->prepare(self::TEXT, $level), $this->flushIndent(), var_export($text, true));
        $this->indentNextLine = $indentNextLine;

        return $code;
    }

    /**
     * Prepare PHP source code snippet for output.
     *
     * @param string $text
     * @param int    $bonus          Additional indent level (default: 0)
     * @param bool   $prependNewline Prepend a newline to the snippet? (default: true)
     * @param bool   $appendNewline  Append a newline to the snippet? (default: false)
     *
     * @return string PHP source code snippet
     */
    private function prepare($text, $bonus = 0, $prependNewline = true, $appendNewline = false)
    {
        $text = ($prependNewline ? "\n" : '') . trim($text);
        if ($prependNewline)
        {
            $bonus++;
        }
        if ($appendNewline)
        {
            $text .= "\n";
        }

        return preg_replace("/\n( {8})?/", "\n" . str_repeat(' ', $bonus * 4), $text);
    }

    const DEFAULT_ESCAPE = '\htmlspecialchars(%s, %s, %s)';
    const CUSTOM_ESCAPE  = '\call_user_func($this->lump->getEscape(), %s)';

    /**
     * Get the current escaper.
     *
     * @param string $value (default: '$value')
     *
     * @return string Either a custom callback, or an inline call to `htmlspecialchars`
     */
    private function getEscape($value = '$value')
    {
        if ($this->customEscape)
        {
            return sprintf(self::CUSTOM_ESCAPE, $value);
        }

        return sprintf(self::DEFAULT_ESCAPE, $value, var_export($this->entityFlags, true), var_export($this->charset, true));
    }

    /**
     * Select the appropriate Context `find` method for a given $id.
     *
     * The return value will be one of `find`, `findDot` or `last`.
     *
     * @see Context::find
     * @see Context::findDot
     * @see Context::last
     *
     * @param string $id Variable name
     *
     * @return string `find` method name
     */
    private function getFindMethod($id)
    {
        if ($id === '.')
        {
            return 'last';
        }

        if (isset($this->pragmas[Lump::PRAGMA_ANCHORED_DOT]) && $this->pragmas[Lump::PRAGMA_ANCHORED_DOT])
        {
            if ($id{0} === '.')
            {
                return 'findAnchoredDot';
            }
        }

        if (strpos($id, '.') === false)
        {
            return 'find';
        }

        return 'findDot';
    }

    const IS_CALLABLE        = '!\is_string(%s) && \is_callable(%s)';
    const STRICT_IS_CALLABLE = '\is_object(%s) && \is_callable(%s)';

    /**
     * Helper function to compile strict vs lax "is callable" logic.
     *
     * @param string $variable (default: '$value')
     *
     * @return string "is callable" logic
     */
    private function getCallable($variable = '$value')
    {
        $tpl = $this->strictCallables ? self::STRICT_IS_CALLABLE : self::IS_CALLABLE;

        return sprintf($tpl, $variable, $variable);
    }

    const LINE_INDENT = '$indent . ';

    /**
     * Get the current $indent prefix to write to the buffer.
     *
     * @return string "$indent . " or ""
     */
    private function flushIndent()
    {
        if (!$this->indentNextLine)
        {
            return '';
        }

        $this->indentNextLine = false;

        return self::LINE_INDENT;
    }
}