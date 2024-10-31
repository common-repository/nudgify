<?php

/**
 * This PSR-3 Logger delegates log-entries to a {@see SentryClient} as Breadcrumbs.
 *
 * Note that long-running apps (if they reuse the client/extension for multiple requests)
 * needs to call the {@see clear()} method at the end of a successful web-request, since
 * log-entries will otherwise accumulate indefinitely.
 *
 * @see LoggerInterface
 */
class NudgifySentryBreadcrumbLogger implements NudgifySentryClientExtension
{
    /**
     * @var string[]
     */
    private $log_levels;

    /**
     * @var Breadcrumb[] list of Breadcrumbs being collected for the next Event
     */
    private $breadcrumbs = [];

    /**
     * @param string[] $log_levels map where PSR-3 LogLevel => Sentry Level (optional)
     *
     * @see LogLevel
     * @see Level
     */
    public function __construct() {
        $this->log_levels = [];
    }

    /**
     * @inheritdoc
     */
    public function log($level, $message, $context = [])
    {
        $this->breadcrumbs[] = new NudgifySentryBreadcrumb(
            $this->createTimestamp(),
            $level,
            "[{$level}] {$message}",
            $context
        );
    }

    /**
     * Clears any Breadcrumbs collected via {@see log()}.
     */
    public function clear()
    {
        $this->breadcrumbs = [];
    }

    /**
     * @internal this is called internally by the client at capture
     */
    public function apply($event, $exception, $request)
    {
        $event->breadcrumbs = array_merge(
            $event->breadcrumbs,
            $this->breadcrumbs
        );
    }

    /**
     * @return int current time
     */
    protected function createTimestamp()
    {
        return time();
    }
}
