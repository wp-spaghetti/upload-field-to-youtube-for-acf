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

namespace WpSpaghetti\UFTYFACF\Service;

use DI\Container;
use WpSpaghetti\UFTYFACF\Trait\HookTrait;
use WpSpaghetti\WpLogger\Logger;

if (!\defined('ABSPATH')) {
    exit;
}

/**
 * Cache Handler Class for managing WordPress cache compatibility.
 */
class CacheHandler
{
    use HookTrait;

    /**
     * Minimum required length for access token validation.
     */
    private const MIN_TOKEN_LENGTH = 10;

    /**
     * Minimum token length for safe logging (to show first/last chars).
     */
    private const MIN_LOG_TOKEN_LENGTH = 8;

    private string $option_name;

    public function __construct(
        private Container $container,
        private Logger $logger
    ) {
        $this->init_hook($this->container);

        $this->option_name = $this->container->get('plugin_prefix').'_access_token';
    }

    /**
     * Get access token with comprehensive cache bypass.
     *
     * @return null|array<string, mixed>
     */
    public function get_access_token(): ?array
    {
        $cache_info = $this->get_cache_info();

        // Action before getting access token
        $this->do_action(__FUNCTION__.'_before', $cache_info);

        if (!empty($cache_info['has_cache'])) {
            $token = $this->get_with_cache_bypass();
        } else {
            $token = get_option($this->option_name);
        }

        // Ensure token is properly formatted array or null
        if (!\is_array($token) && false !== $token) {
            // Try to decode if it's a JSON string (cache corruption)
            if (\is_string($token)) {
                $decoded = json_decode($token, true);
                if (JSON_ERROR_NONE === json_last_error() && \is_array($decoded)) {
                    $token = $decoded;
                    $this->logger->warning('Token was stored as JSON string, decoded successfully', [
                        'cache_info' => $cache_info,
                    ]);
                } else {
                    $this->logger->error('Invalid token format from cache', [
                        'token' => $this->sanitize_token_for_logging($token),
                        'cache_info' => $cache_info,
                    ]);
                    $token = null;
                }
            } else {
                $token = null;
            }
        }

        // Filter to modify the retrieved token
        $token = $this->apply_filters(__FUNCTION__.'_token', $token, $cache_info);

        // Action after getting access token
        $this->do_action(__FUNCTION__.'_after', $token, $cache_info);

        return \is_array($token) ? $token : null;
    }

    /**
     * Save access token with cache handling.
     *
     * @param mixed $token
     */
    public function save_access_token($token): bool
    {
        // Action before saving access token
        $this->do_action(__FUNCTION__.'_before', $token);

        // Filter to modify the token before saving
        $token = $this->apply_filters(__FUNCTION__.'_token', $token);

        if (!$this->is_valid_token_format($token)) {
            $this->logger->error('Attempting to save invalid token format', [
                'token' => $this->sanitize_token_for_logging($token),
            ]);

            // Action when token format is invalid
            $this->do_action(__FUNCTION__.'_invalid_token_format', $token);

            return false;
        }

        $cache_info = $this->get_cache_info();

        if (!empty($cache_info['has_cache'])) {
            $result = $this->save_with_cache_handling($token);
        } else {
            $result = update_option($this->option_name, $token);
        }

        if ($result) {
            // Action after successful save
            $this->do_action(__FUNCTION__.'_after', $token, $cache_info);
        } else {
            // Action when save fails
            $this->do_action(__FUNCTION__.'_error', $token, $cache_info);
        }

        return $result;
    }

    /**
     * Delete access token with cache handling.
     */
    public function delete_access_token(): bool
    {
        $cache_info = $this->get_cache_info();

        // Action before deleting access token
        $this->do_action(__FUNCTION__.'_before', $cache_info);

        if (!empty($cache_info['has_cache'])) {
            $this->clear_all_caches();
        }

        $result = delete_option($this->option_name);

        if (!empty($cache_info['has_cache'])) {
            $this->clear_all_caches();
        }

        if ($result) {
            // Action after successful deletion
            $this->do_action(__FUNCTION__.'_after', $cache_info);
        }

        return (bool) $result; // Ensure boolean return type
    }

