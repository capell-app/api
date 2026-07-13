<?php

declare(strict_types=1);

namespace Capell\Api\Actions;

use Capell\Api\Data\PublicPagePayloadOptionsData;
use Capell\Api\Support\SanitizesPublicHtml;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Lorisleiva\Actions\Concerns\AsObject;

class BuildPublicLayoutPayloadAction
{
    use AsObject;
    use SanitizesPublicHtml;

    private const string BUILD_LAYOUT_GRAPH_ACTION = 'Capell\\LayoutBuilder\\Actions\\BuildPublicLayoutGraphAction';

    private const string LAYOUT_WIDGET_REGISTRY = 'Capell\\LayoutBuilder\\Support\\LayoutWidgets\\LayoutWidgetRegistry';

    private const string LAYOUT_WIDGET_TARGET = 'Capell\\LayoutBuilder\\Enums\\LayoutWidgetTarget';

    /**
     * @return array<string, mixed>
     */
    public function handle(Layout $layout, Page $page, Language $language, PublicPagePayloadOptionsData $options): array
    {
        $buildLayoutGraphAction = self::BUILD_LAYOUT_GRAPH_ACTION;

        if (! class_exists($buildLayoutGraphAction)) {
            return [];
        }

        $graph = $buildLayoutGraphAction::run(
            layout: $layout,
            page: $page,
            language: $language,
            containers: $options->layoutContainers(),
            includeHtml: $options->shouldIncludeLayoutHtml(),
        );

        return $this->layoutGraph($graph, $options);
    }

    /**
     * @return array<string, mixed>
     */
    private function layoutGraph(object $graph, PublicPagePayloadOptionsData $options): array
    {
        return [
            'key' => $this->stringProperty($graph, 'key'),
            'meta' => $this->sanitizeHtmlValue($this->arrayProperty($graph, 'meta')),
            'containers' => array_map(
                fn (object $container): array => $this->layoutContainer($container, $options),
                $this->objectArrayProperty($graph, 'containers'),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function layoutContainer(object $container, PublicPagePayloadOptionsData $options): array
    {
        return [
            'key' => $this->stringProperty($container, 'key'),
            'meta' => $this->sanitizeHtmlValue($this->arrayProperty($container, 'meta')),
            'widgets' => array_map(
                fn (object $widget): array => $this->layoutWidget($widget, $options),
                $this->objectArrayProperty($container, 'widgets'),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function layoutWidget(object $widget, PublicPagePayloadOptionsData $options): array
    {
        $type = $this->nullableStringProperty($widget, 'type');

        $data = [
            'key' => $this->stringProperty($widget, 'key'),
            'occurrence' => $this->intProperty($widget, 'occurrence'),
            'type' => $type,
            'data' => $this->sanitizeHtmlValue($this->arrayProperty($widget, 'data')),
        ];

        if ($options->includeWidgetComponents && $type !== null) {
            $component = $this->inertiaComponent($type);

            if (is_string($component)) {
                $data['component'] = $component;
            }
        }

        $rawHtml = $this->nullableStringProperty($widget, 'html');

        if ($rawHtml !== null) {
            $html = $this->sanitizeHtmlValue($rawHtml);

            if (is_string($html)) {
                $data['html'] = $html;
            }
        }

        return $data;
    }

    private function inertiaComponent(string $widgetType): ?string
    {
        $registryClass = self::LAYOUT_WIDGET_REGISTRY;
        $targetClass = self::LAYOUT_WIDGET_TARGET;

        if (! class_exists($registryClass) || ! enum_exists($targetClass) || ! app()->bound($registryClass)) {
            return null;
        }

        $target = constant($targetClass . '::FrontendInertia');
        $registry = app($registryClass);

        if (! is_object($registry) || ! method_exists($registry, 'get')) {
            return null;
        }

        $component = $registry->get($widgetType, $target);

        return is_string($component) ? $component : null;
    }

    private function stringProperty(object $object, string $property): string
    {
        $value = $object->{$property} ?? '';

        return is_string($value) ? $value : '';
    }

    private function nullableStringProperty(object $object, string $property): ?string
    {
        $value = $object->{$property} ?? null;

        return is_string($value) ? $value : null;
    }

    private function intProperty(object $object, string $property): int
    {
        $value = $object->{$property} ?? 0;

        return is_int($value) ? $value : 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayProperty(object $object, string $property): array
    {
        $value = $object->{$property} ?? [];

        return is_array($value) ? $value : [];
    }

    /**
     * @return array<int, object>
     */
    private function objectArrayProperty(object $object, string $property): array
    {
        $value = $object->{$property} ?? [];

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $item): bool => is_object($item)));
    }
}
