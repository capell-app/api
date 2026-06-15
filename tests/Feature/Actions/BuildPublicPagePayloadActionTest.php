<?php

declare(strict_types=1);

use Capell\Api\Actions\BuildPublicPagePayloadAction;
use Capell\Api\Data\PublicPagePayloadOptionsData;
use Capell\Core\Data\PublicPageFieldsData;
use Capell\Core\Data\Widgets\WidgetDefinitionData;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Support\Widgets\WidgetRegistry;
use Capell\LayoutBuilder\Contracts\PublicWidgetPayloadResolver;
use Capell\LayoutBuilder\Enums\LayoutTypeEnum;
use Capell\LayoutBuilder\Models\Widget;

require_once dirname(__DIR__, 5) . '/tests/Packages/Support/PublicOutputSafety.php';

it('matches the public page api field payload and sanitizes html recursively', function (): void {
    $payload = BuildPublicPagePayloadAction::run(
        fields: new PublicPageFieldsData(
            url: '/services',
            title: 'Services',
            content: '<p onclick="alert(1)"><a href="javascript:alert(2)">Book</a></p><script>alert(3)</script>',
            meta: ['description' => '<span onmouseover="alert(4)">Private care</span><script>alert(5)</script>'],
        ),
        options: new PublicPagePayloadOptionsData(fields: ['url', 'title', 'content', 'meta']),
    );

    expect($payload)->toBe([
        'url' => '/services',
        'title' => 'Services',
        'content' => '<p><a>Book</a></p>',
        'meta' => ['description' => '<span>Private care</span>'],
    ]);
});

it('can include inertia widget component names without changing the default api payload', function (): void {
    $language = Language::factory()->english()->create();
    $site = Site::factory()->default()->create(['language_id' => $language->id]);
    $page = Page::factory()->site($site)->create();
    $widgetType = Blueprint::factory()
        ->type(LayoutTypeEnum::Widget->value)
        ->create(['key' => 'hero']);
    $widget = Widget::factory()->create([
        'key' => 'service-hero',
        'blueprint_id' => $widgetType->id,
    ]);
    $layout = Layout::factory()->site($site)->create([
        'key' => 'booking-page',
        'containers' => [
            'main' => [
                'widgets' => [
                    ['widget_key' => $widget->key, 'occurrence' => 1],
                ],
            ],
        ],
    ]);

    resolve(WidgetRegistry::class)->registerDefinition(WidgetDefinitionData::frontendInertia(
        key: 'hero',
        component: 'Theme/Widgets/Hero',
    ));

    app()->bind(PublicWidgetPayloadResolver::class, fn (): PublicWidgetPayloadResolver => new class implements PublicWidgetPayloadResolver
    {
        /**
         * @return array<string, mixed>
         */
        public function data(Widget $block, Page $page, Language $language, string $containerKey, int $occurrence): array
        {
            return [
                'headline' => '<strong onclick="alert(1)">Appointments</strong><script>alert(2)</script>',
            ];
        }

        public function html(Widget $block, Page $page, Language $language, string $containerKey, int $occurrence): string
        {
            return '<section>Blade fallback</section>';
        }
    });

    $defaultPayload = BuildPublicPagePayloadAction::run(
        fields: new PublicPageFieldsData(url: '/bookings', title: 'Bookings', content: ''),
        options: new PublicPagePayloadOptionsData(include: ['layout'], containers: ['main']),
        layout: $layout,
        page: $page,
        language: $language,
    );
    $inertiaPayload = BuildPublicPagePayloadAction::run(
        fields: new PublicPageFieldsData(url: '/bookings', title: 'Bookings', content: ''),
        options: new PublicPagePayloadOptionsData(include: ['layout'], containers: ['main'], includeWidgetComponents: true),
        layout: $layout,
        page: $page,
        language: $language,
    );

    $defaultWidget = apiTestFirstLayoutWidget($defaultPayload);
    $inertiaWidget = apiTestFirstLayoutWidget($inertiaPayload);
    $inertiaWidgetData = apiTestWidgetData($inertiaWidget);

    expect($defaultWidget)->not->toHaveKey('component')
        ->and($inertiaWidget['component'])->toBe('Theme/Widgets/Hero')
        ->and($inertiaWidgetData['headline'])->toBe('<strong>Appointments</strong>')
        ->and($inertiaWidget)->not->toHaveKey('html');
});

