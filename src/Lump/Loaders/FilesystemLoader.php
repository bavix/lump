<?php

namespace Bavix\Lump\Loaders;

use Bavix\Lump\Interfaces\LoaderInterface;
use Bavix\Lump\Exceptions;

/**
 * lump Template filesystem Loaders implementation.
 *
 * A FilesystemLoader instance loads lump Template source from the filesystem by name:
 *
 *     $loader = new Loader_FilesystemLoader(dirname(__FILE__).'/views');
 *     $tpl = $loader->load('foo'); // equivalent to `file_get_contents(dirname(__FILE__).'/views/foo.lump');
 *
 * This is probably the most useful lump Loaders implementation. It can be used for partials and normal Templates:
 *
 *     $m = new lump(array(
 *          'loader'          => new Loader_FilesystemLoader(dirname(__FILE__).'/views'),
 *          'partials_loader' => new Loader_FilesystemLoader(dirname(__FILE__).'/views/partials'),
 *     ));
 */
class FilesystemLoader implements LoaderInterface
{
    private $baseDir;
    private $extension = '.lump';
    private $templates = array();

    /**
     * lump filesystem Loaders constructor.
     *
     * Passing an $options array allows overriding certain Loaders options during instantiation:
     *
     *     $options = array(
     *         // The filename extension used for lump templates. Defaults to '.lump'
     *         'extension' => '.ms',
     *     );
     *
     * @throws Exceptions\RuntimeException if $baseDir does not exist
     *
     * @param string $baseDir Base directory containing lump template files
     * @param array  $options Array of Loaders options (default: array())
     */
    public function __construct($baseDir, array $options = array())
    {
        $this->baseDir = $baseDir;

        if (strpos($this->baseDir, '://') === false)
        {
            $this->baseDir = realpath($this->baseDir);
        }

        if ($this->shouldCheckPath() && !is_dir($this->baseDir))
        {
            throw new Exceptions\RuntimeException(sprintf('FilesystemLoader baseDir must be a directory: %s', $baseDir));
        }

        if (array_key_exists('extension', $options))
        {
            if (empty($options['extension']))
            {
                $this->extension = '';
            }
            else
            {
                $this->extension = '.' . ltrim($options['extension'], '.');
            }
        }
    }

    /**
     * Load a Template by name.
     *
     *     $loader = new Loader_FilesystemLoader(dirname(__FILE__).'/views');
     *     $loader->load('admin/dashboard'); // loads "./views/admin/dashboard.lump";
     *
     * @param string $name
     *
     * @return string lump Template source
     */
    public function load($name)
    {
        if (!isset($this->templates[$name]))
        {
            $this->templates[$name] = $this->loadFile($name);
        }

        return $this->templates[$name];
    }

    /**
     * Helper function for loading a lump file by name.
     *
     * @throws Exceptions\UnknownTemplateException If a template file is not found
     *
     * @param string $name
     *
     * @return string lump Template source
     */
    protected function loadFile($name)
    {
        $fileName = $this->getFileName($name);

        if ($this->shouldCheckPath() && !file_exists($fileName))
        {
            throw new Exceptions\UnknownTemplateException($name);
        }

        return file_get_contents($fileName);
    }

    /**
     * Helper function for getting a lump template file name.
     *
     * @param string $name
     *
     * @return string Template file name
     */
    protected function getFileName($name)
    {
        $fileName = $this->baseDir . '/' . $name;
        if (substr($fileName, 0 - strlen($this->extension)) !== $this->extension)
        {
            $fileName .= $this->extension;
        }

        return $fileName;
    }

    /**
     * Only check if baseDir is a directory and requested templates are files if
     * baseDir is using the filesystem stream wrapper.
     *
     * @return bool Whether to check `is_dir` and `file_exists`
     */
    protected function shouldCheckPath()
    {
        return strpos($this->baseDir, '://') === false || strpos($this->baseDir, 'file://') === 0;
    }
}
