<?php

namespace AlpineDigital\LogDashboard\controllers;

use AlpineDigital\LogDashboard\Plugin;
use AlpineDigital\LogDashboard\services\LocalLogReader;
use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use yii\web\Response;

class DashboardController extends Controller
{
    public function beforeAction($action): bool
    {
        // In embedded mode the pre-built SPA talks to the API with POST (its
        // "dynamic" project mode). These endpoints only read logs — they change
        // nothing and sit behind the permission check below — but Craft enforces
        // a CSRF token on any POST, and the framework-agnostic bundle never
        // sends one. Skip CSRF for them so the requests aren't rejected.
        if (in_array($action->id, ['logs', 'log-content'], true)) {
            $this->enableCsrfValidation = false;
        }

        if (! parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission(Plugin::PERMISSION);

        return true;
    }

    public function actionLogs(): Response
    {
        return $this->asJson((new LocalLogReader())->listLogFiles());
    }

    public function actionLogContent(): Response
    {
        // getParam() reads query string *and* POST body, so the same handler
        // serves both the GET (standalone) and POST (embedded "dynamic") shapes
        // the SPA uses.
        $request = Craft::$app->getRequest();
        $file = trim((string) $request->getParam('file', ''));

        if ($file === '') {
            return $this->asJson(['error' => 'Missing file parameter'])->setStatusCode(400);
        }

        $page = max(1, (int) $request->getParam('page', 1));
        $limit = max(1, min(500, (int) $request->getParam('limit', 100)));
        $level = strtoupper((string) $request->getParam('level', 'ALL'));
        $search = trim((string) $request->getParam('search', ''));

        try {
            return $this->asJson(
                (new LocalLogReader())->readLogContent($file, $page, $limit, $level, $search)
            );
        } catch (\Throwable $e) {
            return $this->asJson(['error' => $e->getMessage()])->setStatusCode(422);
        }
    }

    /**
     * Serve the built SPA: a real static file (JS/CSS/font) when the path maps
     * to one, otherwise the index shell for client-side routes.
     */
    public function actionServe(string $path = ''): Response
    {
        $dist = realpath($this->distPath());

        if ($dist === false) {
            return $this->rawResponse(
                'Log Dashboard UI is not built yet. Run "npm run build:package" in the frontend.',
                'text/plain',
                500
            );
        }

        if ($path !== '') {
            $full = realpath($dist.'/'.$path);

            if ($full !== false && str_starts_with($full, $dist) && is_file($full)) {
                return $this->fileResponse($full);
            }
        }

        return $this->spaShell($dist);
    }

    /**
     * Return the index shell with the SPA pointed at its control panel URL and
     * handed its embedded-mode runtime config.
     */
    private function spaShell(string $dist): Response
    {
        $index = $dist.'/index.html';

        if (! is_file($index)) {
            return $this->rawResponse('Log Dashboard UI is not built yet.', 'text/plain', 500);
        }

        // Build the SPA's mount path as a root-relative path (e.g.
        // "/admin/log-dashboard") from the control panel *root* URL plus this
        // plugin's handle. Deriving it from cpUrl() with no path — rather than
        // UrlHelper::cpUrl('log-dashboard') — sidesteps projects where a pathed
        // cpUrl() drops the trailing segment (which sent the SPA's assets and
        // API to "/admin/…" instead of "/admin/log-dashboard/…", leaving a
        // blank page). Root-relative keeps it origin- and trailing-slash-proof.
        $cpPath = rtrim((string) parse_url(UrlHelper::cpUrl(), PHP_URL_PATH), '/');
        $appPath = $cpPath.'/log-dashboard';

        $html = (string) file_get_contents($index);

        $base = '<base href="'.$appPath.'/">';
        $html = preg_replace('/<base href="[^"]*">/', $base, $html, 1, $replaced);
        if (! $replaced) {
            $html = str_replace('<head>', '<head>'.$base, $html);
        }

        $config = json_encode([
            'embedded' => true,
            'apiBase' => $appPath.'/api',
            'name' => Craft::$app->getSystemName(),
        ]);
        $html = str_replace('</head>', '<script>window.__LOG_DASHBOARD__='.$config.';</script></head>', $html);

        // Never cache the shell: its hashed asset references change on each
        // build, so a cached shell would keep loading stale JS.
        $response = $this->rawResponse($html, 'text/html');
        $response->getHeaders()->set('Cache-Control', 'no-store, must-revalidate');

        return $response;
    }

    private function fileResponse(string $full): Response
    {
        $mimes = [
            'js' => 'text/javascript',
            'css' => 'text/css',
            'html' => 'text/html',
            'json' => 'application/json',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'map' => 'application/json',
        ];
        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));

        return $this->rawResponse((string) file_get_contents($full), $mimes[$ext] ?? 'application/octet-stream');
    }

    private function rawResponse(string $body, string $contentType, int $statusCode = 200): Response
    {
        $response = $this->asRaw($body);
        $response->setStatusCode($statusCode);
        $response->getHeaders()->set('Content-Type', $contentType);

        return $response;
    }

    /**
     * The Angular application builder emits into a `browser/` subfolder.
     */
    private function distPath(): string
    {
        return __DIR__.'/../../resources/dist/browser';
    }
}
