<?php
/**
 * Twig Whoops
 * @author Gwillz (github.com/gwillz)
 */

namespace Whoops\Twig;

use Twig\Environment;
use Whoops\Exception\Inspector;

/**
 * An inspector for twig stack traces.
 *
 * This relies heavily on the builtin cache of the Twig environment.
 * Twig contexts are extracted from the cached template files and the line
 * numbers are remapped from there.
 *
 * @property TwigInspectorFactory $inspectorFactory
 * @package Whoops\Twig
 */
class TwigInspector extends Inspector
{

    /**
     * @param \Throwable $exception The exception to inspect
     * @param TwigInspectorFactory $inspectorFactory
     */
    public function __construct($exception, TwigInspectorFactory $inspectorFactory)
    {
        parent::__construct($exception, $inspectorFactory);
    }


    /** @inheritdoc */
    protected function getTrace($error)
    {
        $frames = parent::getTrace($error);
        $this->processBacktrace($frames);
        return $frames;
    }


    /**
     * Fetch real trace information for a twig template.
     *
     * The result includes a mapped file path, line number, and source code.
     *
     * @param string $file Compiled template path
     * @param int|null $line Trace line number
     * @return array|null [ file, line, code ]
     */
    public function decodeErrorFrame(string $file, int $line = null)
    {
        $cachePath = $this->inspectorFactory->environment->getCache();

        if (!is_string($cachePath)) {
            return null;
        }

        try {
            // Must start with cache path.
            if (strpos($file, $cachePath) !== 0) {
                return null;
            }

            $contents = @file_get_contents($file);

            // Must exist.
            if (!$contents) {
                return null;
            }

            $matches = [];

            // Must have a class def, which we crudely extract.
            // Lucky us though - this doesn't need to be autoloaded.
            if (!preg_match('/^class (\w+)/m', $contents, $matches)) {
                return null;
            }

            [, $class] = $matches;

            /** @var Template $template */
            $template = new $class($this->inspectorFactory->environment);
            $source = $template->getSourceContext();

            // The template file path.
            $sourceFile = $source->getPath();

            // Re-map the line number.
            $sourceLine = null;

            if ($line !== null) {
                foreach ($template->getDebugInfo() as $codeLine => $templateLine) {
                    if ($codeLine <= $line) {
                        $sourceLine = $templateLine;
                        break;
                    }
                }
            }

            // Fetch a sample of the code.
            $sourceCode = null;

            if ($sourceLine !== null) {
                $lines = explode("\n", $source->getCode(), $sourceLine + 1);
                $sourceCode = $lines[$sourceLine - 1] ?? null;

                if ($sourceCode) {
                    $sourceCode = trim($sourceCode);
                }
            }

            return [$sourceFile, $sourceLine, $sourceCode];
        }
        catch (\Throwable $error) {
            // Shush.
        }

        return null;
    }


    /**
     * Process twig components of a backtrace.
     *
     * Backtrace renders should read the 'source' key to get the source code sample
     * for the current line. This should replace the class->method(args) component.
     *
     * @param array $trace
     * @return void
     */
    public function processBacktrace(&$trace)
    {
        $cachePath = $this->inspectorFactory->environment->getCache();
        $clean = $this->inspectorFactory->clean;

        // No point processing if we can't decode things.
        if (!is_string($cachePath)) {
            return null;
        }

        foreach ($trace as $key => &$frame) {

            // This separates the 'twig' from the 'php' stack frames. After this
            // point stop processing.
            if (
                isset($frame['class'])
                and isset($frame['function'])
                and $frame['class'] === Environment::class
                and $frame['function'] === 'render'
            ) {
                break;
            }

            // Remove frames without a file, these are often string templates.
            if (empty($frame['file'])) {
                if ($clean) unset($trace[$key]);
                continue;
            }

            $twig_frame = self::decodeErrorFrame($frame['file'], $frame['line'] ?? null);
            if (!$twig_frame) {
                if ($clean) unset($trace[$key]);
                continue;
            }

            [$file, $line, $code] = $twig_frame;

            $frame['file'] = $file;
            $frame['line'] = $line;

            // Replace the function call with the twig source code.
            if ($code) {
                $frame['function'] = $code;
                unset($frame['class']);
                unset($frame['type']);
                unset($frame['args']);
                unset($frame['object']);

            }
        }
        unset($frame);
    }
}
