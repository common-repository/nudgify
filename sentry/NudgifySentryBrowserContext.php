<?php

/**
 * @link https://docs.sentry.io/clientdev/interfaces/contexts/
 */
class NudgifySentryBrowserContext implements NudgifySentryContext
{
    /**
     * @var string|null Display name of the browser application.
     */
    public $name;

    /**
     * @var string|null Version string of the browser.
     */
    public $version;

    /**
     * @param null|string $name
     * @param null|string $version
     */
    public function __construct($name,  $version)
    {
        $this->name = $name;
        $this->version = $version;
    }

    public function getType()
    {
        return "browser";
    }

    public function jsonSerialize()
    {
        return array_filter(get_object_vars($this));
    }
}
