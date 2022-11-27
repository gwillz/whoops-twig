<?php
/**
 * Twig Whoops
 * @author Gwillz (github.com/gwillz)
 */

namespace Whoops\Twig;

use InvalidArgumentException;
use Twig\Environment;
use Whoops\Inspector\InspectorFactoryInterface;
use Whoops\Inspector\InspectorInterface;

/**
 * A factory for creating Twig trace inspectors. The inspector must be
 * configured with the respective Twig environment in order to determine the
 * cache path and build source contexts.
 *
 * @package Whoops\Twig
 */
class TwigInspectorFactory implements InspectorFactoryInterface
{
    /**
     * @var Environment
     */
    public $environment;

    /**
     * @var bool
     */
    public $clean;

    /**
     * @param Environment $environment
     * @param bool $clean
     * @return void
     * @throws InvalidArgumentException
     */
    public function __construct(Environment $environment, bool $clean = true)
    {
        $this->environment = $environment;
        $this->clean = $clean;

        // Check that we can actually use this inspector effectively.
        $cachePath = $environment->getCache(true);
        if (!is_string($cachePath)) {
            throw new InvalidArgumentException('Twig template cache must be a string path');
        }
    }

    /**
     * @param \Throwable $exception
     * @return InspectorInterface
     */
    public function create(\Throwable $exception): InspectorInterface
    {
        return new TwigInspector($exception, $this);
    }
}
