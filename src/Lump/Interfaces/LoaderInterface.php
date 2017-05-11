<?php

namespace Bavix\Lump\Interfaces;

use Bavix\Lump\Exceptions;

interface LoaderInterface
{
    /**
     * Load a Template by name.
     *
     * @throws Exceptions\UnknownTemplateException If a template file is not found
     *
     * @param string $name
     *
     * @return string lump Template source
     */
    public function load($name);
}
