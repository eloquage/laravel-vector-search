<?php

namespace Eloquage\LaravelVectorSearch\Tests;

use Eloquage\LaravelVectorSearch\LaravelVectorSearchServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName
            ) => 'Eloquage\\LaravelVectorSearch\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelVectorSearchServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('vector-search.default_embedding_provider', 'null');

        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $migration = include dirname(__DIR__).'/database/migrations/create_vector_indexes_table.php.stub';
        $migration->up();

        $migration = include dirname(__DIR__).'/database/migrations/create_vector_documents_table.php.stub';
        $migration->up();

        $migration = include dirname(__DIR__).'/database/migrations/create_vector_chunks_table.php.stub';
        $migration->up();
    }
}
