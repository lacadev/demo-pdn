<?php

namespace App\Module;


use WPEmerge\ServiceProviders\ServiceProviderInterface;

/**
 * Registers the ModuleLoader into the WPEmerge container.
 *
 * After registration, resolve modules via:
 *   $loader = \WPEmerge\Facade\App::make(ModuleLoader::class);
 *   $loader->register([MyFeature::class])->boot();
 *
 * Add to config.php 'providers' array:
 *   \App\Module\ModuleServiceProvider::class,
 */
class ModuleServiceProvider implements ServiceProviderInterface
{
    public function register( $container )
    {
        $container[ModuleLoader::class] = function ( $c ) {
            return new ModuleLoader($c);
        };
    }

    public function bootstrap( $container )
    {
        /** @var ModuleLoader $loader */
        $loader = $container[ModuleLoader::class];

        // Register all client modules. New features: implement ModuleInterface and add here.
        $loader->register([
            // \App\Features\ContactForm\ContactFormManager::class,
            // \App\Features\DynamicCPT\DynamicCptManager::class,
            // \App\Features\ClientTracker\Tracker::class,
        ]);

        // Boot after WP is fully loaded so modules can safely add_action/add_filter.
        add_action('after_setup_theme', [$loader, 'boot'], 20);
    }
}
