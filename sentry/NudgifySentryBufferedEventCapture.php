<?php

/**
 * This class implements buffered capture of {@see Event} instances to the
 * Sentry back-end via an HTTP request.
 *
 * Buffered events must be explicitly flushed at the end of the request -
 * for example, under FCGI, you could use `register_shutdown_function` and
 * `fastcgi_finish_request` to first flush the response, and then flush any
 * buffered events to Sentry, without blocking the user.
 */
class NudgifySentryBufferedEventCapture implements NudgifySentryEventCapture
{
    /**
     * @var NudgifySentryEvent[]
     */
    private $events = [];

    /**
     * @var NudgifySentryEventCapture|null
     */
    private $destination;

    /**
     * @param NudgifySentryEventCapture $destination the destination `EventCapture` implementation to `flush()` to
     */
    public function __construct($destination)
    {
        $this->destination = $destination;
    }

    public function captureEvent($event)
    {
        $this->events[] = $event;
    }

    /**
     * Flush all captured Events to the destination `EventCapture` implementation.
     */
    public function flush()
    {
        foreach ($this->events as $event) {
            $this->destination->captureEvent($event);
        }

        $this->events = [];
    }
}
