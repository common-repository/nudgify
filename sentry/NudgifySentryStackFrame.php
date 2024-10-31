<?php

/**
 * @link https://docs.sentry.io/clientdev/interfaces/exception/
 */
class NudgifySentryStackFrame implements JsonSerializable
{
    /**
     * @var string|null relative (to project root) path/filename
     */
    public $filename;

    /**
     * @var string|null absolute path/filename
     */
    public $abs_path;

    /**
     * @var string|null
     */
    public $function;

    /**
     * @var int|null
     */
    public $lineno;

    /**
     * @var string|null
     */
    public $context_line;

    /**
     * @var string[]
     */
    public $pre_context = [];

    /**
     * @var string[]
     */
    public $post_context = [];

    /**
     * @var string[] map where parameter-name => string representation of value
     */
    public $vars = [];

    public function __construct($filename, $function, $lineno)
    {
        $this->filename = $filename;
        $this->function = $function;
        $this->lineno = $lineno;
    }

    /**
     * @internal
     */
    public function jsonSerialize()
    {
        return array_filter(get_object_vars($this));
    }
}
