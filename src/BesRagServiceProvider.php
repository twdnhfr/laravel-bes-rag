<?php

namespace Twdnhfr\BesRag;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Twdnhfr\BesRag\Engine\EngineFactory;
use Twdnhfr\BesRag\Engine\RunRepository;

class BesRagServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('bes-rag')
            ->hasConfigFile()
            ->hasMigration('create_bes_rag_tables');
    }

    public function packageBooted(): void
    {
        // Routes are strictly opt-in; checked at boot time so runtime
        // config (and tests) can enable them.
        if (config('bes-rag.routes.enabled')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/bes-rag.php');
        }
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(RunRepository::class);

        $this->app->singleton(EngineFactory::class, fn ($app) => new EngineFactory($app));

        $this->app->singleton(BesRagManager::class, fn ($app) => new BesRagManager(
            $app->make(EngineFactory::class),
            $app->make(RunRepository::class),
        ));
    }
}
