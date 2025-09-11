<?php

declare(strict_types=1);

/*
 * This file is part of the WordPress plugin "Upload Field to YouTube for ACF".
 *
 * (ɔ) Frugan <dev@frugan.it>
 *
 * This source file is subject to the GNU GPLv3 or later license that is bundled
 * with this source code in the file LICENSE.
 */

namespace WpSpaghetti\UFTYFACF\Trait;

use DI\Container;

if (!\defined('ABSPATH')) {
    exit;
}

/**
 * Trait BaseHookTrait - Base hook functionality without conflicting methods.
 *
 * This trait provides the core hook functionality that is safe to use in all classes,
 * including those that extend acf_field. It excludes add_action() and add_filter()
 * methods that would conflict with the acf_field parent class.
 */
trait BaseHookTrait
{
    /**
     * Cached hook prefix for this class instance.
     */
    private string $hook_prefix;

    /**
     * Initialize the hook functionality.
     * Should be called in the constructor of classes using this trait.
     *
     * @param Container $container the dependency injection container
     */
    protected function init_hook(Container $container): void
    {
        $this->hook_prefix = $container->get('plugin_prefix').'_'.$this->get_short_name().'_';
    }

    /**
     * Get the hook prefix for this class.
     *
     * @return string the hook prefix (e.g., 'wpspaghetti_uftyfacf_bootstrap')
     */
    protected function get_hook_prefix(): string
    {
        return $this->hook_prefix;
    }

    /**
     * Execute a WordPress action with the class-specific prefix.
     *
     * @param string $action  the action name (will be prefixed)
     * @param mixed  ...$args action arguments
     */
    protected function do_action(string $action, ...$args): void
    {
        do_action($this->get_hook_prefix().$action, ...$args);
    }

    /**
     * Apply a WordPress filter with the class-specific prefix.
     *
     * @param string $filter  the filter name (will be prefixed)
     * @param mixed  $value   the value to filter
     * @param mixed  ...$args additional filter arguments
     *
     * @return mixed the filtered value
     */
    protected function apply_filters(string $filter, $value, ...$args)
    {
        return apply_filters($this->get_hook_prefix().$filter, $value, ...$args);
    }

    /**
     * Remove a WordPress action with the class-specific prefix.
     *
     * @param string   $action   the action name (will be prefixed)
     * @param callable $callback the callback function
     * @param int      $priority the priority (default: 10)
     *
     * @return bool true on success, false on failure
     */
    protected function remove_action(string $action, callable $callback, int $priority = 10): bool
    {
        $result = remove_action($this->get_hook_prefix().$action, $callback, $priority);

        return (bool) $result; // Ensure boolean return
    }

    /**
     * Remove a WordPress filter with the class-specific prefix.
     *
     * @param string   $filter   the filter name (will be prefixed)
     * @param callable $callback the callback function
     * @param int      $priority the priority (default: 10)
     *
     * @return bool true on success, false on failure
     */
    protected function remove_filter(string $filter, callable $callback, int $priority = 10): bool
    {
        $result = remove_filter($this->get_hook_prefix().$filter, $callback, $priority);

        return (bool) $result; // Ensure boolean return
    }

    /**
     * Get the short class name in lowercase.
     *
     * @return string the lowercase class name (e.g., 'bootstrap')
     */
    private function get_short_name(): string
    {
        return strtolower((new \ReflectionClass(static::class))->getShortName());
    }
}
