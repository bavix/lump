<?php

namespace Bavix\Lump;

use Bavix\Lump\Interfaces\CacheInterface;
use Bavix\Lump\Interfaces\LoaderInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class Lump
{
    const VERSION      = '1.0.0';
    const SPEC_VERSION = '2.14.6';

    const PRAGMA_FILTERS      = 'FILTERS';
    const PRAGMA_BLOCKS       = 'BLOCKS';
    const PRAGMA_ANCHORED_DOT = 'ANCHORED-DOT';

    // Known pragmas
    private static $knownPragmas = array(
        self::PRAGMA_FILTERS      => true,
        self::PRAGMA_BLOCKS       => true,
        self::PRAGMA_ANCHORED_DOT => true,
    );

    // Template cache
    private $templates = array();

    // Environment
    private $templateClassPrefix  = '__';
    private $cache;
    private $lambdaCache;
    private $cacheLambdaTemplates = false;
    private $loader;
    private $partialsLoader;
    private $helpers;
    private $escape;
    private $entityFlags          = ENT_COMPAT;
    private $charset              = 'UTF-8';
    private $logger;
    private $strictCallables      = false;
    private $pragmas              = array();

    // Services
    private $tokenizer;
    private $parser;
    private $compiler;

    /**
     * lump class constructor.
     *
     * Passing an $options array allows overriding certain lump options during instantiation:
     *
     *     $options = array(
     *         // The class prefix for compiled templates. Defaults to '__'.
     *         'template_class_prefix' => '__MyTemplates_',
     *
     *         // A lump cache instance or a cache directory string for compiled templates.
     *         // lump will not cache templates unless this is set.
     *         'cache' => dirname(__FILE__).'/tmp/cache/lump',
     *
     *         // Override default permissions for cache files. Defaults to using the system-defined umask. It is
     *         // *strongly* recommended that you configure your umask properly rather than overriding permissions here.
     *         'cache_file_mode' => 0666,
     *
     *         // Optionally, enable caching for lambda section templates. This is generally not recommended, as lambda
     *         // sections are often too dynamic to benefit from caching.
     *         'cache_lambda_templates' => true,
     *
     *         // A lump template loader instance. Uses a StringLoader if not specified.
     *         'loader' => new Loader_FilesystemLoader(dirname(__FILE__).'/views'),
     *
     *         // A lump loader instance for partials.
     *         'partials_loader' => new Loader_FilesystemLoader(dirname(__FILE__).'/views/partials'),
     *
     *         // An array of lump partials. Useful for quick-and-dirty string template loading, but not as
     *         // efficient or lazy as a Filesystem (or database) loader.
     *         'partials' => array('foo' => file_get_contents(dirname(__FILE__).'/views/partials/foo.lump')),
     *
     *         // An array of 'helpers'. Helpers can be global variables or objects, closures (e.g. for higher order
     *         // sections), or any other valid lump context value. They will be prepended to the context stack,
     *         // so they will be available in any template loaded by this lump instance.
     *         'helpers' => array('i18n' => function ($text) {
     *             // do something translatey here...
     *         }),
     *
     *         // An 'escape' callback, responsible for escaping double-lump variables.
     *         'escape' => function ($value) {
     *             return htmlspecialchars($buffer, ENT_COMPAT, 'UTF-8');
     *         },
     *
     *         // Type argument for `htmlspecialchars`.  Defaults to ENT_COMPAT.  You may prefer ENT_QUOTES.
     *         'entity_flags' => ENT_QUOTES,
     *
     *         // Character set for `htmlspecialchars`. Defaults to 'UTF-8'. Use 'UTF-8'.
     *         'charset' => 'ISO-8859-1',
     *
     *         // A lump Loggers instance. No logging will occur unless this is set. Using a PSR-3 compatible
     *         // logging library -- such as Monolog -- is highly recommended. A simple stream logger implementation is
     *         // available as well:
     *         'logger' => new Logger_StreamLogger('php://stderr'),
     *
     *         // Only treat Closure instances and invokable classes as callable. If true, values like
     *         // `array('ClassName', 'methodName')` and `array($classInstance, 'methodName')`, which are traditionally
     *         // "callable" in PHP, are not called to resolve variables for interpolation or section contexts. This
     *         // helps protect against arbitrary code execution when user input is passed directly into the template.
     *         // This currently defaults to false, but will default to true in v3.0.
     *         'strict_callables' => true,
     *
     *         // Enable pragmas across all templates, regardless of the presence of pragma tags in the individual
     *         // templates.
     *         'pragmas' => [Engine::PRAGMA_FILTERS],
     *     );
     *
     * @throws Exceptions\InvalidArgumentException If `escape` option is not callable
     *
     * @param array $options (default: array())
     */
    public function __construct(array $options = array())
    {
        if (isset($options['template_class_prefix']))
        {
            $this->templateClassPrefix = $options['template_class_prefix'];
        }

        if (isset($options['cache']))
        {
            $cache = $options['cache'];

            if (is_string($cache))
            {
                $mode  = isset($options['cache_file_mode']) ? $options['cache_file_mode'] : null;
                $cache = new Caches\FilesystemCache($cache, $mode);
            }

            $this->setCache($cache);
        }

        if (isset($options['cache_lambda_templates']))
        {
            $this->cacheLambdaTemplates = (bool)$options['cache_lambda_templates'];
        }

        if (isset($options['loader']))
        {
            $this->setLoader($options['loader']);
        }

        if (isset($options['partials_loader']))
        {
            $this->setPartialsLoader($options['partials_loader']);
        }

        if (isset($options['partials']))
        {
            $this->setPartials($options['partials']);
        }

        if (isset($options['helpers']))
        {
            $this->setHelpers($options['helpers']);
        }

        if (isset($options['escape']))
        {
            if (!is_callable($options['escape']))
            {
                throw new Exceptions\InvalidArgumentException('lump Constructor "escape" option must be callable');
            }

            $this->escape = $options['escape'];
        }

        if (isset($options['entity_flags']))
        {
            $this->entityFlags = $options['entity_flags'];
        }

        if (isset($options['charset']))
        {
            $this->charset = $options['charset'];
        }

        if (isset($options['logger']))
        {
            $this->setLogger($options['logger']);
        }

        if (isset($options['strict_callables']))
        {
            $this->strictCallables = $options['strict_callables'];
        }

        if (isset($options['pragmas']))
        {
            foreach ($options['pragmas'] as $pragma)
            {
                if (!isset(self::$knownPragmas[$pragma]))
                {
                    throw new Exceptions\InvalidArgumentException(sprintf('Unknown pragma: "%s".', $pragma));
                }
                $this->pragmas[$pragma] = true;
            }
        }
    }

    /**
     * Shortcut 'render' invocation.
     *
     * Equivalent to calling `$lump->loadTemplate($template)->render($context);`
     *
     * @see Lump::loadTemplate
     * @see Template::render
     *
     * @param string $template
     * @param mixed  $context (default: array())
     *
     * @return string Rendered template
     */
    public function render($template, $context = array())
    {
        return $this->loadTemplate($template)->render($context);
    }

    /**
     * Get the current lump escape callback.
     *
     * @return callable|null
     */
    public function getEscape()
    {
        return $this->escape;
    }

    /**
     * Get the current lump entitity type to escape.
     *
     * @return int
     */
    public function getEntityFlags()
    {
        return $this->entityFlags;
    }

    /**
     * Get the current lump character set.
     *
     * @return string
     */
    public function getCharset()
    {
        return $this->charset;
    }

    /**
     * Get the current globally enabled pragmas.
     *
     * @return array
     */
    public function getPragmas()
    {
        return array_keys($this->pragmas);
    }

    /**
     * Set the lump template Loaders instance.
     *
     * @param LoaderInterface $loader
     */
    public function setLoader(LoaderInterface $loader)
    {
        $this->loader = $loader;
    }

    /**
     * Get the current lump template Loaders instance.
     *
     * If no Loaders instance has been explicitly specified, this method will instantiate and return
     * a StringLoader instance.
     *
     * @return LoaderInterface
     */
    public function getLoader()
    {
        if (!isset($this->loader))
        {
            $this->loader = new Loaders\StringLoader();
        }

        return $this->loader;
    }

    /**
     * Set the lump partials Loaders instance.
     *
     * @param LoaderInterface $partialsLoader
     */
    public function setPartialsLoader(LoaderInterface $partialsLoader)
    {
        $this->partialsLoader = $partialsLoader;
    }

    /**
     * Get the current lump partials Loaders instance.
     *
     * If no Loaders instance has been explicitly specified, this method will instantiate and return
     * an ArrayLoader instance.
     *
     * @return LoaderInterface
     */
    public function getPartialsLoader()
    {
        if (!isset($this->partialsLoader))
        {
            $this->partialsLoader = new Loaders\ArrayLoader();
        }

        return $this->partialsLoader;
    }

    /**
     * Set partials for the current partials Loaders instance.
     *
     * @throws Exceptions\RuntimeException If the current Loaders instance is immutable
     *
     * @param array $partials (default: array())
     */
    public function setPartials(array $partials = array())
    {
        if (!isset($this->partialsLoader))
        {
            $this->partialsLoader = new Loaders\ArrayLoader();
        }

        if (!$this->partialsLoader instanceof Loaders\MutableLoader)
        {
            throw new Exceptions\RuntimeException('Unable to set partials on an immutable lump Loaders instance');
        }

        $this->partialsLoader->setTemplates($partials);
    }

    /**
     * Set an array of lump helpers.
     *
     * An array of 'helpers'. Helpers can be global variables or objects, closures (e.g. for higher order sections), or
     * any other valid lump context value. They will be prepended to the context stack, so they will be available in
     * any template loaded by this lump instance.
     *
     * @throws Exceptions\InvalidArgumentException if $helpers is not an array or Traversable
     *
     * @param array|\Traversable $helpers
     */
    public function setHelpers($helpers)
    {
        if (!is_array($helpers) && !$helpers instanceof \Traversable)
        {
            throw new Exceptions\InvalidArgumentException('setHelpers expects an array of helpers');
        }

        $this->getHelpers()->clear();

        foreach ($helpers as $name => $helper)
        {
            $this->addHelper($name, $helper);
        }
    }

    /**
     * Get the current set of lump helpers.
     *
     * @see Lump::setHelpers
     *
     * @return HelperCollection
     */
    public function getHelpers()
    {
        if (null === $this->helpers)
        {
            $this->helpers = new HelperCollection();
        }

        return $this->helpers;
    }

    /**
     * Add a new lump helper.
     *
     * @see Lump::setHelpers
     *
     * @param string $name
     * @param mixed  $helper
     */
    public function addHelper($name, $helper)
    {
        $this->getHelpers()->add($name, $helper);
    }

    /**
     * Get a lump helper by name.
     *
     * @see Lump::setHelpers
     *
     * @param string $name
     *
     * @return mixed Helper
     */
    public function getHelper($name)
    {
        return $this->getHelpers()->get($name);
    }

    /**
     * Check whether this lump instance has a helper.
     *
     * @see Lump::setHelpers
     *
     * @param string $name
     *
     * @return bool True if the helper is present
     */
    public function hasHelper($name)
    {
        return $this->getHelpers()->has($name);
    }

    /**
     * Remove a helper by name.
     *
     * @see Lump::setHelpers
     *
     * @param string $name
     */
    public function removeHelper($name)
    {
        $this->getHelpers()->remove($name);
    }

    /**
     * Set the lump Loggers instance.
     *
     * @throws Exceptions\InvalidArgumentException If logger is not an instance of Loggers or Psr\Log\LoggerInterface
     *
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function setLogger($logger = null)
    {
        if ($logger !== null && !($logger instanceof LoggerInterface))
        {
            throw new Exceptions\InvalidArgumentException('Expected an instance of Loggers or Psr\\Log\\LoggerInterface.');
        }

        if ($this->getCache()->getLogger() === null)
        {
            $this->getCache()->setLogger($logger);
        }

        $this->logger = $logger;
    }

    /**
     * Get the current lump Loggers instance.
     *
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Set the lump Tokenizer instance.
     *
     * @param Tokenizer $tokenizer
     */
    public function setTokenizer(Tokenizer $tokenizer)
    {
        $this->tokenizer = $tokenizer;
    }

    /**
     * Get the current lump Tokenizer instance.
     *
     * If no Tokenizer instance has been explicitly specified, this method will instantiate and return a new one.
     *
     * @return Tokenizer
     */
    public function getTokenizer()
    {
        if (!isset($this->tokenizer))
        {
            $this->tokenizer = new Tokenizer();
        }

        return $this->tokenizer;
    }

    /**
     * Set the lump Parser instance.
     *
     * @param Parser $parser
     */
    public function setParser(Parser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Get the current lump Parser instance.
     *
     * If no Parser instance has been explicitly specified, this method will instantiate and return a new one.
     *
     * @return Parser
     */
    public function getParser()
    {
        if (!isset($this->parser))
        {
            $this->parser = new Parser();
        }

        return $this->parser;
    }

    /**
     * Set the lump Compiler instance.
     *
     * @param Compiler $compiler
     */
    public function setCompiler(Compiler $compiler)
    {
        $this->compiler = $compiler;
    }

    /**
     * Get the current lump Compiler instance.
     *
     * If no Compiler instance has been explicitly specified, this method will instantiate and return a new one.
     *
     * @return Compiler
     */
    public function getCompiler()
    {
        if (!isset($this->compiler))
        {
            $this->compiler = new Compiler();
        }

        return $this->compiler;
    }

    /**
     * Set the lump Caches instance.
     *
     * @param CacheInterface $cache
     */
    public function setCache(CacheInterface $cache)
    {
        if (isset($this->logger) && $cache->getLogger() === null)
        {
            $cache->setLogger($this->getLogger());
        }

        $this->cache = $cache;
    }

    /**
     * Get the current lump Caches instance.
     *
     * If no Caches instance has been explicitly specified, this method will instantiate and return a new one.
     *
     * @return CacheInterface
     */
    public function getCache()
    {
        if (!isset($this->cache))
        {
            $this->setCache(new Caches\NoopCache());
        }

        return $this->cache;
    }

    /**
     * Get the current Lambda Caches instance.
     *
     * If 'cache_lambda_templates' is enabled, this is the default cache instance. Otherwise, it is a NoopCache.
     *
     * @see Lump::getCache
     *
     * @return CacheInterface
     */
    protected function getLambdaCache()
    {
        if ($this->cacheLambdaTemplates)
        {
            return $this->getCache();
        }

        if (!isset($this->lambdaCache))
        {
            $this->lambdaCache = new Caches\NoopCache();
        }

        return $this->lambdaCache;
    }

    /**
     * Helper method to generate a lump template class.
     *
     * @param string $source
     *
     * @return string lump Template class name
     */
    public function getTemplateClassName($source)
    {
        return $this->templateClassPrefix . sha1(sprintf(
            'version:%s,escape:%s,entity_flags:%i,charset:%s,strict_callables:%s,pragmas:%s,source:%s',
            self::VERSION,
            isset($this->escape) ? 'custom' : 'default',
            $this->entityFlags,
            $this->charset,
            $this->strictCallables ? 'true' : 'false',
            implode(' ', $this->getPragmas()),
            $source
        ));
    }

    /**
     * Load a lump Template by name.
     *
     * @param string $name
     *
     * @return Template
     */
    public function loadTemplate($name)
    {
        return $this->loadSource($this->getLoader()->load($name));
    }

    /**
     * Load a lump partial Template by name.
     *
     * This is a helper method used internally by Template instances for loading partial templates. You can most likely
     * ignore it completely.
     *
     * @param string $name
     *
     * @return Template
     */
    public function loadPartial($name)
    {
        try
        {
            if (isset($this->partialsLoader))
            {
                $loader = $this->partialsLoader;
            }
            elseif (isset($this->loader) && !$this->loader instanceof Loaders\StringLoader)
            {
                $loader = $this->loader;
            }
            else
            {
                throw new Exceptions\UnknownTemplateException($name);
            }

            return $this->loadSource($loader->load($name));
        }
        catch (Exceptions\UnknownTemplateException $e)
        {
            // If the named partial cannot be found, log then return null.
            $this->log(
                LogLevel::WARNING,
                'Partial not found: "{name}"',
                array('name' => $e->getTemplateName())
            );
        }
    }

    /**
     * Load a lump lambda Template by source.
     *
     * This is a helper method used by Template instances to generate subtemplates for Lambda sections. You can most
     * likely ignore it completely.
     *
     * @param string $source
     * @param string $delims (default: null)
     *
     * @return Template
     */
    public function loadLambda($source, $delims = null)
    {
        if ($delims !== null)
        {
            $source = $delims . "\n" . $source;
        }

        return $this->loadSource($source, $this->getLambdaCache());
    }

    /**
     * Instantiate and return a lump Template instance by source.
     *
     * Optionally provide a Caches instance. This is used internally by Engine::loadLambda to respect
     * the 'cache_lambda_templates' configuration option.
     *
     * @see Lump::loadTemplate
     * @see Lump::loadPartial
     * @see Lump::loadLambda
     *
     * @param string $source
     * @param CacheInterface  $cache (default: null)
     *
     * @return Template
     */
    private function loadSource($source, CacheInterface $cache = null)
    {
        $className = $this->getTemplateClassName($source);

        if (!isset($this->templates[$className]))
        {
            if ($cache === null)
            {
                $cache = $this->getCache();
            }

            if (!class_exists($className, false))
            {
                if (!$cache->load($className))
                {
                    $compiled = $this->compile($source);
                    $cache->cache($className, $compiled);
                }
            }

            $this->log(
                LogLevel::DEBUG,
                'Instantiating template: "{className}"',
                array('className' => $className)
            );

            $this->templates[$className] = new $className($this);
        }

        return $this->templates[$className];
    }

    /**
     * Helper method to tokenize a lump template.
     *
     * @see Tokenizer::scan
     *
     * @param string $source
     *
     * @return array Tokens
     */
    private function tokenize($source)
    {
        return $this->getTokenizer()->scan($source);
    }

    /**
     * Helper method to parse a lump template.
     *
     * @see Parser::parse
     *
     * @param string $source
     *
     * @return array Token tree
     */
    private function parse($source)
    {
        $parser = $this->getParser();
        $parser->setPragmas($this->getPragmas());

        return $parser->parse($this->tokenize($source));
    }

    /**
     * Helper method to compile a lump template.
     *
     * @see Compiler::compile
     *
     * @param string $source
     *
     * @return string generated lump template class code
     */
    private function compile($source)
    {
        $tree = $this->parse($source);
        $name = $this->getTemplateClassName($source);

        $this->log(
            LogLevel::INFO,
            'Compiling template to "{className}" class',
            array('className' => $name)
        );

        $compiler = $this->getCompiler();
        $compiler->setPragmas($this->getPragmas());

        return $compiler->compile($source, $tree, $name, isset($this->escape), $this->charset, $this->strictCallables, $this->entityFlags);
    }

    /**
     * Add a log record if logging is enabled.
     *
     * @param int    $level   The logging level
     * @param string $message The log message
     * @param array  $context The log context
     */
    private function log($level, $message, array $context = array())
    {
        if (isset($this->logger))
        {
            $this->getLogger()->log($level, $message, $context);
        }
    }
}
