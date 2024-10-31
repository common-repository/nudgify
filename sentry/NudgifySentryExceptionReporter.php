<?php

/**
 * This extension reports details about Exceptions, including stack-traces and vars.
 */
class NudgifySentryExceptionReporter implements NudgifySentryClientExtension
{
    /**
     * @var string placeholder for unavailable file-names
     */
    const NO_FILE = "{no file}";

    /**
     * @var string|null root path (with trailing directory-separator)
     */
    protected $root_path;

    /**
     * @var int maximum length of formatted string-values
     */
    protected $max_string_length;

    /**
     * @var string[] file-name patterns to filter from stack-traces
     */
    protected $filters;

    /**
     * Severity of `ErrorException` mappings are identical to the official (2.0) client
     * by default - you can override the error-level mappings via this public property.
     *
     * @var string[] map where PHP error-level => Sentry Event error-level
     *
     * @link http://php.net/manual/en/errorfunc.constants.php
     *
     * @link https://docs.sentry.io/clientdev/attributes/#optional-attributes
     */
    public $error_levels = [
        E_DEPRECATED        => NudgifySentryLevel::WARNING,
        E_USER_DEPRECATED   => NudgifySentryLevel::WARNING,
        E_WARNING           => NudgifySentryLevel::WARNING,
        E_USER_WARNING      => NudgifySentryLevel::WARNING,
        E_RECOVERABLE_ERROR => NudgifySentryLevel::WARNING,
        E_ERROR             => NudgifySentryLevel::FATAL,
        E_PARSE             => NudgifySentryLevel::FATAL,
        E_CORE_ERROR        => NudgifySentryLevel::FATAL,
        E_CORE_WARNING      => NudgifySentryLevel::FATAL,
        E_COMPILE_ERROR     => NudgifySentryLevel::FATAL,
        E_COMPILE_WARNING   => NudgifySentryLevel::FATAL,
        E_USER_ERROR        => NudgifySentryLevel::ERROR,
        E_NOTICE            => NudgifySentryLevel::INFO,
        E_USER_NOTICE       => NudgifySentryLevel::INFO,
        E_STRICT            => NudgifySentryLevel::INFO,
    ];

    /**
     * The optional `$root_path`, if given, will be stripped from filenames.
     *
     * The optional `$filters` is an array of {@see \fnmatch()} patterns, which will be applied
     * to absolute paths of source-file references in stack-traces. You can use this to filter
     * scripts that define/bootstrap sensitive values like passwords and hostnames, so that
     * these lines will never show up in a stack-trace.
     *
     * @param string|null $root_path         absolute project root-path (e.g. Composer root path; optional)
     * @param int         $max_string_length PHP values longer than this will be truncated
     * @param string[]    $filters           Optional file-name patterns to filter from stack-traces
     */
    public function __construct($root_path = null, $max_string_length = 200, $filters = [])
    {
        $this->root_path = $root_path
            ? rtrim($root_path, "/\\") . "/"
            : null;

        $this->max_string_length = $max_string_length;

        $this->filters = $filters;
    }

    public function apply($event, $exception, $request)
    {
        if ($exception instanceof ErrorException) {
            $event->level = $this->error_levels[$exception->getSeverity()] ?: NudgifySentryLevel::ERROR;
        }

        $event->exception = $this->createExceptionList($exception);
    }

    /**
     * Creates an {@see ExceptionList} instance from a given {@see Throwable}.
     *
     * @param Throwable $exception
     *
     * @return NudgifySentryExceptionList
     */
    protected function createExceptionList($exception)
    {
        $items = [];

        while ($exception) {
            $items[] = $this->createExceptionInfo($exception);

            $exception = $exception->getPrevious();
        }

        return new NudgifySentryExceptionList(array_reverse($items));
    }

    /**
     * Creates an {@see ExceptionInfo} intsance from a given {@see Throwable}.
     *
     * @param Throwable $exception
     *
     * @return NudgifySentryExceptionInfo
     */
    protected function createExceptionInfo($exception)
    {
        $trace = $exception->getTrace();

        array_unshift(
            $trace,
            [
                "file" => $exception->getFile(),
                "line" => $exception->getLine(),
            ]
        );

        return new NudgifySentryExceptionInfo(
            get_class($exception),
            $exception->getMessage(),
            $this->createStackTrace($trace)
        );
    }

    /**
     * Creates a {@see StackTrace} instance from a given PHP stack-trace.
     *
     * @param array $trace PHP stack-trace
     *
     * @return NudgifySentryStackTrace
     */
    protected function createStackTrace($trace)
    {
        $frames = [];

        foreach ($trace as $index => $entry) {
            $frames[] = $this->createStackFrame($entry);
        }

        return new NudgifySentryStackTrace(array_reverse($frames));
    }

    /**
     * Creates a {@see StackFrame} instance from a given PHP stack-trace entry.
     *
     * @param array $entry PHP stack-trace entry
     *
     * @return NudgifySentryStackFrame
     */
    protected function createStackFrame($entry)
    {
        $filename = isset($entry["file"])
            ? $entry["file"]
            : self::NO_FILE;

        $function = isset($entry["class"])
            ? $entry["class"] . @$entry["type"] . @$entry["function"]
            : @$entry["function"];

        $lineno = array_key_exists("line", $entry)
            ? (int) $entry["line"]
            : 0;

        $frame = new NudgifySentryStackFrame($filename, $function, $lineno);

        if ($this->root_path && strpos($filename, $this->root_path) !== -1) {
            $frame->abs_path = $filename;
            $frame->filename = substr($filename, strlen($this->root_path));
        }

        if ($this->isFiltered($filename)) {
            $frame->context_line = "### FILTERED FILE ###";
        } else {
            if ($filename !== self::NO_FILE) {
                $this->loadContext($frame, $filename, $lineno, 5);
            }

            if (isset($entry['args'])) {
                $frame->vars = $this->extractVars($entry);
            }
        }

        return $frame;
    }

