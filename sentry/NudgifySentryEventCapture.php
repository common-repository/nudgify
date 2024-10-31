<?php

/**
 * This interface abstracts the capture of {@see NudgifySentryEvent} objects to Sentry.
 */
interface NudgifySentryEventCapture
{
    /**
     * Capture a given {@see NudgifySentryEvent} to Sentry.
     *
     * @param NudgifySentryEvent $event
     */
    public function captureEvent($event);
}