it('strips authoring metadata and secrets from public page and layout payloads', function (): void {
    $language = Language::factory()->english()->create();
    $site = Site::factory()->default()->create(['language_id' => $language->id]);
    $page = Page::factory()->site($site)->create();
    $widgetType = Blueprint::factory()
        ->type(LayoutTypeEnum::Widget->value)
        ->create(['key' => 'hero']);
    $widget = Widget::factory()->create([
        'key' => 'service-hero',
        'blueprint_id' => $widgetType->id,
    ]);
    $layout = Layout::factory()->site($site)->create([
        'key' => 'booking-page',
        'containers' => [
            'main' => [
                'widgets' => [
                    ['widget_key' => $widget->key, 'occurrence' => 1],
                ],
            ],
        ],
    ]);

    app()->bind(PublicWidgetPayloadResolver::class, fn (): PublicWidgetPayloadResolver => new class implements PublicWidgetPayloadResolver
    {
        /**
         * @return array<string, mixed>
         */
        public function data(Widget $block, Page $page, Language $language, string $containerKey, int $occurrence): array
        {
            return [
                'headline' => '<strong data-capell-authoring="true">Appointments</strong>',
                'admin_url' => '/admin/pages/99/edit?signature=secret-signature',
                'model_id' => 99,
                'prompt' => 'Rewrite with the private editor prompt.',
                'nested' => [
                    'copy' => '<p>Public widget copy</p>',
                    'authorization' => 'Bearer widget-token-secret',
                    'safe_link' => '/admin/widgets/99?signature=secret-signature',
                ],
            ];
        }

        public function html(Widget $block, Page $page, Language $language, string $containerKey, int $occurrence): string
        {
            return '<section data-capell-authoring="true"><a href="/admin/pages/99/edit?signature=secret-signature">Edit</a><p>Public fallback</p></section>';
        }
    });

    $payload = BuildPublicPagePayloadAction::run(
        fields: new PublicPageFieldsData(
            url: '/bookings',
            title: 'Bookings',
            content: '<p data-capell-authoring="true">Book online</p>',
            meta: [
                'description' => '<span data-model-id="99">Private care</span>',
                'admin_url' => '/admin/pages/99/edit?signature=secret-signature',
                'model_id' => 99,
                'prompt' => 'Summarise this with a private system prompt.',
                'nested' => [
                    'summary' => 'Safe public summary',
                    'bearer' => 'Authorization: Bearer meta-token-secret',
                    'signed_editor_url' => '/admin/pages/99/edit?signature=secret-signature',
                ],
            ],
        ),
        options: new PublicPagePayloadOptionsData(
            fields: ['url', 'title', 'content', 'meta'],
            include: ['layout.html'],
            containers: ['main'],
        ),
        layout: $layout,
        page: $page,
        language: $language,
    );

    $widgetPayload = apiTestFirstLayoutWidget($payload);
    $widgetData = apiTestWidgetData($widgetPayload);
    $serializedPayload = json_encode($payload, JSON_THROW_ON_ERROR);

    throw_unless(is_array($payload), RuntimeException::class, 'Expected API public page payload array.');

    $content = $payload['content'] ?? null;
    $meta = $payload['meta'] ?? null;

    expect($content)->toBe('<p>Book online</p>')
        ->and($meta)->toBe([
            'description' => '<span>Private care</span>',
            'nested' => ['summary' => 'Safe public summary'],
        ])
        ->and($widgetData)->toBe([
            'headline' => '<strong>Appointments</strong>',
            'nested' => ['copy' => '<p>Public widget copy</p>'],
        ])
        ->and($widgetPayload)->not->toHaveKey('html');

    expect($serializedPayload)
        ->not->toContain('data-capell-authoring')
        ->not->toContain('/admin/')
        ->not->toContain('model_id')
        ->not->toContain('private system prompt')
        ->not->toContain('meta-token-secret')
        ->not->toContain('widget-token-secret')
        ->not->toContain('secret-signature');

    assertCapellPublicOutputIsSafe($serializedPayload, 'Capell API action payload');
});

/**
 * @return array<mixed, mixed>
 */
function apiTestFirstLayoutWidget(mixed $payload): array
{
    if (! is_array($payload)) {
        throw new RuntimeException('Expected API payload to be an array.');
    }

    $layout = $payload['layout'] ?? null;

    if (! is_array($layout)) {
        throw new RuntimeException('Expected API payload to include a layout array.');
    }

    $containers = $layout['containers'] ?? null;

    if (! is_array($containers)) {
        throw new RuntimeException('Expected API layout payload to include containers.');
    }

    $firstContainer = $containers[0] ?? null;

    if (! is_array($firstContainer)) {
        throw new RuntimeException('Expected API layout payload to include a first container.');
    }

    $widgets = $firstContainer['widgets'] ?? null;

    if (! is_array($widgets)) {
        throw new RuntimeException('Expected API layout container to include widgets.');
    }

    $firstWidget = $widgets[0] ?? null;

    if (! is_array($firstWidget)) {
        throw new RuntimeException('Expected API layout container to include a first widget.');
    }

    return $firstWidget;
}

/**
 * @param  array<mixed, mixed>  $widget
 * @return array<mixed, mixed>
 */
function apiTestWidgetData(array $widget): array
{
    $data = $widget['data'] ?? null;

    if (! is_array($data)) {
        throw new RuntimeException('Expected API layout widget to include data.');
    }

    return $data;
}
