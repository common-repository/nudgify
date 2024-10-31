<?php

class NudgifySentryRuntimeContext implements NudgifySentryContext
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $version;

    /**
     * @var string
     */
    public $raw_description;

    public function __construct($name, $version, $raw_description)
    {
        $this->name = $name;
        $this->version = $version;
        $this->raw_description = $raw_description;
    }

    public function getType()
    {
        return "runtime";
    }

    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}
