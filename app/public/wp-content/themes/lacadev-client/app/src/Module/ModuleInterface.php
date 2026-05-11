<?php

namespace App\Module;

/**
 * Contract for all theme modules.
 */
interface ModuleInterface
{
    /**
     * Register WordPress hooks and bootstrap the module.
     */
    public function boot(): void;
}
