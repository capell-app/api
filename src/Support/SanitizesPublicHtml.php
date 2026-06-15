<?php

declare(strict_types=1);

namespace Capell\Api\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

trait SanitizesPublicHtml
{
    private const array BLOCKED_PUBLIC_KEYS = [
        'access_token',
        'admin_path',
        'admin_url',
        'api_key',
        'api_secret',
        'authorization',
        'bearer',
        'bearer_token',
        'client_secret',
        'credential',
        'credentials',
        'edit_url',
        'editable_regions',
        'expires',
        'field_path',
        'filament_url',
        'internal_id',
        'internal_model_id',
        'model_id',
        'model_type',
        'page_id',
        'password',
        'permission',
        'permissions',
        'private_key',
        'prompt',
        'record_key',
        'recordkey',
        'refresh_token',
        'secret',
        'secret_prompt',
        'selector',
        'signature',
        'signed_editor_url',
        'signed_url',
        'system_prompt',
        'token',
        'webhook_secret',
    ];

    private ?HtmlSanitizer $publicHtmlSanitizer = null;

    private function sanitizeHtmlValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->sanitizePublicString($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        $sanitized = [];

        foreach ($value as $key => $item) {
            if ($this->isBlockedPublicKey($key)) {
                continue;
            }

            $sanitizedItem = $this->sanitizeHtmlValue($item);

            if ($sanitizedItem !== null) {
                $sanitized[$key] = $sanitizedItem;
            }
        }

        return $sanitized;
    }

    private function sanitizePublicString(string $value): ?string
    {
        $sanitized = $this->sanitizeHtml($this->redactCredentialFragments($value));

        if ($this->containsBlockedPublicValue($sanitized)) {
            return null;
        }

        return $sanitized;
    }

    private function sanitizeHtml(string $html): string
    {
        return $this->publicHtmlSanitizer()->sanitize($html);
    }

    private function redactCredentialFragments(string $value): string
    {
        $redacted = (string) preg_replace(
            '/\bBearer\s+[A-Za-z0-9._~+\/=-]{8,}\b/',
            'Bearer [redacted]',
            $value,
        );

        $redacted = (string) preg_replace(
            '/\b([a-z][a-z0-9+.-]*:\/\/)([^:\s\/@]+):([^@\s\/]+)@/i',
            '$1$2:[redacted]@',
            $redacted,
        );

        return (string) preg_replace(
            '/([?&](?:expires|signature|token|access_token|refresh_token)=)[^&\s<>"\']+/i',
            '$1[redacted]',
            $redacted,
        );
    }

    private function isBlockedPublicKey(mixed $key): bool
    {
        if (! is_string($key)) {
            return false;
        }

        $normalizedKey = preg_replace('/[^a-z0-9]+/', '_', strtolower($key));

        if (! is_string($normalizedKey)) {
            return true;
        }

        return in_array(trim($normalizedKey, '_'), self::BLOCKED_PUBLIC_KEYS, true);
    }

    private function containsBlockedPublicValue(string $value): bool
    {
        $patterns = [
            '/\bdata-(?:capell-authoring|capell-editor|field-path|model-id|page-id)\b/i',
            '/\b(?:fieldPath|field[_-]?path|modelId|model[_-]?id|pageId|page[_-]?id)\b\s*(?:=|:)/i',
            '/\b(?:CapellFrontendAuthoring|frontend-authoring|signed-editor|signed_editor|editable_regions)\b/i',
            '~(?<![A-Za-z0-9_-])/(?:admin|authoring/regions|filament|filament-peek|livewire)(?:[/?#)"\'\s]|$)~i',
            '/\b(?:Authorization\s*[:=]\s*)?Bearer\s+(?!\[redacted\])[A-Za-z0-9._~+\/=-]{8,}\b/i',
            '/\b(?:secret|token|password|passwd|pwd|credential|private_key|api_key|access_key|client_secret|webhook_secret|signing_secret)\s*[:=]\s*(?!\[redacted\])[^"\'\s,;}{]+/i',
            '/[?&](?:expires|signature|token|access_token|refresh_token)=(?!\[redacted\])[^&\s<>"\']+/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value) === 1) {
                return true;
            }
        }

        return false;
    }

    private function publicHtmlSanitizer(): HtmlSanitizer
    {
        if ($this->publicHtmlSanitizer instanceof HtmlSanitizer) {
            return $this->publicHtmlSanitizer;
        }

        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->withMaxInputLength(-1);

        foreach (['class'] as $attribute) {
            $config = $config->allowAttribute($attribute, '*');
        }

        return $this->publicHtmlSanitizer = new HtmlSanitizer($config);
    }
}
