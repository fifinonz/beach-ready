<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Serves the service worker with the current build's version and files
 * filled in (decision 0008). A new build means a new version, which makes
 * browsers install the new worker and delete the old caches.
 */
class ServiceWorkerController extends Controller
{
    public function __invoke(): Response
    {
        $script = str_replace(
            ['__VERSION__', '__ASSETS__'],
            [json_encode($this->version()), json_encode($this->assets(), JSON_UNESCAPED_SLASHES)],
            file_get_contents(resource_path('service-worker/service-worker.js')),
        );

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            // Browsers must always check for a new worker, never reuse an old one.
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * A short hash of the build's manifests, so it changes with every build
     * that changes a file. "dev" when nothing has been built.
     */
    private function version(): string
    {
        $manifests = array_filter([$this->path('manifest.json'), $this->path('fonts-manifest.json')], 'is_file');

        if ($manifests === []) {
            return 'dev';
        }

        return substr(md5(implode('', array_map('md5_file', $manifests))), 0, 12);
    }

    /**
     * Every file the build produced, as URLs, for use offline.
     *
     * @return list<string>
     */
    private function assets(): array
    {
        $files = [];

        foreach ($this->read('manifest.json') as $chunk) {
            $files = [...$files, $chunk['file'], ...($chunk['css'] ?? []), ...($chunk['assets'] ?? [])];
        }

        if ($fontStyles = $this->read('fonts-manifest.json')['style']['file'] ?? null) {
            $files[] = $fontStyles;
        }

        return array_values(array_unique(array_map(fn (string $file) => "/build/{$file}", $files)));
    }

    /**
     * @return array<string, mixed>
     */
    private function read(string $manifest): array
    {
        $path = $this->path($manifest);

        return is_file($path) ? json_decode(file_get_contents($path), true) : [];
    }

    private function path(string $manifest): string
    {
        return public_path("build/{$manifest}");
    }
}
