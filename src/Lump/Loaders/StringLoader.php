<?php

namespace Bavix\Lump\Loaders;

use Bavix\Lump\Interfaces\LoaderInterface;

/**
 * lump Template string Loaders implementation.
 *
 * A StringLoader instance is essentially a noop. It simply passes the 'name' argument straight through:
 *
 *     $loader = new StringLoader;
 *     $tpl = $loader->load('{{ foo }}'); // '{{ foo }}'
 *
 * This is the default Template Loaders instance used by lump:
 *
 *     $m = new lump;
 *     $tpl = $m->loadTemplate('{{ foo }}');
 *     echo $tpl->render(array('foo' => 'bar')); // "bar"
 */
class StringLoader implements LoaderInterface
{
    /**
     * Load a Template by source.
     *
     * @param string $name lump Template source
     *
     * @return string lump Template source
     */
    public function load($name)
    {
        return $name;
    }
}
