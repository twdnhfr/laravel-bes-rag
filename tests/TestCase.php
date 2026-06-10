<?php

namespace Twdnhfr\BesRag\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Twdnhfr\BesRag\BesRagServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Twdnhfr\\BesRag\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            AiServiceProvider::class,
            BesRagServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        // Routes on for feature tests; never hits the network — the LLM,
        // embedder and retriever are bound to fakes per test.
        config()->set('bes-rag.routes.enabled', true);
        config()->set('bes-rag.queue.connection', 'sync');

        // Fake credentials so provider resolution succeeds; tests never
        // actually call a provider.
        config()->set('ai.providers.anthropic.key', 'test-anthropic-key');
        config()->set('ai.providers.openai.key', 'test-openai-key');
    }

    protected function defineDatabaseMigrations(): void
    {
        $migration = include __DIR__.'/../database/migrations/create_bes_rag_tables.php.stub';
        $migration->up();
    }
}
