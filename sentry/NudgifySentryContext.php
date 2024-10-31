<?php

/**
 * @link https://docs.sentry.io/clientdev/interfaces/contexts/
 */
interface NudgifySentryContext extends JsonSerializable
{
    public function getType();
}
