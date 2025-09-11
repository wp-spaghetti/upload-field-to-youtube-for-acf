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

use Psr\Container\ContainerInterface;

// Load WordPress stubs
if (file_exists(dirname(__DIR__).'/vendor/php-stubs/wordpress-stubs/wordpress-stubs.php')) {
    require_once dirname(__DIR__).'/vendor/php-stubs/wordpress-stubs/wordpress-stubs.php';
}

// Load ACF stubs
if (file_exists(dirname(__DIR__).'/vendor/php-stubs/acf-pro-stubs/acf-pro-stubs.php')) {
    require_once dirname(__DIR__).'/vendor/php-stubs/acf-pro-stubs/acf-pro-stubs.php';
}

// Define plugin constants that Psalm needs to know about
define('WPSPAGHETTI_UFTYFACF_VERSION', '0.1.0');
define('WPSPAGHETTI_UFTYFACF_BASENAME', 'my-plugin/my-plugin.php');
define('WPSPAGHETTI_UFTYFACF_NAME', 'my-plugin');
define('WPSPAGHETTI_UFTYFACF_UNDERNAME', 'my_plugin');
define('WPSPAGHETTI_UFTYFACF_URL', 'https://example.com/wp-content/plugins/my-plugin/');
define('WPSPAGHETTI_UFTYFACF_PATH', '/tmp/wordpress/wp-content/plugins/my-plugin/');

// Define other WordPress constants commonly used
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}

if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', true);
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', '/tmp/wordpress/wp-content/');
}

if (!defined('WPMU_PLUGIN_DIR')) {
    define('WPMU_PLUGIN_DIR', '/tmp/wordpress/wp-content/mu-plugins/');
}

// Add function for Psalm
if (!function_exists('wpspaghetti_uftyfacf_get_container')) {
    /**
     * Get the global container instance for Psalm static analysis.
     */
    function wpspaghetti_uftyfacf_get_container(): ContainerInterface
    {
        return new class implements ContainerInterface {
            #[Override]
            public function get(string $id)
            {
                // Return appropriate mock objects based on the requested service
                return match ($id) {
                    'plugin_prefix' => 'my-plugin',
                    'plugin_name' => 'my-plugin',
                    'plugin_undername' => 'my_plugin',
                    'field_defaults' => [],
                    'env_settings' => [],
                    default => new stdClass()
                };
            }

            #[Override]
            public function has(string $id): bool
            {
                return true;
            }
        };
    }
}

// Add missing class for cache handler
if (!class_exists('WpFastestCache')) {
    class WpFastestCache
    {
        public function deleteCache($minified = false): void
        {
            // Mock implementation for Psalm
        }
    }
}
