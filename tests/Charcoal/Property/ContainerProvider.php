<?php

namespace Charcoal\Tests\Property;

use PDO;

// From PSR-3
use Psr\Log\NullLogger;

// From 'cache/void-adapter' (PSR-6)
use Cache\Adapter\Void\VoidCachePool;

// From Pimple
use Pimple\Container;

// From 'charcoal-factory'
use Charcoal\Factory\GenericFactory as Factory;

// From 'charcoal-core'
use Charcoal\Model\Service\MetadataLoader;
use Charcoal\Loader\CollectionLoader;
use Charcoal\Source\DatabaseSource;

// From 'charcoal-translator'
use Charcoal\Translator\LocalesManager;
use Charcoal\Translator\Translator;

/**
 * Service Container for Unit Tests
 */
class ContainerProvider
{
    /**
     * Register the unit tests required services.
     *
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerBaseServices(Container $container)
    {
        $this->registerConfig($container);
        $this->registerSource($container);
        $this->registerLogger($container);
        $this->registerCache($container);
    }

    /**
     * Setup the application configset.
     *
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerConfig(Container $container)
    {
        $container['config'] = [
            'base_path' => realpath(__DIR__.'/../../..')
        ];
    }

    /**
     * Setup the application's data source interface.
     *
     * Note: Uses SQLite to create a database in memory.
     *
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerSource(Container $container)
    {
        $container['database'] = function (Container $container) {
            $pdo = new PDO('sqlite::memory:');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        };
    }

    /**
     * Setup the application's logging interface.
     *
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerLogger(Container $container)
    {
        $container['logger'] = function (Container $container) {
            return new NullLogger();
        };
    }

    /**
     * Setup the application's caching interface.
     *
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerCache(Container $container)
    {
        $container['cache'] = function ($container) {
            return new VoidCachePool();
        };
    }

    /**
     * Setup the application's translator service.
     *
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerTranslator(Container $container)
    {
        $container['language/manager'] = function (Container $container) {
            $manager = new LocalesManager([
                'locales' => [
                    'en' => [ 'locale' => 'en-US' ]
                ]
            ]);

            $manager->setCurrentLocale($manager->currentLocale());

            return $manager;
        };

        $container['translator'] = function (Container $container) {
            return new Translator([
                'manager' => $container['language/manager']
            ]);
        };
    }

    /**
     * Setup the framework's metadata loader interface.
     *
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerMetadataLoader(Container $container)
    {
        $container['metadata/loader'] = function (Container $container) {
            return new MetadataLoader([
                'cache'     => $container['cache'],
                'logger'    => $container['logger'],
                'base_path' => realpath(__DIR__.'/../../../'),
                'paths'     => [
                    'metadata',
                    'vendor/locomotivemtl/charcoal-property/metadata'
                ]
            ]);
        };
    }

    /**
     * Setup the framework's data source factory.
     *
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerSourceFactory(Container $container)
    {
        $container['source/factory'] = function ($container) {
            return new Factory([
                'map' => [
                    'database' => DatabaseSource::class
                ],
                'arguments'  => [[
                    'logger' => $container['logger'],
                    'cache'  => $container['cache'],
                    'pdo'    => $container['database']
                ]]
            ]);
        };
    }

    /**
     * Setup the framework's model factory.
     *
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerModelFactory(Container $container)
    {
        $container['model/factory'] = function ($container) {
            return new Factory([
                'arguments' => [[
                    'container'         => $container,
                    'logger'            => $container['logger'],
                    'metadata_loader'   => $container['metadata/loader'],
                    'source_factory'    => $container['source/factory'],
                    'property_factory'  => $container['property/factory']
                ]]
            ]);
        };
    }

    /**
     * Setup the framework's property factory.
     *
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerPropertyFactory(Container $container)
    {
        $container['property/factory'] = function (Container $container) {
            return new Factory([
                'resolver_options' => [
                    'prefix' => '\\Charcoal\\Property\\',
                    'suffix' => 'Property'
                ],
                'arguments' => [[
                    'container' => $container,
                    'database'  => $container['database'],
                    'logger'    => $container['logger']
                ]]
            ]);
        };
    }

    /**
     * Setup the framework's collection loader interface.
     *
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerModelCollectionLoader(Container $container)
    {
        $container['model/collection/loader'] = function (Container $container) {
            return new CollectionLoader([
                'logger' => $container['logger'],
                'cache'  => $container['cache']
            ]);
        };
    }
}
