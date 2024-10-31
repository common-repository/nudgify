<?php

/**
 * @link https://docs.sentry.io/clientdev/interfaces/stacktrace/
 */
class NudgifySentryStackTrace implements JsonSerializable
{
    /**
     * @var NudgifySentryStackFrame[]
     */
    public $frames = [];

    /**
     * @var array|null tuple like [int $start_frame, int $end_frame]
     */
    public $frames_omitted;

    /**
     * @param NudgifySentryStackFrame[] $frames
     */
    public function __construct($frames)
    {
        $this->frames = $frames;
    }

    public function setFramesOmitted($start, $end)
    {
        $this->frames_omitted = [$start, $end];
    }

    /**
     * @internal
     */
    public function jsonSerialize()
    {
        return array_filter(get_object_vars($this));
    }
}
