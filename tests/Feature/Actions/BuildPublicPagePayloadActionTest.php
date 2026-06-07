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

    expect($defaultPayload['layout']['containers'][0]['widgets'][0])->not->toHaveKey('component')
        ->and($inertiaPayload['layout']['containers'][0]['widgets'][0]['component'])->toBe('Theme/Widgets/Hero')
        ->and($inertiaPayload['layout']['containers'][0]['widgets'][0]['data']['headline'])->toBe('<strong>Appointments</strong>')
        ->and($inertiaPayload['layout']['containers'][0]['widgets'][0])->not->toHaveKey('html');
});
