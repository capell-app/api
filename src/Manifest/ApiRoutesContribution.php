<?php

declare(strict_types=1);

namespace Capell\Api\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;
use Capell\Core\Contracts\Extensions\RegistersExtensionRoute;
use Override;

final class ApiRoutesContribution implements ExtensionContribution, RegistersExtensionRoute
{
    #[Override]
    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }
}
