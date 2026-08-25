<?php

use craft\helpers\App;

return [
    /*
     * The dashboard only mounts its control panel section and routes when
     * enabled. Left `null`, it follows Craft's own `devMode` general config
     * setting. The plugin additionally hard-blocks whenever `allowAdminChanges`
     * is disabled, so it can never be exposed on a locked-down environment.
     */
    'enabled' => App::parseBooleanEnv(App::env('LOG_DASHBOARD_ENABLED')),

    /*
     * Directory the .log files are read from. Defaults to Craft's own
     * storage/logs directory when left blank.
     */
    'path' => (string) (App::env('LOG_DASHBOARD_PATH') ?? ''),
];
