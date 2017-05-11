<?php

namespace Bavix\Lump\Caches;

use Psr\Log\LogLevel;

class NoopCache extends AbstractCache
{
    /**
     * Loads nothing. Move along.
     *
     * @param string $key
     *
     * @return bool
     */
    public function load($key)
    {
        return false;
    }

    /**
     * Loads the compiled lump Template class without caching.
     *
     * @param string $key
     * @param string $value
     */
    public function cache($key, $value)
    {
        $this->log(
            LogLevel::WARNING,
            'Template cache disabled, evaluating "{className}" class at runtime',
            array('className' => $key)
        );
        eval('?>' . $value);
    }
}
