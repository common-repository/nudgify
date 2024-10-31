<?php

/**
 * This model represents a Sentry DSN string
 */
class NudgifySentryDSN
{
    /**
     * @var string Sentry authorization header-name
     *
     * @see getAuthHeader()
     */
    const AUTH_HEADER_NAME = "X-Sentry-Auth";

    /**
     * @var string Sentry API endpoint
     */
    private $url;

    /**
     * @var string X-Sentry authentication header template
     */
    private $auth_header;

    /**
     * @var string
     */
    private $dsn;

    /**
     * @param string $dsn Sentry DSN string
     */
    public function __construct($dsn)
    {
        $this->dsn = $dsn;

        $url = parse_url($dsn);
        
        if (empty($url['user'])) {
            $url['user'] = null;
        }
        if (empty($url['scheme'])) {
            $url['scheme'] = null;
        }
        if (empty($url['host'])) {
            $url['host'] = null;
        }
        if (empty($url['path'])) {
            $url['path'] = null;
        }

        $auth_header = implode(
            ", ",
            [
                "Sentry sentry_version=7",
                "sentry_timestamp=%s",
                "sentry_key={$url['user']}",
                "sentry_client=nudgify-wordpress",
            ]
        );

        $this->auth_header = $auth_header;

        $this->url = "{$url['scheme']}://{$url['host']}/api{$url['path']}/store/";
    }

    /**
     * @return string
     */
    public function getURL()
    {
        return $this->url;
    }

    /**
     * @return string authorization header-value
     *
     * @see DSN::AUTH_HEADER_NAME
     */
    public function getAuthHeader()
    {
        return sprintf($this->auth_header, $this->getTime());
    }

    /**
     * @return string
     */
    public function getDSN()
    {
        return $this->dsn;
    }

    /**
     * @internal
     *
     * @return float
     */
    protected function getTime()
    {
        return microtime(true);
    }
}
