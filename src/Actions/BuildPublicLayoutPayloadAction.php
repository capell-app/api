<?php

declare(strict_types=1);

namespace Capell\Api\Actions;

use Capell\Api\Data\PublicPagePayloadOptionsData;
use Capell\Api\Support\SanitizesPublicHtml;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\LayoutBuilder\Actions\BuildPublicLayoutGraphAction;
use Capell\LayoutBuilder\Data\PublicLayoutContainerData;
use Capell\LayoutBuilder\Data\PublicLayoutGraphData;
use Capell\LayoutBuilder\Data\PublicLayoutWidgetData;
use Capell\LayoutBuilder\Enums\LayoutWidgetTarget;
use Capell\LayoutBuilder\Support\LayoutWidgets\LayoutWidgetRegistry;
use Lorisleiva\Actions\Concerns\AsObject;

class BuildPublicLayoutPayloadAction
{
    use AsObject;
    use SanitizesPublicHtml;

    public function __construct(
        private readonly LayoutWidgetRegistry $widgetRegistry,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Layout $layout, Page $page, Language $language, PublicPagePayloadOptionsData $options): array
    {
        $graph = BuildPublicLayoutGraphAction::run(
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
    private function layoutGraph(PublicLayoutGraphData $graph, PublicPagePayloadOptionsData $options): array
    {
        return [
            'key' => $graph->key,
            'meta' => $this->sanitizeHtmlValue($graph->meta),
            'containers' => array_map(
                fn (PublicLayoutContainerData $container): array => $this->layoutContainer($container, $options),
                $graph->containers,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function layoutContainer(PublicLayoutContainerData $container, PublicPagePayloadOptionsData $options): array
    {
        return [
            'key' => $container->key,
            'meta' => $this->sanitizeHtmlValue($container->meta),
            'layout_widgets' => array_map(
                fn (PublicLayoutWidgetData $widget): array => $this->layoutWidget($widget, $options),
                $container->widgets,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function layoutWidget(PublicLayoutWidgetData $widget, PublicPagePayloadOptionsData $options): array
    {
        $data = [
            'key' => $widget->key,
            'occurrence' => $widget->occurrence,
            'type' => $widget->type,
            'data' => $this->sanitizeHtmlValue($widget->data),
        ];

        if ($options->includeWidgetComponents && is_string($widget->type)) {
            $component = $this->widgetRegistry->get($widget->type, LayoutWidgetTarget::FrontendInertia);

            if (is_string($component)) {
                $data['component'] = $component;
            }
        }

        if ($widget->html !== null) {
            $html = $this->sanitizeHtmlValue($widget->html);

            if (is_string($html)) {
                $data['html'] = $html;
            }
        }

        return $data;
    }
}
