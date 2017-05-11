<?php

namespace Bavix\Lump\Loggers;

use Psr\Log\LogLevel;
use Bavix\Lump\Exceptions;

/**
 * A lump Stream Loggers.
 *
 * The Stream Loggers wraps a file resource instance (such as a stream) or a
 * stream URL. All log messages over the threshold level will be appended to
 * this stream.
 *
 * Hint: Try `php://stderr` for your stream URL.
 */
class StreamLogger extends AbstractLogger
{
    protected static $levels = array(
        LogLevel::DEBUG     => 100,
        LogLevel::INFO      => 200,
        LogLevel::NOTICE    => 250,
        LogLevel::WARNING   => 300,
        LogLevel::ERROR     => 400,
        LogLevel::CRITICAL  => 500,
        LogLevel::ALERT     => 550,
        LogLevel::EMERGENCY => 600,
    );

    protected $level;
    protected $stream = null;
    protected $url    = null;

    /**
     * @throws \InvalidArgumentException if the logging level is unknown
     *
     * @param resource|string $stream Resource instance or URL
     * @param int             $level  The minimum logging level at which this handler will be triggered
     */
    public function __construct($stream, $level = LogLevel::ERROR)
    {
        $this->setLevel($level);

        if (is_resource($stream))
        {
            $this->stream = $stream;
        }
        else
        {
            $this->url = $stream;
        }
    }

    /**
     * Close stream resources.
     */
    public function __destruct()
    {
        if (is_resource($this->stream))
        {
            fclose($this->stream);
        }
    }

    /**
     * Set the minimum logging level.
     *
     * @throws Exceptions\InvalidArgumentException if the logging level is unknown
     *
     * @param int $level The minimum logging level which will be written
     */
    public function setLevel($level)
    {
        if (!array_key_exists($level, self::$levels))
        {
            throw new Exceptions\InvalidArgumentException(sprintf('Unexpected logging level: %s', $level));
        }

        $this->level = $level;
    }

    /**
     * Get the current minimum logging level.
     *
     * @return int
     */
    public function getLevel()
    {
        return $this->level;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @throws Exceptions\InvalidArgumentException if the logging level is unknown
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     */
    public function log($level, $message, array $context = array())
    {
        if (!array_key_exists($level, self::$levels))
        {
            throw new Exceptions\InvalidArgumentException(sprintf('Unexpected logging level: %s', $level));
        }

        if (self::$levels[$level] >= self::$levels[$this->level])
        {
            $this->writeLog($level, $message, $context);
        }
    }

    /**
     * Write a record to the log.
     *
     * @throws Exceptions\LogicException   If neither a stream resource nor url is present
     * @throws Exceptions\RuntimeException If the stream url cannot be opened
     *
     * @param int    $level   The logging level
     * @param string $message The log message
     * @param array  $context The log context
     */
    protected function writeLog($level, $message, array $context = array())
    {
        if (!is_resource($this->stream))
        {
            if (!isset($this->url))
            {
                throw new Exceptions\LogicException('Missing stream url, the stream can not be opened. This may be caused by a premature call to close().');
            }

            $this->stream = fopen($this->url, 'ba');
        }

        if (!is_resource($this->stream))
        {
            // @codeCoverageIgnoreStart
            throw new Exceptions\RuntimeException(sprintf('The stream or file "%s" could not be opened.', $this->url));
            // @codeCoverageIgnoreEnd
        }

        fwrite($this->stream, self::formatLine($level, $message, $context));
    }

    /**
     * Gets the name of the logging level.
     *
     * @throws \InvalidArgumentException if the logging level is unknown
     *
     * @param int $level
     *
     * @return string
     */
    protected static function getLevelName($level)
    {
        return strtoupper($level);
    }

    /**
     * Format a log line for output.
     *
     * @param int    $level   The logging level
     * @param string $message The log message
     * @param array  $context The log context
     *
     * @return string
     */
    protected static function formatLine($level, $message, array $context = array())
    {
        return sprintf(
            "%s: %s\n",
            self::getLevelName($level),
            self::interpolateMessage($message, $context)
        );
    }

    /**
     * Interpolate context values into the message placeholders.
     *
     * @param string $message
     * @param array  $context
     *
     * @return string
     */
    protected static function interpolateMessage($message, array $context = array())
    {
        if (strpos($message, '{') === false)
        {
            return $message;
        }

        // build a replacement array with braces around the context keys
        $replace = array();
        foreach ($context as $key => $val)
        {
            $replace['{' . $key . '}'] = $val;
        }

        // interpolate replacement values into the the message and return
        return strtr($message, $replace);
    }
}
