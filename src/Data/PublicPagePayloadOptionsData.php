<?php

declare(strict_types=1);

namespace Capell\Api\Data;

use Illuminate\Http\Request;
use Spatie\LaravelData\Data;

class PublicPagePayloadOptionsData extends Data
{
    /**
     * @param  array<int, string>  $fields
     * @param  array<int, string>  $include
     * @param  array<int, string>  $containers
     */
    public function __construct(
        public array $fields = [],
        public array $include = [],
        public array $containers = [],
        public bool $includeWidgetComponents = false,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            fields: self::requestedList($request, 'fields'),
            include: self::requestedList($request, 'include'),
            containers: self::requestedList($request, 'containers'),
        );
    }

    public function shouldIncludeLayout(): bool
    {
        return in_array('layout', $this->include, true) || in_array('layout.html', $this->include, true);
    }

    public function shouldIncludeLayoutHtml(): bool
    {
        return in_array('layout.html', $this->include, true);
    }

    public function requestsUnboundedLayoutHtml(): bool
    {
        return $this->shouldIncludeLayoutHtml()
            && ($this->containers === [] || in_array('all', $this->containers, true));
    }

    /**
     * @return array<int, string>
     */
    public function layoutContainers(): array
    {
        if (in_array('all', $this->containers, true)) {
            return ['*'];
        }

        return $this->containers;
    }

    /**
     * @return array<int, string>
     */
    private static function requestedList(Request $request, string $key): array
    {
        $value = $request->query($key);

        if (! is_string($value)) {
            return [];
        }

        return collect(explode(',', $value))
            ->map(fn (string $item): string => trim($item))
            ->filter(fn (string $item): bool => $item !== '')
            ->unique()
            ->values()
            ->all();
    }
}