    /**
     * @param string $filename absolute path to source-file
     *
     * @return bool true, if the given filename matches a defined filter pattern
     *
     * @see $filters
     */
    protected function isFiltered($filename)
    {
        foreach ($this->filters as $pattern) {
            if (fnmatch($pattern, $filename)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Attempts to load lines of source-code "context" from a PHP script to a {@see StackFrame} instance.
     *
     * @param NudgifySentryStackFrame $frame     Sentry Client StackFrame to populate
     * @param string     $filename  path to PHP script
     * @param int        $lineno
     * @param int        $num_lines number of lines of context
     */
    protected function loadContext($frame, $filename, $lineno, $num_lines)
    {
        if (!is_file($filename) || !is_readable($filename)) {
            return;
        }

        $target = max(0, ($lineno - ($num_lines + 1)));

        $current_lineno = $target + 1;

        try {
            $file = new SplFileObject($filename);

            $file->seek($target);

            while (!$file->eof()) {
                $line = rtrim($file->current(), "\r\n");

                if ($current_lineno == $lineno) {
                    $frame->context_line = $line;
                } elseif ($current_lineno < $lineno) {
                    $frame->pre_context[] = $line;
                } elseif ($current_lineno > $lineno) {
                    $frame->post_context[] = $line;
                }

                $current_lineno += 1;

                if ($current_lineno > $lineno + $num_lines) {
                    break;
                }

                $file->next();
            }
        } catch (Exception $ex) {
            return;
        }
    }

    /**
     * Extracts a map of parameters names to human-readable values from a given stack-frame.
     *
     * @param array $entry PHP stack-frame entry
     *
     * @return string[] map where parameter name => human-readable value string
     */
    protected function extractVars(array $entry)
    {
        $reflection = $this->getReflection($entry);

        $names = $reflection ? $this->getParameterNames($reflection) : [];

        $vars = [];

        $values = $this->formatValues($entry['args']);

        foreach ($values as $index => $value) {
            $vars[isset($names[$index]) ? $names[$index] : "#" . ($index + 1)] = $value;
        }

        return $vars;
    }

    /**
     * Attempts to obtain a Function Reflection for a given stack-frame.
     *
     * @param array $entry PHP stack-frame entry
     *
     * @return ReflectionFunctionAbstract|null
     */
    protected function getReflection($entry)
    {
        try {
            if (isset($entry["class"])) {
                if (method_exists($entry["class"], $entry["function"])) {
                    return new ReflectionMethod($entry["class"], $entry["function"]);
                } elseif ("::" === $entry["type"]) {
                    return new ReflectionMethod($entry["class"], "__callStatic");
                } else {
                    return new ReflectionMethod($entry["class"], "__call");
                }
            } elseif (function_exists($entry["function"])) {
                return new ReflectionFunction($entry["function"]);
            }
        } catch (ReflectionException $exception) {
            return null;
        }

        return null;
    }

    /**
     * Creates a list of parameter-names for a given Function Reflection.
     *
     * @param ReflectionFunctionAbstract $reflection
     *
     * @return string[] list of parameter names
     */
    protected function getParameterNames($reflection)
    {
        $names = [];

        foreach ($reflection->getParameters() as $param) {
            $names[] = "$" . $param->getName();
        }

        return $names;
    }

    /**
     * Formats an array of raw PHP values as human-readable strings
     *
     * @param mixed[] $values raw PHP values
     *
     * @return string[] formatted values
     */
    protected function formatValues($values)
    {
        $formatted = [];

        foreach ($values as $value) {
            $formatted[] = $this->formatValue($value);
        }

        return $formatted;
    }

    /**
     * Formats any given PHP value as a human-readable string
     *
     * @param mixed $value
     *
     * @return string
     */
    protected function formatValue($value)
    {
        $type = is_array($value) && is_callable($value)
            ? "callable"
            : strtolower(gettype($value));

        switch ($type) {
            case "boolean":
                return $value ? "true" : "false";

            case "integer":
                return number_format($value, 0, "", "");

            case "double": // (for historical reasons "double" is returned in case of a float, and not simply "float")
                $formatted = sprintf("%.6g", $value);

                return $value == $formatted
                    ? "{$formatted}"
                    : "~{$formatted}";

            case "string":
                $string = strlen($value) > $this->max_string_length
                    ? substr($value, 0, $this->max_string_length) . "...[" . strlen($value) . "]"
                    : $value;

                return '"' . addslashes($string) . '"';

            case "array":
                return "array[" . count($value) . "]";

            case "object":
                if ($value instanceof Closure) {
                    $reflection = new ReflectionFunction($value);

                    return "{Closure in " . $reflection->getFileName() . "({$reflection->getStartLine()})}";
                }

                return "{" . ($value instanceof \stdClass ? "object" : get_class($value)) . "}";

            case "resource":
                return "{" . get_resource_type($value) . "}";

            case "resource (closed)":
                return "{unknown type}";

            case "callable":
                return is_object($value[0])
                    ? '{' . get_class($value[0]) . "}->{$value[1]}()"
                    : "{$value[0]}::{$value[1]}()";

            case "null":
                return "null";
        }

        return "{{$type}}"; // "unknown type" and possibly unsupported (future) types
    }
}
