<?php

namespace Bavix\Lump\Loaders;

use Bavix\Lump\Exceptions\UnknownTemplateException;
use Bavix\Lump\Interfaces\LoaderInterface;

/**
 * A lump Template cascading loader implementation, which delegates to other
 * Loaders instances.
 */
class CascadingLoader implements LoaderInterface
{
    private $loaders;

    /**
     * Construct a CascadingLoader with an array of loaders.
     *
     *     $loader = new Loader_CascadingLoader(array(
     *         new Loader_InlineLoader(__FILE__, __COMPILER_HALT_OFFSET__),
     *         new Loader_FilesystemLoader(__DIR__.'/templates')
     *     ));
     *
     * @param LoaderInterface[] $loaders
     */
    public function __construct(array $loaders = array())
    {
        $this->loaders = array();
        foreach ($loaders as $loader)
        {
            $this->addLoader($loader);
        }
    }

    /**
     * Add a Loaders instance.
     *
     * @param LoaderInterface $loader
     */
    public function addLoader(LoaderInterface $loader)
    {
        $this->loaders[] = $loader;
    }

    /**
     * Load a Template by name.
     *
     * @throws UnknownTemplateException If a template file is not found
     *
     * @param string $name
     *
     * @return string lump Template source
     */
    public function load($name)
    {
        foreach ($this->loaders as $loader)
        {
            try
            {
                return $loader->load($name);
            }
            catch (UnknownTemplateException $e)
            {
                // do nothing, check the next loader.
            }
        }

        throw new UnknownTemplateException($name);
    }
}
