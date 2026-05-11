<?php
namespace App\Performance;

/**
 * ScriptGovernor — route-aware asset dequeuer.
 *
 * Removes scripts/styles that are globally enqueued by plugins/themes
 * but are only needed on specific page types.  Runs at late priority on
 * `wp_enqueue_scripts` so it always fires after plugins have registered
 * their assets.
 *
 * Usage:
 *   $governor = new ScriptGovernor();
 *   $governor
 *       ->dequeueScript('contact-form-7', fn() => !is_page_template('contact.php'))
 *       ->dequeueStyle('woocommerce-general', fn() => !is_woocommerce())
 *       ->boot();
 *
 * @package App\Performance
 */
class ScriptGovernor
{
    /**
     * @var array<array{handle: string, type: 'script'|'style', on: callable}>
     */
    private array $rules = [];

    // ─────────────────────────────────────────────────────────────────────
    // Rule registration
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Register a dequeue rule.
     *
     * @param string   $handle The registered script/style handle to target.
     * @param string   $type   'script' or 'style'.
     * @param callable $on     Returns true when the asset SHOULD be removed.
     */
    public function dequeue(string $handle, string $type, callable $on): static
    {
        $this->rules[] = ['handle' => $handle, 'type' => $type, 'on' => $on];
        return $this;
    }

    /** Shorthand for script rules. */
    public function dequeueScript(string $handle, callable $on): static
    {
        return $this->dequeue($handle, 'script', $on);
    }

    /** Shorthand for style rules. */
    public function dequeueStyle(string $handle, callable $on): static
    {
        return $this->dequeue($handle, 'style', $on);
    }

    /**
     * Add multiple rules at once.
     *
     * @param array<array{handle: string, type: string, on: callable}> $rules
     */
    public function addRules(array $rules): static
    {
        foreach ($rules as $rule) {
            $this->dequeue($rule['handle'], $rule['type'], $rule['on']);
        }
        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Evaluation
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Evaluate all registered rules and dequeue matching assets.
     * Hooked into `wp_enqueue_scripts` at the configured priority.
     */
    public function apply(): void
    {
        foreach ($this->rules as ['handle' => $handle, 'type' => $type, 'on' => $on]) {
            if (!$on()) {
                continue;
            }

            if ($type === 'style') {
                wp_dequeue_style($handle);
                wp_deregister_style($handle);
            } else {
                wp_dequeue_script($handle);
                wp_deregister_script($handle);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Lifecycle
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Attach to WordPress hooks.
     *
     * @param int $priority Hook priority — high value = runs after plugins.
     */
    public function boot(int $priority = 999): void
    {
        add_action('wp_enqueue_scripts', [$this, 'apply'], $priority);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Static factory helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Check whether the current request is a WooCommerce page.
     * Safe to call even if WooCommerce is not active.
     */
    public static function isWooPage(): bool
    {
        return function_exists('is_woocommerce')
            && (is_woocommerce() || is_cart() || is_checkout() || is_account_page());
    }

    /**
     * Returns true on every page EXCEPT the given page slugs/IDs.
     *
     * @param array<int|string> $pages
     */
    public static function notOnPages(array $pages): callable
    {
        return function () use ($pages): bool {
            return !is_page($pages);
        };
    }

    /**
     * Returns true on every page EXCEPT singular posts of the given post types.
     *
     * @param array<string> $postTypes
     */
    public static function notOnPostTypes(array $postTypes): callable
    {
        return function () use ($postTypes): bool {
            return !is_singular($postTypes);
        };
    }
}
