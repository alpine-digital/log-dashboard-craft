<?php

namespace AlpineDigital\LogDashboard\services;

use AlpineDigital\LogDashboard\Plugin;

/**
 * Reads the host project's local .log files. No SSH, no remote transport —
 * the plugin runs inside the project, so the logs are on the local disk.
 */
class LocalLogReader
{
    public function basePath(): string
    {
        return rtrim(Plugin::getInstance()->getSettings()->path, '/\\');
    }

    /**
     * List the .log files in the configured directory, newest first.
     */
    public function listLogFiles(): array
    {
        $files = [];

        foreach (glob($this->basePath().'/*.log') ?: [] as $filePath) {
            $files[] = [
                'name' => basename($filePath),
                'size' => round(filesize($filePath) / 1024, 1).' KB',
                'modified' => date('d-m-Y H:i', filemtime($filePath)),
                '_mtime' => filemtime($filePath),
            ];
        }

        usort($files, fn ($a, $b) => $b['_mtime'] <=> $a['_mtime']);

        return array_map(function ($file) {
            unset($file['_mtime']);

            return $file;
        }, $files);
    }

    /**
     * Parse a single local log file into a page of entries.
     */
    public function readLogContent(string $fileName, int $page, int $limit, string $level, string $search): array
    {
        return LogParser::parseLogs($this->resolve($fileName), $limit, $page, $level, $search);
    }

    /**
     * Resolve a requested file name to a safe absolute path inside the base
     * directory. Basename + .log extension only — the path-traversal guard.
     */
    private function resolve(string $fileName): string
    {
        $base = basename($fileName);

        if (! str_ends_with($base, '.log')) {
            throw new \RuntimeException('Only .log files are allowed');
        }

        $path = $this->basePath().'/'.$base;

        if (! is_file($path)) {
            throw new \RuntimeException('Log file not found: '.$base);
        }

        return $path;
    }
}
