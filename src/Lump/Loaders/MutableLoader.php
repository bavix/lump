<?php

namespace Bavix\Lump\Loaders;

/**
 * lump Template mutable Loaders interface.
 */
interface MutableLoader
{
    /**
     * Set an associative array of Template sources for this loader.
     *
     * @param array $templates
     */
    public function setTemplates(array $templates);

    /**
     * Set a Template source by name.
     *
     * @param string $name
     * @param string $template lump Template source
     */
    public function setTemplate($name, $template);
}
