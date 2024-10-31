<?php

/**
 * This extension reports details about the (PSR-7) HTTP Request.
 */
class NudgifySentryRequestReporter implements NudgifySentryClientExtension
{
    public function apply($event, $exception, $request)
    {
        $event->addTag("site", empty($_SERVER['HTTP_HOST']) ? '' : $_SERVER['HTTP_HOST']);

        $url = implode('', [
            empty($_SERVER['REQUEST_SCHEME']) ? 'http://' : "{$_SERVER['REQUEST_SCHEME']}://",
            empty($_SERVER['HTTP_HOST']) ? '' : $_SERVER['HTTP_HOST'],
            empty($_SERVER['REQUEST_URI']) ? '/' : $_SERVER['REQUEST_URI']
        ]);

        $event->request = new NudgifySentryRequest($url, $_SERVER['REQUEST_METHOD']);

        $event->request->query_string = $_SERVER['QUERY_STRING'];

        $event->request->cookies = $_COOKIE;

        $headers = [];

        foreach ($_SERVER as $name => $value) {
            $headers[$name] = $value;
        }

        $event->request->headers = $headers;
    }
}
