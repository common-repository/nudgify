<?php

/**
 * @link https://docs.sentry.io/clientdev/attributes/
 */
class NudgifySentryEvent implements JsonSerializable
{
    /**
     * @var string ISO 8601 date format (as required by Sentry)
     *
     * @see gmdate()
     */
    const DATE_FORMAT = "Y-m-d\TH:i:s";

    /**
     * @var string auto-generated UUID v4 (without dashes, as required by Sentry)
     */
    public $event_id;

    /**
     * @var string severity level of this Event
     *
     * @see Level
     */
    public $level = NudgifySentryLevel::ERROR;

    /**
     * @var int timestamp
     */
    public $timestamp;

    /**
     * @var string human-readable message
     */
    public $message;

    /**
     * The name of the transaction which caused this exception.
     *
     * For example, in a web app, this might be the route name: `/welcome`
     *
     * @var string|null
     */
    public $transaction;

    /**
     * @var string platform name
     */
    public $platform = "php";

    /**
     * @var string[] map where tag name => value
     */
    public $tags = [];

    /**
     * @var NudgifySentryExceptionList|null
     */
    public $exception;

    /**
     * @var Request|null
     */
    public $request;

    /**
     * @var NudgifySentryUserInfo
     */
    public $user;

    /**
     * @var string|null the name of the logger that captured this Event
     */
    public $logger;

    /**
     * @var string|null project release/version information (e.g. Git SHA, Composer version number, etc.)
     */
    public $release;

    /**
     * @var string|null project configuration/environment information (e.g. "production", "staging", etc.)
     */
    public $environment;

    /**
     * @var string[] map where module-name => version number (e.g. ["kodus/user" => "1.2.3"], etc.)
     */
    public $modules = [];

    /**
     * @var array map of arbitrary meta-data to store with the Event (e.g. ["some_key" => 1234], etc.)
     */
    public $extra = [];

    /**
     * @var NudgifySentryBreadcrumb[] breadcrumbs collected prior to the capture of this Event
     */
    public $breadcrumbs = [];

    /**
     * @var NudgifySentryContext[] map where Context Type => Context
     */
    protected $contexts = [];

    /**
     * @param string       $event_id
     * @param int          $timestamp
     * @param string       $message
     * @param NudgifySentryUserInfo     $user
     */
    public function __construct($event_id, $timestamp, $message, $user)
    {
        $this->event_id = $event_id;
        $this->timestamp = $timestamp;
        $this->message = $message;
        $this->user = $user;
    }

    /**
     * Add/replace a given {@see Context} instance.
     *
     * @param NudgifySentryContext $context
     */
    public function addContext($context)
    {
        $this->contexts[$context->getType()] = $context;
    }

    /**
     * Add/replace a given "tag" name/value pair
     *
     * @param string $name
     * @param string $value
     */
    public function addTag($name, $value)
    {
        $this->tags[$name] = $value;
    }

    /**
     * @internal
     */
    public function jsonSerialize()
    {
        $data = array_filter(get_object_vars($this));

        $data["timestamp"] = gmdate(self::DATE_FORMAT, $this->timestamp);

        if (isset($data["breadcrumbs"])) {
            $data["breadcrumbs"] = ["values" => $data["breadcrumbs"]];
        }

        return $data;
    }
}
