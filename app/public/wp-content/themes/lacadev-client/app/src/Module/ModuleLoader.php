<?php

namespace App\Module;

/**
 * Resolves and boots registered modules via the service container.
 */
class ModuleLoader
{
    /** @var class-string<ModuleInterface>[] */
    private array $modules = [];

    /** @var ModuleInterface[] Resolved instances, keyed by class */
    private array $resolved = [];

    public function __construct(private readonly mixed $container)
    {
    }

    /**
     * Register module class names to be booted.
     *
     * @param class-string<ModuleInterface>[] $modules
     */
    public function register(array $modules): static
    {
        foreach ($modules as $class) {
            if (!in_array($class, $this->modules, true)) {
                $this->modules[] = $class;
            }
        }
        return $this;
    }

    /**
     * Resolve all registered modules from the container and call boot().
     */
    public function boot(): void
    {
        foreach ($this->modules as $class) {
            if (isset($this->resolved[$class])) {
                continue;
            }

            /** @var ModuleInterface $module */
            $module = $this->resolve($class);
            $module->boot();
            $this->resolved[$class] = $module;
        }
    }

    /**
     * Resolve a module instance from the container or instantiate it.
     */
    private function resolve(string $class): ModuleInterface
    {
        if (is_object($this->container) && method_exists($this->container, 'make')) {
            return $this->container->make($class);
        }

        if (isset($this->container[$class])) {
            return $this->container[$class];
        }

        if (class_exists($class)) {
            return new $class($this->container);
        }

        throw new \RuntimeException("Unable to resolve module: {$class}");
    }

    /**
     * Retrieve an already-booted module instance.
     *
     * @template T of ModuleInterface
     * @param class-string<T> $class
     * @return T|null
     */
    public function get(string $class): ?ModuleInterface
    {
        return $this->resolved[$class] ?? null;
    }
}
