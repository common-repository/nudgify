<?php

class NudgifySentryOSContext implements NudgifySentryContext
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
    public $build;

    public function __construct($name, $version, $build)
    {
        $this->name = $name;
        $this->version = $version;
        $this->build = $build;
    }

    public function getType()
    {
        return "os";
    }

    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}
