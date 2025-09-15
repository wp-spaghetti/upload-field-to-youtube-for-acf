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

if (!defined('ABSPATH')) {
    exit;
}

use Psr\Container\ContainerInterface;
use WpSpaghetti\UFTYFACF\Service\CacheHandler;
use WpSpaghetti\UFTYFACF\Service\GoogleClientManager;
use WpSpaghetti\UFTYFACF\Service\YoutubeApiService;
use WpSpaghetti\WpEnv\Environment;
use WpSpaghetti\WpLogger\Logger;

return [
    'plugin_prefix' => 'wpspaghetti_uftyfacf',
    'plugin_name' => WPSPAGHETTI_UFTYFACF_NAME, // Single words, no spaces, hyphens allowed
    'plugin_undername' => WPSPAGHETTI_UFTYFACF_UNDERNAME, // Single words, no spaces, underscores allowed

    'plugin_data' => \DI\factory(static function () {
        if (!function_exists('get_plugin_data')) {
            // @phpstan-ignore-next-line
            require_once ABSPATH.'wp-admin/includes/plugin.php';
        }

        return get_plugin_data(WPSPAGHETTI_UFTYFACF_FILE, false, false);
    }),

    'field_defaults' => \DI\factory(static fn (ContainerInterface $container): array => apply_filters($container->get('plugin_prefix').'_field_defaults', [
        'category_id' => 22, // People & Blogs
        'tags' => !empty($_SERVER['HTTP_HOST']) ? str_replace('www.', '', sanitize_text_field((string) wp_unslash($_SERVER['HTTP_HOST']))) : '',
        'privacy_status' => 'unlisted',
        'made_for_kids' => false,
        'allow_upload' => true,
        'allow_select' => true,
        'api_update_on_post_update' => true,
        'api_delete_on_post_delete' => false,
    ])),

    'env_settings' => \DI\factory(static fn (ContainerInterface $container): array => apply_filters($container->get('plugin_prefix').'_env_settings', [
        'version' => $container->get('plugin_data')['Version'] ?? '0.0.0',
        'url' => WPSPAGHETTI_UFTYFACF_URL,
        'path' => WPSPAGHETTI_UFTYFACF_PATH,
        'debug' => Environment::isDebug(),
        'locale' => get_locale(),
        'server_upload' => Environment::getBool('WPSPAGHETTI_UFTYFACF_SERVER_UPLOAD_ENABLED', false),
        'cron_schedule' => Environment::get('WPSPAGHETTI_UFTYFACF_CRON_SCHEDULE', 'daily'),
        'recent_upload_time_window' => Environment::getInt('WPSPAGHETTI_UFTYFACF_RECENT_UPLOAD_TIME_WINDOW', 300), // 5 minutes
        'resumable_upload_max_chunks' => Environment::getInt('WPSPAGHETTI_UFTYFACF_RESUMABLE_UPLOAD_MAX_CHUNKS', 10000), // Increased limit for very large files
        'video_id_retrieval_max_attempts' => Environment::getInt('WPSPAGHETTI_UFTYFACF_VIDEO_ID_RETRIEVAL_MAX_ATTEMPTS', 5), // Maximum retry attempts
        'video_id_retrieval_sleep_interval' => Environment::getInt('WPSPAGHETTI_UFTYFACF_VIDEO_ID_RETRIEVAL_SLEEP_INTERVAL', 3), // Sleep between attempts in seconds
        'video_id_retrieval_initial_sleep' => Environment::getInt('WPSPAGHETTI_UFTYFACF_VIDEO_ID_RETRIEVAL_INITIAL_SLEEP', 2), // Initial sleep before first attempt in seconds
    ])),

    'allowed_video_mime_types' => \DI\factory(static fn (ContainerInterface $container): array => apply_filters($container->get('plugin_prefix').'_allowed_video_mime_types', [
        // Common video formats supported by YouTube
        'mp4' => 'video/mp4',
        'avi' => 'video/avi',
        'mov' => 'video/quicktime',
        'wmv' => 'video/x-ms-wmv',
        'flv' => 'video/x-flv',
        'webm' => 'video/webm',
        'mkv' => 'video/x-matroska',
        '3gp' => 'video/3gpp',
        'ogv' => 'video/ogg',
        'm4v' => 'video/mp4',
        'mpg' => 'video/mpeg',
        'mpeg' => 'video/mpeg',
        'mts' => 'video/mp2t',
        'ts' => 'video/mp2t',
    ])),

    'allowed_html' => static function (ContainerInterface $container): array {
        if (!function_exists('wp_kses_allowed_html')) {
            // @phpstan-ignore-next-line
            require_once ABSPATH.'wp-includes/kses.php';
        }

        /**
         * @param array|string $context The context for which to retrieve tags.
         *                              Allowed values are 'post', 'strip', 'data', 'entities',
         *                              or the name of a field filter such as 'pre_user_description',
         *                              or an array of allowed HTML elements and attributes.
         *
         * @return array an array of allowed HTML elements and their attributes
         */
        $allowed_html = wp_kses_allowed_html('post');

        $tags = [
            // Form container (missing from WordPress default)
            'form' => [
                'action' => true,
                'method' => true,
                'enctype' => true,
                'name' => true,
                'id' => true,
                'class' => true,
                'style' => true,
                'data-*' => true,
                'aria-label' => true,
                'aria-labelledby' => true,
                'role' => true,
            ],

            // Input fields (missing from WordPress default)
            'input' => [
                'type' => true,
                'name' => true,
                'id' => true,
                'value' => true,
                'class' => true,
                'style' => true,
                'placeholder' => true,
                'required' => true,
                'disabled' => true,
                'readonly' => true,
                'checked' => true,
                'min' => true,
                'max' => true,
                'step' => true,
                'maxlength' => true,
                'pattern' => true,
                'data-*' => true,
                'aria-label' => true,
                'aria-labelledby' => true,
                'aria-describedby' => true,
                'role' => true,
            ],

            // Select dropdowns (missing from WordPress default)
            'select' => [
                'name' => true,
                'id' => true,
                'class' => true,
                'style' => true,
                'multiple' => true,
                'size' => true,
                'required' => true,
                'disabled' => true,
                'data-*' => true,
                'aria-label' => true,
                'aria-labelledby' => true,
                'aria-describedby' => true,
                'role' => true,
            ],

            // Option elements (missing from WordPress default)
            'option' => [
                'value' => true,
                'selected' => true,
                'disabled' => true,
                'class' => true,
                'style' => true,
                'data-*' => true,
            ],

            // Option groups (missing from WordPress default)
            'optgroup' => [
                'label' => true,
                'disabled' => true,
                'class' => true,
                'style' => true,
                'data-*' => true,
            ],

            // Button already exists, but might need form-specific attributes
            'button' => [
                'onclick' => true, // Often needed for form interactions
            ],

            // Legend already exists, might need disabled attribute
            'legend' => [
                'disabled' => true, // For fieldset functionality
            ],

            // Fieldset already exists, might need disabled attribute
            'fieldset' => [
                'disabled' => true, // Essential for fieldset functionality
            ],

            // Textarea already exists, might need additional form attributes
            'textarea' => [
                'placeholder' => true,
                'required' => true,
                'maxlength' => true,
                'minlength' => true,
                'wrap' => true,
            ],
        ];

        foreach ($tags as $tag => $attrs) {
            if (isset($allowed_html[$tag])) {
                $allowed_html[$tag] = array_merge($allowed_html[$tag], $attrs);
            } else {
                $allowed_html[$tag] = $attrs;
            }
        }

        return apply_filters($container->get('plugin_prefix').'_allowed_html', $allowed_html);
    },

    'wp_filesystem' => static function (): WP_Filesystem_Direct {
        // https://wordpress.stackexchange.com/a/370377/99214
        if (!function_exists('WP_Filesystem_Direct')) {
            // @phpstan-ignore-next-line
            require_once ABSPATH.'wp-admin/includes/class-wp-filesystem-base.php';

            // @phpstan-ignore-next-line
            require_once ABSPATH.'wp-admin/includes/class-wp-filesystem-direct.php';
        }

        return new WP_Filesystem_Direct(null);
    },

    CacheHandler::class => \DI\autowire(),

    GoogleClientManager::class => \DI\autowire(),

    YoutubeApiService::class => \DI\autowire(),

    Logger::class => static function (ContainerInterface $container) {
        // Use Environment class to detect testing environment
        $minLogLevel = Environment::isTesting() ? 'emergency' : (Environment::isDebug() ? 'debug' : 'info');

        return new Logger([
            'component_name' => $container->get('plugin_prefix'),
            'min_level' => $minLogLevel,
        ]);
    },

    // Allow third-party extensions to add their own definitions
    // This can be extended via WordPress filters
];