    /**
     * Get information about active cache plugins.
     *
     * @return array<string, mixed>
     */
    public function get_cache_info(): array
    {
        $cache_plugins = [];
        $has_cache = false;

        // Check for various cache plugins
        // @phpstan-ignore-next-line
        if (\defined('W3TC') && W3TC) {
            $cache_plugins[] = 'W3 Total Cache';
            $has_cache = true;
        }

        if (\defined('WP_ROCKET_VERSION')) {
            $cache_plugins[] = 'WP Rocket';
            $has_cache = true;
        }

        if (\defined('LSCWP_V')) {
            $cache_plugins[] = 'LiteSpeed Cache';
            $has_cache = true;
        }

        if (\defined('WPCACHEHOME')) {
            $cache_plugins[] = 'WP Super Cache';
            $has_cache = true;
        }

        if (class_exists('WpFastestCache')) {
            $cache_plugins[] = 'WP Fastest Cache';
            $has_cache = true;
        }

        if (\defined('AUTOPTIMIZE_PLUGIN_VERSION')) {
            $cache_plugins[] = 'Autoptimize';
            $has_cache = true;
        }

        if (class_exists('Hummingbird\WP_Hummingbird')) {
            $cache_plugins[] = 'Hummingbird';
            $has_cache = true;
        }

        if (\defined('BREEZE_VERSION')) {
            $cache_plugins[] = 'Breeze';
            $has_cache = true;
        }

        if (class_exists('SiteGround_Optimizer\Loader')) {
            $cache_plugins[] = 'SiteGround Optimizer';
            $has_cache = true;
        }

        if (\defined('CACHE_ENABLER_VERSION')) {
            $cache_plugins[] = 'Cache Enabler';
            $has_cache = true;
        }

        if (\defined('COMET_CACHE_VERSION')) {
            $cache_plugins[] = 'Comet Cache';
            $has_cache = true;
        }

        // Check for external object cache
        if (wp_using_ext_object_cache()) {
            $cache_plugins[] = 'External Object Cache';
            $has_cache = true;
        }

        $cache_info = [
            'has_cache' => $has_cache,
            'cache_plugins' => $cache_plugins,
            'using_external_cache' => wp_using_ext_object_cache(),
        ];

        // Action before getting cache info
        $this->do_action(__FUNCTION__.'_before', $cache_info);

        // Filter to modify cache info - ensure result is always an array
        $filtered_cache_info = $this->apply_filters(__FUNCTION__.'_cache_info', $cache_info);

        // Ensure the filtered result is an array - fallback to original if not
        if (!\is_array($filtered_cache_info) || empty($filtered_cache_info)) {
            $filtered_cache_info = $cache_info;
        }

        // Ensure required keys exist with safe defaults
        $filtered_cache_info = array_merge([
            'has_cache' => false,
            'cache_plugins' => [],
            'using_external_cache' => false,
        ], $filtered_cache_info);

        // Action after getting cache info
        $this->do_action(__FUNCTION__.'_after', $filtered_cache_info);

        return $filtered_cache_info;
    }

    /**
     * Validate if the token has the expected format.
     *
     * @param mixed $token
     */
    public function is_valid_token_format($token): bool
    {
        // Token should be an array
        if (!\is_array($token)) {
            return false;
        }

        // Token should have required keys
        $required_keys = ['access_token', 'token_type'];
        foreach ($required_keys as $key) {
            if (!isset($token[$key]) || empty($token[$key])) {
                return false;
            }
        }

        // access_token should be a non-empty string
        if (!\is_string($token['access_token']) || \strlen($token['access_token']) < self::MIN_TOKEN_LENGTH) {
            return false;
        }

        // token_type should be 'Bearer'
        if ('Bearer' !== $token['token_type']) {
            return false;
        }

        return true;
    }

