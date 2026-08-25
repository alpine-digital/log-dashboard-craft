<?php

namespace AlpineDigital\LogDashboard;

use AlpineDigital\LogDashboard\models\Settings;
use Craft;
use craft\base\Model;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use yii\base\Event;

/**
 * @property-read Settings $settings
 */
class Plugin extends \craft\base\Plugin
{
    public const PERMISSION = 'accessLogDashboard';

    public function init(): void
    {
        parent::init();

        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function (RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => $this->name,
                    'permissions' => [
                        self::PERMISSION => ['label' => Craft::t('log-dashboard', 'Access the log dashboard')],
                    ],
                ];
            }
        );

        if (! $this->dashboardIsAvailable()) {
            return;
        }

        $this->hasCpSection = true;

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['log-dashboard/api/logs'] = 'log-dashboard/dashboard/logs';
                $event->rules['log-dashboard/api/log-content'] = 'log-dashboard/dashboard/log-content';
                $event->rules['log-dashboard'] = 'log-dashboard/dashboard/serve';
                $event->rules['log-dashboard/<path:.*>'] = 'log-dashboard/dashboard/serve';
            }
        );
    }

    /**
     * Hide the nav item entirely for users who lack the permission, rather
     * than showing it and 403ing on click.
     */
    public function getCpNavItem(): ?array
    {
        if (! Craft::$app->getUser()->checkPermission(self::PERMISSION)) {
            return null;
        }

        $item = parent::getCpNavItem();

        // Craft masks CP nav icons to a single color. The SVG ships next to this
        // plugin class, so __DIR__ resolves it regardless of where the package
        // is installed (vendor clone, path repo, etc.).
        $item['icon'] = __DIR__ . '/icon-mask.svg';

        return $item;
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    /**
     * The dashboard exposes log contents over the control panel, so it must
     * never mount when admin changes are disallowed — the standard Craft
     * signal that this environment is locked down (staging/production) —
     * regardless of the `enabled` setting.
     */
    private function dashboardIsAvailable(): bool
    {
        if (! Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            return false;
        }

        $settings = $this->getSettings();

        return $settings->enabled ?? Craft::$app->getConfig()->getGeneral()->devMode;
    }
}
