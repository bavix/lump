<?php

namespace Bavix\Lump\Interfaces;

interface CacheInterface
{

    /**
     * Load a compiled Template class from cache.
     *
     * @param string $key
     *
     * @return bool indicates successfully class load
     */
    public function load($key);

    /**
     * Caches and load a compiled Template class.
     *
     * @param string $key
     * @param string $value
     */
    public function cache($key, $value);

}
