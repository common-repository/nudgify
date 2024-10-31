<?php

/**
 * This interface provides a minimum of composability for the client - you can
 * inject implementations of this interface to the {@see SentryClient} constructor,
 * and the client will apply these prior to capture of any {@see Event}.
 *
 * @see SentryClient::__construct()
 */
interface NudgifySentryClientExtension
{
    /**
     * Applies transformations to the `$event`, and/or extracts additional information
     * from the `$exception` and `$request` instances and applies them to the `$event`.
     *
     * @param NudgifySentryEvent                       $event
     * @param Throwable                   $exception
     * @param ServerRequestInterface|null $request
     */
    public function apply($event, $exception, $request);
}
