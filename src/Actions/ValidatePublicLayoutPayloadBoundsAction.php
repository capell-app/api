<?php

declare(strict_types=1);

namespace Capell\Api\Actions;

use Capell\Api\Data\PublicPagePayloadOptionsData;
use Capell\Core\Models\Layout;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class ValidatePublicLayoutPayloadBoundsAction
{
    use AsFake;
    use AsObject;

    public function handle(Layout $layout, PublicPagePayloadOptionsData $options): ?string
    {
        $containers = is_array($layout->containers) ? $layout->containers : [];
        $requestedContainers = $options->layoutContainers();
        $configuredMaxContainers = config('capell-api.public_pages.max_layout_containers', 12);
        $configuredMaxWidgets = config('capell-api.public_pages.max_widgets_per_container', 50);
        $maxContainers = max(1, is_int($configuredMaxContainers) ? $configuredMaxContainers : 12);
        $maxWidgets = max(1, is_int($configuredMaxWidgets) ? $configuredMaxWidgets : 50);

        if ($requestedContainers !== [] && $requestedContainers !== ['*']) {
            if (count($requestedContainers) > $maxContainers) {
                return 'Requested layout container count exceeds the API limit.';
            }

            $containers = array_intersect_key($containers, array_flip($requestedContainers));
        }

        if (count($containers) > $maxContainers) {
            return 'Layout container count exceeds the API limit; request explicit containers.';
        }

        foreach ($containers as $container) {
            $widgets = is_array($container) ? ($container['widgets'] ?? []) : [];

            if (is_array($widgets) && count($widgets) > $maxWidgets) {
                return 'Layout widget count exceeds the API limit.';
            }
        }

        return null;
    }
}