    /**
     * Sanitize token data for safe logging.
     *
     * @param mixed $token
     *
     * @return array<string, mixed>|string
     */
    public function sanitize_token_for_logging($token): array|string
    {
        if (!\is_array($token)) {
            return 'invalid_format: '.\gettype($token);
        }

        $sanitized = [];
        foreach ($token as $key => $value) {
            if (\in_array($key, ['access_token', 'refresh_token'], true)) {
                // Show only first and last 4 characters for security
                $sanitized[$key] = \is_string($value) && \strlen($value) > self::MIN_LOG_TOKEN_LENGTH
                    ? substr($value, 0, 4).'...'.substr($value, -4)
                    : 'invalid_format';
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Get access token with cache bypass strategies.
     *
     * @return null|array<string, mixed>|bool
     */
    private function get_with_cache_bypass(): array|bool|null
    {
        $max_attempts = 3;
        $attempt = 0;
        $token = null;

        while ($attempt < $max_attempts) {
            // Clear caches before reading
            $this->clear_all_caches();

            $token = get_option($this->option_name);

            // If we get a valid token or explicit false, return it
            if (false === $token || $this->is_valid_token_format($token)) {
                return $token;
            }

            // Token is corrupted or null, try more aggressive cache clearing
            $this->aggressive_cache_clear();

            ++$attempt;

            // Small delay to avoid race conditions
            if ($attempt < $max_attempts) {
                usleep(100000); // 100ms
            }
        }

        $this->logger->warning('Failed to retrieve valid token after multiple attempts', [
            'attempts' => $attempt,
            'final_token' => $this->sanitize_token_for_logging($token ?? 'null'),
            'cache_info' => $this->get_cache_info(),
        ]);

        return $token ?? false;
    }

    /**
     * Save token with cache handling and verification.
     *
     * @param mixed $token
     */
    private function save_with_cache_handling($token): bool
    {
        // Clear cache before saving
        $this->clear_all_caches();

        $result = update_option($this->option_name, $token);

        if (!$result) {
            return false;
        }

        // Clear cache after saving
        $this->clear_all_caches();

        // Verify the save was successful
        $this->clear_all_caches();
        $verify_token = get_option($this->option_name);

        if (!$this->tokens_are_equal($token, $verify_token)) {
            $this->logger->error('Token verification failed after save - possible cache corruption', [
                'saved_token' => $this->sanitize_token_for_logging($token),
                'retrieved_token' => $this->sanitize_token_for_logging($verify_token),
                'cache_info' => $this->get_cache_info(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Clear all known caches based on detected cache plugins.
     */
    private function clear_all_caches(): void
    {
        // Action before clearing caches
        $this->do_action(__FUNCTION__.'_before');

        $cache_info = $this->get_cache_info();

        if (empty($cache_info['cache_plugins']) || !\is_array($cache_info['cache_plugins'])) {
            return;
        }

        // Define default cache clearing methods
        $default_methods = [
            'w3tc' => true,
            'wp_rocket' => true,
            'litespeed' => true,
            'wp_super_cache' => true,
            'wp_fastest_cache' => true,
            'autoptimize' => true,
            'hummingbird' => true,
            'breeze' => true,
            'siteground' => true,
            'cache_enabler' => true,
            'comet_cache' => true,
            'external_cache' => true,
        ];

        // Filter to customize cache clearing methods
        $cache_clear_methods = $this->apply_filters(__FUNCTION__.'_cache_clear_methods', $default_methods);

        // Ensure cache_clear_methods is an array with safe defaults
        if (!\is_array($cache_clear_methods)) {
            $cache_clear_methods = $default_methods;
        }

        // Clear caches based on detected plugins
        foreach ($cache_info['cache_plugins'] as $plugin) {
            $this->clear_specific_cache($plugin, $cache_clear_methods);
        }

        // Clear external object cache if available
        if (!empty($cache_clear_methods['external_cache']) && wp_using_ext_object_cache()) {
            if (\function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
        }

        // Action after clearing caches
        $this->do_action(__FUNCTION__.'_after', $cache_clear_methods);
    }

    /**
     * Clear specific cache plugin cache.
     *
     * @param array<string, bool> $methods
     */
    private function clear_specific_cache(string $plugin, array $methods): void
    {
        switch ($plugin) {
            case 'W3 Total Cache':
                if (!empty($methods['w3tc']) && \function_exists('w3tc_flush_all')) {
                    w3tc_flush_all();
                }

                break;

            case 'WP Rocket':
                if (!empty($methods['wp_rocket']) && \function_exists('rocket_clean_domain')) {
                    rocket_clean_domain();
                }

                break;

            case 'LiteSpeed Cache':
                if (!empty($methods['litespeed']) && class_exists('LiteSpeed\Purge')) {
                    $purgeClass = 'LiteSpeed\Purge';
                    // @phpstan-ignore-next-line
                    if (method_exists($purgeClass, 'purge_all')) {
                        $purgeClass::purge_all();
                    }
                }

                break;

            case 'WP Super Cache':
                if (!empty($methods['wp_super_cache']) && \function_exists('wp_cache_clear_cache')) {
                    wp_cache_clear_cache();
                }

                break;

            case 'WP Fastest Cache':
                if (!empty($methods['wp_fastest_cache']) && class_exists('WpFastestCache')) {
                    $cache = new \WpFastestCache();
                    if (method_exists($cache, 'deleteCache')) {
                        $cache->deleteCache(true);
                    }
                }

                break;

            case 'Autoptimize':
                if (!empty($methods['autoptimize']) && class_exists('autoptimizeCache')) {
                    // @phpstan-ignore-next-line
                    if (method_exists('autoptimizeCache', 'clearall')) {
                        /** @psalm-suppress UndefinedClass */
                        \autoptimizeCache::clearall();
                    }
                }

                break;

            case 'Hummingbird':
                if (!empty($methods['hummingbird']) && \function_exists('wphb_flush_cache')) {
                    wphb_flush_cache();
                }

                break;

            case 'Breeze':
                if (!empty($methods['breeze']) && \function_exists('breeze_clear_all_cache')) {
                    breeze_clear_all_cache();
                }

                break;

            case 'SiteGround Optimizer':
                if (!empty($methods['siteground']) && \function_exists('sg_cachepress_purge_cache')) {
                    sg_cachepress_purge_cache();
                }

                break;

            case 'Cache Enabler':
                if (!empty($methods['cache_enabler']) && class_exists('Cache_Enabler')) {
                    // @phpstan-ignore-next-line
                    if (method_exists('Cache_Enabler', 'clear_complete_cache')) {
                        /** @psalm-suppress UndefinedClass */
                        \Cache_Enabler::clear_complete_cache();
                    }
                }

                break;

            case 'Comet Cache':
                if (!empty($methods['comet_cache']) && class_exists('comet_cache')) {
                    // @phpstan-ignore-next-line
                    if (method_exists('comet_cache', 'clear')) {
                        /** @psalm-suppress UndefinedClass */
                        \comet_cache::clear();
                    }
                }

                break;
        }
    }

    /**
     * More aggressive cache clearing for stubborn caches.
     */
    private function aggressive_cache_clear(): void
    {
        // Action before aggressive cache clearing
        $this->do_action(__FUNCTION__.'_before');

        // Force WordPress to reload options
        wp_load_alloptions();

        // Clear external object cache if available
        if (\function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        // Clear opcache if available
        if (\function_exists('opcache_reset')) {
            opcache_reset();
        }

        // Clear APCu if available
        if (\function_exists('apcu_clear_cache')) {
            apcu_clear_cache();
        }

        // Action after aggressive cache clearing
        $this->do_action(__FUNCTION__.'_after');
    }

    /**
     * Compare two tokens for equality.
     *
     * @param mixed $token1
     * @param mixed $token2
     */
    private function tokens_are_equal($token1, $token2): bool
    {
        if (!\is_array($token1) || !\is_array($token2)) {
            return false;
        }

        // Compare essential fields
        $essential_fields = ['access_token', 'token_type', 'refresh_token', 'expires_in'];

        foreach ($essential_fields as $field) {
            if (isset($token1[$field]) !== isset($token2[$field])) {
                return false;
            }

            if (isset($token1[$field]) && $token1[$field] !== $token2[$field]) {
                return false;
            }
        }

        return true;
    }
}
