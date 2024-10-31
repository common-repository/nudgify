<?php

/**
 * @link https://docs.sentry.io/clientdev/interfaces/exception/
 */
class NudgifySentryExceptionInfo implements JsonSerializable
{
    /**
     * @var string
     */
    public $type;

    /**
     * @var string
     */
    public $value;

    /**
     * @var NudgifySentryStackTrace
     */
    public $stacktrace;

    public function __construct($type, $value, $stacktrace)
    {
        $this->type = $type;
        $this->value = $value;
        $this->stacktrace = $stacktrace;
    }

    /**
     * @internal
     */
    public function jsonSerialize()
    {
        return array_filter(get_object_vars($this));
    }
}
