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

// Require Composer autoloader
require_once dirname(__DIR__).'/vendor/autoload.php';

// Load WordPress stubs
if (file_exists(dirname(__DIR__).'/vendor/php-stubs/wordpress-stubs/wordpress-stubs.php')) {
    require_once dirname(__DIR__).'/vendor/php-stubs/wordpress-stubs/wordpress-stubs.php';
}

// Load ACF stubs
if (file_exists(dirname(__DIR__).'/vendor/php-stubs/acf-pro-stubs/acf-pro-stubs.php')) {
    require_once dirname(__DIR__).'/vendor/php-stubs/acf-pro-stubs/acf-pro-stubs.php';
}

// IMPORTANT: Do not mock internal PHP functions
// WP_Mock cannot override internal PHP functions like sprintf, strlen, etc.
// If these functions are needed in tests, use them directly.

// Initialize WP_Mock - this must come after stubs are loaded
WP_Mock::bootstrap();

// Configure Mockery to be less strict about method calls
Mockery::globalHelpers();

// Create a custom Mockery configuration
Mockery::getConfiguration()->allowMockingNonExistentMethods(true);
Mockery::getConfiguration()->allowMockingMethodsUnnecessarily(true);

// Set up Mockery with WordPress-friendly defaults
Mockery::getConfiguration()->mockingNonExistentMethodsAllowed(true);

// Mock essential WordPress constants if not defined
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', '/tmp/wordpress/wp-content/');
}

if (!defined('WPMU_PLUGIN_DIR')) {
    define('WPMU_PLUGIN_DIR', '/tmp/wordpress/wp-content/mu-plugins/');
}

// Mock plugin constants for testing
if (!defined('WPSPAGHETTI_UFTYFACF_BASENAME')) {
    define('WPSPAGHETTI_UFTYFACF_BASENAME', 'my-plugin/my-plugin.php');
}

if (!defined('WPSPAGHETTI_UFTYFACF_NAME')) {
    define('WPSPAGHETTI_UFTYFACF_NAME', 'my-plugin');
}

if (!defined('WPSPAGHETTI_UFTYFACF_UNDERNAME')) {
    define('WPSPAGHETTI_UFTYFACF_UNDERNAME', 'my_plugin');
}

if (!defined('WPSPAGHETTI_UFTYFACF_URL')) {
    define('WPSPAGHETTI_UFTYFACF_URL', 'http://example.com/wp-content/plugins/my-plugin/');
}

if (!defined('WPSPAGHETTI_UFTYFACF_PATH')) {
    define('WPSPAGHETTI_UFTYFACF_PATH', '/tmp/wordpress/wp-content/plugins/my-plugin/');
}

// Mock cache plugin constants that might be checked
if (!defined('W3TC')) {
    define('W3TC', false);
}

if (!defined('WP_ROCKET_VERSION')) {
    define('WP_ROCKET_VERSION', false);
}

if (!defined('LSCWP_V')) {
    define('LSCWP_V', false);
}

if (!defined('WPCACHEHOME')) {
    define('WPCACHEHOME', false);
}

if (!defined('AUTOPTIMIZE_PLUGIN_VERSION')) {
    define('AUTOPTIMIZE_PLUGIN_VERSION', false);
}

if (!defined('BREEZE_VERSION')) {
    define('BREEZE_VERSION', false);
}

if (!defined('CACHE_ENABLER_VERSION')) {
    define('CACHE_ENABLER_VERSION', false);
}

if (!defined('COMET_CACHE_VERSION')) {
    define('COMET_CACHE_VERSION', false);
}

// Define WordPress debug constants for test environment
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', false);
}

if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', false);
}

if (!defined('WP_DEBUG_DISPLAY')) {
    define('WP_DEBUG_DISPLAY', false);
}

// Error handler for capturing errors during tests
set_error_handler(static function ($severity, $message, $file, $line): void {
    // Convert PHP errors to exceptions for better test debugging
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
});

// Create a global helper function to reset WP_Mock state between tests
function reset_wp_mock_state(): void
{
    if (class_exists('WP_Mock')) {
        WP_Mock::tearDown();
        WP_Mock::setUp();
    }
}

// Register the helper function as a shutdown function to clean up
register_shutdown_function(static function (): void {
    if (class_exists('WP_Mock')) {
        WP_Mock::tearDown();
    }
});

// Custom error handler for WordPress function mocking
function handleWordPressFunctionMocking($errno, $errstr, $errfile, $errline)
{
    // Ignore warnings about WordPress functions not being defined
    if (str_contains($errstr, 'was called before it was declared')) {
        return true;
    }

    // Ignore Mockery-related warnings in test environment
    if (str_contains($errstr, 'Mockery') && str_contains($errstr, 'should be called')) {
        return true;
    }

    return false; // Let other errors through
}

set_error_handler('handleWordPressFunctionMocking', E_WARNING | E_NOTICE);
