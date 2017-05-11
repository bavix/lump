<?php

namespace Bavix\Lump\Caches;

use Bavix\Lump\Exceptions;
use Bavix\Lump\Interfaces\CacheInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractCache implements CacheInterface
{

    /**
     * @var LoggerInterface
     */
    private $logger = null;

    /**
     * Get the current logger instance.
     *
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Set a logger instance.
     *
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function setLogger($logger = null)
    {
        if ($logger !== null && !($logger instanceof LoggerInterface))
        {
            throw new Exceptions\InvalidArgumentException('Expected an instance of Loggers or Psr\\Log\\LoggerInterface.');
        }

        $this->logger = $logger;
    }

    /**
     * Add a log record if logging is enabled.
     *
     * @param int    $level   The logging level
     * @param string $message The log message
     * @param array  $context The log context
     */
    protected function log($level, $message, array $context = array())
    {
        if (isset($this->logger))
        {
            $this->getLogger()->log($level, $message, $context);
        }
    }

}
