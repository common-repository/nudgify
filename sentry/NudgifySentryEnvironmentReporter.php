<?php

/**
 * This built-in extension reports OS and PHP run-time information.
 */
class NudgifySentryEnvironmentReporter implements NudgifySentryClientExtension
{
    /**
     * @var NudgifySentryRuntimeContext
     */
    protected $runtime;

    /**
     * @var NudgifySentryOSContext
     */
    protected $os;

    public function __construct()
    {
        $this->runtime = $this->createRuntimeContext();

        $this->os = $this->createOSContext();
    }

    /**
     * Applies transformations to the `$event`, and/or extracts additional information
     * from the `$exception` and `$request` instances and applies them to the `$event`.
     *
     * @param NudgifySentryEvent                       $event
     * @param Throwable                   $exception
     * @param ServerRequestInterface|null $request
     */
    public function apply($event, $exception, $request)
    {
        $event->addContext($this->os);

        $event->addContext($this->runtime);

        $event->addTag("server_name", php_uname('n'));
    }

    /**
     * Create run-time context information about this PHP installation.
     *
     * @return NudgifySentryRuntimeContext
     */
    protected function createRuntimeContext()
    {
        $name = "php";

        $raw_description = PHP_VERSION;

        preg_match("#^\d+(\.\d+){2}#", $raw_description, $version);

        return new NudgifySentryRuntimeContext($name, $version[0], $raw_description);
    }

    /**
     * Create the OS context information about this Operating System.
     *
     * @return NudgifySentryOSContext
     */
    protected function createOSContext()
    {
        $name = php_uname("s");
        $version = php_uname("v");
        $build = php_uname("r");

        return new NudgifySentryOSContext($name, $version, $build);
    }
}
