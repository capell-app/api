<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('keeps committed non-2xx API JSON proof screenshots current', function (): void {
    $packagePath = dirname(__DIR__, 2);
    $repositoryPath = dirname(__DIR__, 4);

    $entries = [
        [
            'htmlPath' => $packagePath . '/docs/screenshots/.page-resolve-forbidden-json.html',
            'screenshotPath' => $packagePath . '/docs/screenshots/page-resolve-forbidden-json.png',
            'status' => 403,
            'html' => apiErrorScreenshotHtml(
                title: '403 Forbidden',
                status: 403,
                theme: 'light',
                json: apiErrorScreenshotForbiddenJson(),
            ),
        ],
        [
            'htmlPath' => $packagePath . '/docs/screenshots/.page-resolve-forbidden-json-dark.html',
            'screenshotPath' => $packagePath . '/docs/screenshots/page-resolve-forbidden-json-dark.png',
            'status' => 403,
            'html' => apiErrorScreenshotHtml(
                title: '403 Forbidden',
                status: 403,
                theme: 'dark',
                json: apiErrorScreenshotForbiddenJson(),
            ),
        ],
        [
            'htmlPath' => $packagePath . '/docs/screenshots/.page-resolve-not-found-json.html',
            'screenshotPath' => $packagePath . '/docs/screenshots/page-resolve-not-found-json.png',
            'status' => 404,
            'html' => apiErrorScreenshotHtml(
                title: '404 Not Found',
                status: 404,
                theme: 'light',
                json: apiErrorScreenshotNotFoundJson(),
            ),
        ],
        [
            'htmlPath' => $packagePath . '/docs/screenshots/.page-resolve-not-found-json-dark.html',
            'screenshotPath' => $packagePath . '/docs/screenshots/page-resolve-not-found-json-dark.png',
            'status' => 404,
            'html' => apiErrorScreenshotHtml(
                title: '404 Not Found',
                status: 404,
                theme: 'dark',
                json: apiErrorScreenshotNotFoundJson(),
            ),
        ],
    ];

    foreach ($entries as $entry) {
        expect($entry['html'])
            ->toContain('"status": ' . $entry['status'])
            ->not->toContain('authoring')
            ->not->toContain('signed editor')
            ->not->toContain('admin/')
            ->not->toContain('token');

        if (getenv('CAPELL_REFRESH_API_ERROR_SCREENSHOTS') === '1') {
            file_put_contents($entry['htmlPath'], $entry['html']);

            try {
                $process = new Process([
                    'node',
                    $repositoryPath . '/scripts/capture-static-html-screenshot.mjs',
                    $entry['htmlPath'],
                    $entry['screenshotPath'],
                    '1440',
                    '900',
                ], $repositoryPath);
                $process->setTimeout(60);
                $process->mustRun();
            } finally {
                if (is_file($entry['htmlPath'])) {
                    unlink($entry['htmlPath']);
                }
            }
        }

        expect($entry['screenshotPath'])->toBeFile();

        $dimensions = getimagesize($entry['screenshotPath']);

        expect($dimensions)->toBeArray()
            ->and($dimensions[0] ?? null)->toBe(1440)
            ->and($dimensions[1] ?? null)->toBe(900);
    }
});

/**
 * @return array<string, mixed>
 */
function apiErrorScreenshotForbiddenJson(): array
{
    return [
        'message' => 'Explicit language selection requires a signed context.',
        'status' => 403,
        'error' => 'forbidden_context',
        'route' => 'capell-api.v1.pages.resolve',
        'headers' => [
            'X-Capell-Api-Version' => 'v1',
            'Cache-Control' => 'no-store',
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function apiErrorScreenshotNotFoundJson(): array
{
    return [
        'message' => 'The requested published page could not be resolved.',
        'status' => 404,
        'error' => 'page_not_found',
        'route' => 'capell-api.v1.pages.resolve',
        'headers' => [
            'X-Capell-Api-Version' => 'v1',
            'Cache-Control' => 'no-store',
        ],
    ];
}

/**
 * @param  array<string, mixed>  $json
 */
function apiErrorScreenshotHtml(string $title, int $status, string $theme, array $json): string
{
    $encodedJson = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $isDark = $theme === 'dark';
    $background = $isDark ? '#0b1120' : '#f6f8fb';
    $panel = $isDark ? '#111827' : '#ffffff';
    $foreground = $isDark ? '#e5e7eb' : '#101827';
    $muted = $isDark ? '#9ca3af' : '#667085';
    $codeBackground = $isDark ? '#020617' : '#111827';

    return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>API {$title}</title>
    <style>
        body { margin: 0; background: {$background}; color: {$foreground}; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        main { box-sizing: border-box; min-height: 900px; padding: 64px; }
        .panel { max-width: 980px; border: 1px solid rgba(148, 163, 184, .28); border-radius: 14px; background: {$panel}; box-shadow: 0 22px 70px rgba(15, 23, 42, .18); overflow: hidden; }
        header { display: flex; justify-content: space-between; align-items: center; gap: 24px; padding: 26px 30px; border-bottom: 1px solid rgba(148, 163, 184, .22); }
        .eyebrow { margin: 0 0 8px; color: {$muted}; font-size: 12px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        h1 { margin: 0; font-size: 34px; line-height: 1.1; }
        .badge { border-radius: 999px; background: #fef3c7; color: #92400e; padding: 7px 11px; font-weight: 800; }
        .meta { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; padding: 20px 30px; }
        .meta div { border: 1px solid rgba(148, 163, 184, .22); border-radius: 10px; padding: 12px; }
        .label { display: block; color: {$muted}; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .value { display: block; margin-top: 5px; font-weight: 800; }
        pre { margin: 0; padding: 30px; background: {$codeBackground}; color: #d1fae5; font-size: 16px; line-height: 1.65; overflow: auto; }
    </style>
</head>
<body>
    <main>
        <section class="panel">
            <header>
                <div>
                    <p class="eyebrow">Capell API v1</p>
                    <h1>{$title} JSON response</h1>
                </div>
                <div class="badge">HTTP {$status}</div>
            </header>
            <div class="meta">
                <div><span class="label">Route</span><span class="value">capell-api.v1.pages.resolve</span></div>
                <div><span class="label">Surface</span><span class="value">Public JSON</span></div>
                <div><span class="label">Cache</span><span class="value">no-store</span></div>
            </div>
            <pre>{$encodedJson}</pre>
        </section>
    </main>
</body>
</html>
HTML;
}
