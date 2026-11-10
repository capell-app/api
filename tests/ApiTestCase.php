<?php

declare(strict_types=1);

namespace Capell\Api\Tests;

use Capell\Admin\Providers\AdminServiceProvider;
use Capell\Api\Providers\ApiServiceProvider;
use Capell\BlockLibrary\Providers\BlockLibraryServiceProvider;
use Capell\Core\Facades\CapellCore;
use Capell\Frontend\Providers\FrontendServiceProvider;
use Capell\LayoutBuilder\LayoutBuilderServiceProvider;
use Capell\Tests\AbstractTestCase;
use Illuminate\Foundation\Application;
use Laravel\Sanctum\SanctumServiceProvider;
use Livewire\LivewireServiceProvider;
use Override;

abstract class ApiTestCase extends AbstractTestCase
{
    #[Override]
    protected function getPackageServiceName(): string
    {
        return 'capell-api';
    }

    #[Override]
    protected function registersFoundationDiagnosticsFixture(): bool
    {
        return false;
    }

    #[Override]
    protected function setUpDatabase(): void
    {
        parent::setUpDatabase();
        $this->artisan('migrate', ['--path' => __DIR__ . '/../../layout-builder/database/migrations', '--realpath' => true])->run();
        $this->artisan('migrate', ['--path' => __DIR__ . '/../../../vendor/laravel/sanctum/database/migrations', '--realpath' => true])->run();
    }

    /**
     * @param  Application  $app
     * @return class-string[]
     */
    #[Override]
    protected function getPackageProviders(mixed $app): array
    {
        return [
            ...parent::getPackageProviders($app),
            LivewireServiceProvider::class,
            SanctumServiceProvider::class,
            AdminServiceProvider::class,
            FrontendServiceProvider::class,
            BlockLibraryServiceProvider::class,
            LayoutBuilderServiceProvider::class,
            ApiServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    #[Override]
    protected function getEnvironmentSetUp(mixed $app): void
    {
        parent::getEnvironmentSetUp($app);

        CapellCore::forcePackageInstalled(AdminServiceProvider::$packageName);
        CapellCore::forcePackageInstalled(FrontendServiceProvider::$packageName);
        CapellCore::forcePackageInstalled(BlockLibraryServiceProvider::$packageName);
        CapellCore::forcePackageInstalled(ApiServiceProvider::$packageName);
        CapellCore::forcePackageInstalled(LayoutBuilderServiceProvider::$packageName);
    }
}
