<?php

/**
 * @link https://docs.sentry.io/clientdev/interfaces/exception/
 */
class NudgifySentryExceptionList
{
    /**
     * @var NudgifySentryExceptionInfo[]
     */
    public $values = [];

    /**
     * @param NudgifySentryExceptionInfo[] $values
     */
    public function __construct($values)
    {
        $this->values = $values;
    }
}
