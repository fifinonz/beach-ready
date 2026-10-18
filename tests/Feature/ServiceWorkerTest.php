<?php

use Illuminate\Support\Facades\File;

/*
| The service worker is filled in per build (decision 0008). These tests
| point Laravel at a throwaway public folder with a fake build in it.
*/

beforeEach(function () {
    $this->public = sys_get_temp_dir().'/beach-ready-public-'.uniqid();
    File::ensureDirectoryExists("{$this->public}/build");
    app()->usePublicPath($this->public);
});

afterEach(fn () => File::deleteDirectory($this->public));

function fakeBuild(string $public, string $appFile): void
{
    file_put_contents("{$public}/build/manifest.json", json_encode([
        'resources/js/app.js' => ['file' => "assets/{$appFile}", 'css' => ['assets/app-abc.css']],
        '_fira-sans-400.woff2' => ['file' => 'assets/fira-sans-400-xyz.woff2'],
    ]));
    file_put_contents("{$public}/build/fonts-manifest.json", json_encode(['style' => ['file' => 'assets/fonts-123.css']]));
}

function versionIn(string $script): string
{
    preg_match('/const VERSION = "([^"]+)";/', $script, $match);

    return $match[1];
}

it('is served as JavaScript that browsers must always check for updates', function () {
    fakeBuild($this->public, 'app-111.js');

    $this->get('/service-worker.js')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript; charset=utf-8')
        ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private');
});

it('lists every built file to keep for offline use', function () {
    fakeBuild($this->public, 'app-111.js');

    $script = $this->get('/service-worker.js')->getContent();

    expect($script)->toContain('const ASSETS = ["/build/assets/app-111.js","/build/assets/app-abc.css","/build/assets/fira-sans-400-xyz.woff2","/build/assets/fonts-123.css"];');
});

it('gets a new version with every build, so a deploy replaces the old caches', function () {
    fakeBuild($this->public, 'app-111.js');
    $before = versionIn($this->get('/service-worker.js')->getContent());

    fakeBuild($this->public, 'app-222.js');
    $after = versionIn($this->get('/service-worker.js')->getContent());

    expect($before)->not->toBe($after)
        ->and($after)->toMatch('/^[0-9a-f]{12}$/');
});

it('keeps the same version when nothing was rebuilt', function () {
    fakeBuild($this->public, 'app-111.js');

    expect(versionIn($this->get('/service-worker.js')->getContent()))
        ->toBe(versionIn($this->get('/service-worker.js')->getContent()));
});

it('still works before anything has been built', function () {
    $script = $this->get('/service-worker.js')->assertOk()->getContent();

    expect(versionIn($script))->toBe('dev')
        ->and($script)->toContain('const ASSETS = [];');
});

it('never caches the API, email links or Sanctum', function () {
    $script = $this->get('/service-worker.js')->getContent();

    expect($script)->toContain("const NEVER_CACHE = ['/api/', '/sanctum/', '/email/', '/up', '/service-worker.js'];");
});

it('is not mistaken for a screen of the Vue app', function () {
    $this->get('/service-worker.js')->assertDontSee('<div id="app"></div>', false);
});
