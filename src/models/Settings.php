<?php

namespace AlpineDigital\LogDashboard\models;

use Craft;
use craft\base\Model;

class Settings extends Model
{
    /**
     * Whether the dashboard's control panel section and routes should mount.
     * Left null to fall back to Craft's `devMode` general config setting.
     */
    public ?bool $enabled = null;

    /**
     * Directory the .log files are read from. Left blank to fall back to
     * Craft's own `storage/logs` directory.
     */
    public string $path = '';

    public function init(): void
    {
        parent::init();

        if ($this->path === '') {
            $this->path = Craft::$app->getPath()->getLogPath();
        }
    }
}
