![](.wordpress-org/banner-1544x500.jpg)

![GitHub Downloads (all assets, all releases)](https://img.shields.io/github/downloads/wp-spaghetti/upload-field-to-youtube-for-acf/total)
![GitHub Actions Workflow Status](https://github.com/wp-spaghetti/upload-field-to-youtube-for-acf/actions/workflows/main.yml/badge.svg)
![Coverage Status](https://img.shields.io/codecov/c/github/wp-spaghetti/upload-field-to-youtube-for-acf)
![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=wp-spaghetti_upload-field-to-youtube-for-acf&metric=alert_status)
![Security Rating](https://sonarcloud.io/api/project_badges/measure?project=wp-spaghetti_upload-field-to-youtube-for-acf&metric=security_rating)
![Known Vulnerabilities](https://snyk.io/test/github/wp-spaghetti/upload-field-to-youtube-for-acf/badge.svg)
![GitHub Issues](https://img.shields.io/github/issues/wp-spaghetti/upload-field-to-youtube-for-acf)
![GitHub Release](https://img.shields.io/github/v/release/wp-spaghetti/upload-field-to-youtube-for-acf)
![License](https://img.shields.io/github/license/wp-spaghetti/upload-field-to-youtube-for-acf)
<!--
![PHP Version](https://img.shields.io/badge/php->=8.0-blue)
![Code Climate](https://img.shields.io/codeclimate/maintainability/wp-spaghetti/upload-field-to-youtube-for-acf)
-->

# Upload Field to YouTube for ACF (WordPress Plugin)

**Upload Field to YouTube for ACF** is a WordPress plugin that allows you to upload videos directly to YouTube via API from the WordPress admin area and/or select existing videos on your YouTube channel based on playlists. It is particularly useful for managing videos that may be associated with Custom Post Types (CPT).

To use this plugin, you need to configure Google oAuth credentials so the plugin can authenticate with the user's YouTube channel.

## Requirements

- PHP ^8.0
- WordPress ^5.7 || ^6.0
- [Advanced Custom Fields](https://www.advancedcustomfields.com) ^5.9 || ^6.0

## Features

- **Direct video upload to YouTube**: upload videos from CPTs within the WordPress interface via Google API
- **Dual upload modes**: client-side upload (browser → YouTube, default) or server-side upload (browser → WordPress → YouTube)  
- **Video selection from playlists**: select existing videos from your YouTube channel organized by playlists
- **Privacy control support**: manage "unlisted" and private videos, ideal for exclusive content tied to specific posts
- **Dependency injection architecture**: clean, extensible codebase with PHP-DI container for better maintainability
- **Official ACF integration**: built using the official [ACF Example Field Type](https://github.com/AdvancedCustomFields/acf-example-field-type) as foundation
- **Asset management**: built-in Vite.js integration through [WP Vite](https://github.com/wp-spaghetti/wp-vite) for modern development workflow
- **Environment management**: built-in support for WordPress constants and .env files via [WP Env](https://github.com/wp-spaghetti/wp-env)
- **Comprehensive logging system**: PSR-3 compatible logging with [WP Logger](https://github.com/wp-spaghetti/wp-logger) integration and optional [Wonolog](https://github.com/inpsyde/Wonolog) support
- **Zero external dependencies**: works with or without optional logging libraries, with automatic fallback to native file-based logging
- **Modern JavaScript implementation**: built with [Vanilla JS](http://vanilla-js.com) (no jQuery dependency) and Vite.js for development and build processes
- **Professional development setup**: autoload classes with Composer and PSR-4, dependency management
- **Advanced field support**: full compatibility with ACF nested repeaters and complex field structures  
- **Internationalization ready**: translations managed via [Crowdin](https://crowdin.com/project/upload-field-to-youtube-for-acf) with automated CI/CD integration

## Installation

You can install the plugin in three ways: manually, via Composer from [WPackagist](https://wpackagist.org), or via Composer from [GitHub Releases](../../releases).

<details>
<summary>Manual Installation</summary>

1. Go to the [Releases](../../releases) section of this repository.
2. Download the latest release zip file.
3. Log in to your WordPress admin dashboard.
4. Navigate to `Plugins` > `Add New`.
5. Click `Upload Plugin`.
6. Choose the downloaded zip file and click `Install Now`.

</details>

<details>
<summary>Installation via Composer from WPackagist</summary>

If you use Composer to manage WordPress plugins, you can install it from [WordPress Packagist](https://wpackagist.org):

1. Open your terminal.
2. Navigate to the root directory of your WordPress installation.
3. Ensure your `composer.json` file has the following configuration: *

```json
{
    "require": {
        "composer/installers": "^1.0 || ^2.0",
        "wpackagist-plugin/upload-field-to-youtube-for-acf": "^0.6"
    },
    "extra": {
        "installer-paths": {
            "wp-content/plugins/{$name}/": [
               "type:wordpress-plugin"
            ]
        }
    }
}
```
4. Run the following command:

```sh
composer update
```

<sub><i>
_Note:_  
_* `composer/installers` might already be required by another dependency._
</i></sub>
</details>

<details>
<summary>Installation via Composer from GitHub Releases</summary>

If you use Composer to manage WordPress plugins, you can install it from this repository directly.

**Standard Version** (uses WordPress update system):

1. Open your terminal.
2. Navigate to the root directory of your WordPress installation.
3. Ensure your `composer.json` file has the following configuration: *

```json
{
    "require": {
        "composer/installers": "^1.0 || ^2.0",
        "wp-spaghetti/upload-field-to-youtube-for-acf": "^0.6"
    },
    "repositories": [
        {
            "type": "package",
            "package": {
                "name": "wp-spaghetti/upload-field-to-youtube-for-acf",
                "version": "0.6.1",
                "type": "wordpress-plugin",
                "dist": {
                    "url": "https://github.com/wp-spaghetti/upload-field-to-youtube-for-acf/releases/download/v0.6.1/upload-field-to-youtube-for-acf.zip",
                    "type": "zip"
                }
            }
        }
    ],
    "extra": {
        "installer-paths": {
            "wp-content/plugins/{$name}/": [
               "type:wordpress-plugin"
            ]
        }
    }
}
```

**Version with Git Updater** (uses Git Updater Lite for updates):

For installations that need updates managed via Git instead of WordPress.org, use the `--with-git-updater` version:

```json
{
    "require": {
        "composer/installers": "^1.0 || ^2.0",
        "wp-spaghetti/upload-field-to-youtube-for-acf": "^0.6"
    },
    "repositories": [
        {
            "type": "package",
            "package": {
                "name": "wp-spaghetti/upload-field-to-youtube-for-acf",
                "version": "0.6.1",
                "type": "wordpress-plugin",
                "dist": {
                    "url": "https://github.com/wp-spaghetti/upload-field-to-youtube-for-acf/releases/download/v0.6.1/upload-field-to-youtube-for-acf--with-git-updater.zip",
                    "type": "zip"
                }
            }
        }
    ],
    "extra": {
        "installer-paths": {
            "wp-content/plugins/{$name}/": [
               "type:wordpress-plugin"
            ]
        }
    }
}
```

4. Run the following command:

```sh
composer update
```

<sub><i>
_Note:_  
_* `composer/installers` might already be required by another dependency._  
_* The `--with-git-updater` version includes [Git Updater Lite](https://github.com/afragen/git-updater-lite) for automatic updates detection, while the standard version relies on WordPress.org update system._
</i></sub>
</details>

## Configuration

Once installed:

1. In your WordPress admin dashboard, navigate to the `Plugins` section and click `Activate Plugin`.
2. Create a new field via ACF and select the `YouTube Uploader` type.
3. Read the description above for advanced usage instructions.

### Creating the oAuth Credentials

1. Go to the [Google API Console](https://console.developers.google.com/).
2. Create a project or select an existing one.
3. Navigate to the **OAuth consent screen** section and configure the required details to enable authentication.
4. Then, go to **Credentials** and create an **OAuth 2.0 Client ID**.
5. In the **Authorized redirect URIs** field, enter your WordPress site's callback URL (e.g., `https://domain.tld/wp-admin/`).
6. Obtain the `Client ID` and `Client Secret`.

#### Limitations

If you use the API upload mode, please note this important notice [here](https://developers.google.com/youtube/v3/docs/videos/insert):

> All videos uploaded via the `videos.insert` endpoint from unverified API projects created after July 28, 2020, will be restricted to private viewing mode. To lift this restriction, each API project must undergo an audit to verify compliance with the YouTube Terms of Service.

### Required Configurations

Add the following lines to `wp-config.php` to define the oAuth credentials:

```php
define('WPSPAGHETTI_UFTYFACF_GOOGLE_OAUTH_CLIENT_ID', 'Client ID');
define('WPSPAGHETTI_UFTYFACF_GOOGLE_OAUTH_CLIENT_SECRET', 'Client Secret');
```

### Optional Configurations

The plugin supports several optional configuration constants in `wp-config.php`:

#### Performance & Caching

```php
// Enable dependency injection container compilation cache (default: true)
define('WPSPAGHETTI_UFTYFACF_BUILDER_CACHE_ENABLED', true);

// Enable filename-based cache busting for CSS/JS assets
define('WPSPAGHETTI_UFTYFACF_VITE_CACHE_BUSTING_ENABLED', true);
```

For more information on cache busting, see filename-based cache busting on [Nginx](https://github.com/h5bp/server-configs-nginx/blob/main/h5bp/location/web_performance_filename-based_cache_busting.conf) and [Apache](https://github.com/h5bp/server-configs-apache/blob/main/h5bp/web_performance/filename-based_cache_busting.conf).

#### Upload Behavior

```php
// Enable server-side upload mode (browser → WordPress → YouTube, default: false)
define('WPSPAGHETTI_UFTYFACF_SERVER_UPLOAD_ENABLED', true);

// Maximum chunks for resumable uploads (default: 10000)
define('WPSPAGHETTI_UFTYFACF_RESUMABLE_UPLOAD_MAX_CHUNKS', 10000);

// Time window for recent upload detection in seconds (default: 300)
define('WPSPAGHETTI_UFTYFACF_RECENT_UPLOAD_TIME_WINDOW', 300);
```

#### Video ID Retrieval Settings

```php
// Maximum attempts to retrieve video ID after upload (default: 5)
define('WPSPAGHETTI_UFTYFACF_VIDEO_ID_RETRIEVAL_MAX_ATTEMPTS', 5);

// Sleep interval between retrieval attempts in seconds (default: 3)
define('WPSPAGHETTI_UFTYFACF_VIDEO_ID_RETRIEVAL_SLEEP_INTERVAL', 3);

// Initial sleep before first retrieval attempt in seconds (default: 2)
define('WPSPAGHETTI_UFTYFACF_VIDEO_ID_RETRIEVAL_INITIAL_SLEEP', 2);
```

#### Maintenance & Logging

```php
// Cron schedule for token maintenance (default: 'daily')
define('WPSPAGHETTI_UFTYFACF_CRON_SCHEDULE', 'daily');

// Log retention period in days (default: 30)
define('WPSPAGHETTI_UFTYFACF_LOGGER_RETENTION_DAYS', 30);

// Disable all plugin logging (default: false)
define('WPSPAGHETTI_UFTYFACF_LOGGER_DISABLED', true);
```

## Actions

<details>
<summary>Bootstrap Class</summary>

```php
// Bootstrap initialization
do_action('wpspaghetti_uftyfacf_bootstrap___construct_before');
do_action('wpspaghetti_uftyfacf_bootstrap___construct_after');

// Plugin loading
do_action('wpspaghetti_uftyfacf_bootstrap_muplugins_loaded_after');
do_action('wpspaghetti_uftyfacf_bootstrap_plugins_loaded_after');

// Initialization
do_action('wpspaghetti_uftyfacf_bootstrap_init_before');
do_action('wpspaghetti_uftyfacf_bootstrap_init_acf_not_available');
do_action('wpspaghetti_uftyfacf_bootstrap_init_mu_plugin_activated');
do_action('wpspaghetti_uftyfacf_bootstrap_init_after');

// Admin initialization
do_action('wpspaghetti_uftyfacf_bootstrap_admin_init_before');
do_action('wpspaghetti_uftyfacf_bootstrap_admin_init_deactivated_missing_acf');
do_action('wpspaghetti_uftyfacf_bootstrap_admin_init_after');
```

</details>

<details>
<summary>Field Class</summary>

```php
// Field rendering
do_action('wpspaghetti_uftyfacf_field_render_field_before', $field);
do_action('wpspaghetti_uftyfacf_field_render_field_after', $field);

// Field validation
do_action('wpspaghetti_uftyfacf_field_validate_value_before', $valid, $value, $field, $input);
do_action('wpspaghetti_uftyfacf_field_validate_value_after', $valid, $value, $field, $input);
do_action('wpspaghetti_uftyfacf_field_validate_value_error', $exception, $valid, $value, $field, $input);

// Field updates
do_action('wpspaghetti_uftyfacf_field_update_value_before', $value, $post_id, $field);
do_action('wpspaghetti_uftyfacf_field_update_value_after', $value, $post_id, $field, $response);
do_action('wpspaghetti_uftyfacf_field_update_value_error', $exception, $value, $post_id, $field, $response);

// Post deletion
do_action('wpspaghetti_uftyfacf_field_before_delete_post_before', $post_id, $field);
do_action('wpspaghetti_uftyfacf_field_before_delete_post_after', $post_id, $field);
do_action('wpspaghetti_uftyfacf_field_before_delete_post_error', $exception, $post_id, $field);

// Settings page
do_action('wpspaghetti_uftyfacf_field_settings_page_before', $oauth, $this);
do_action('wpspaghetti_uftyfacf_field_settings_page_after', $oauth, $this);
do_action('wpspaghetti_uftyfacf_field_settings_page_authorize_before', $oauth, $this);
do_action('wpspaghetti_uftyfacf_field_settings_page_authorize_after', $oauth, $this);
do_action('wpspaghetti_uftyfacf_field_settings_page_authorized_before', $oauth, $this);
do_action('wpspaghetti_uftyfacf_field_settings_page_authorized_after', $oauth, $this);
do_action('wpspaghetti_uftyfacf_field_settings_page_error_before', $oauth, $this);
do_action('wpspaghetti_uftyfacf_field_settings_page_error_after', $oauth, $this);

// Admin settings
do_action('wpspaghetti_uftyfacf_field_admin_init_save_settings', $_POST);

// AJAX actions
do_action('wpspaghetti_uftyfacf_field_wp_ajax_get_youtube_upload_url_before', $post_id, $field_key, $field);
do_action('wpspaghetti_uftyfacf_field_wp_ajax_get_youtube_upload_url_after', $upload_url, $post_id, $field);
do_action('wpspaghetti_uftyfacf_field_wp_ajax_get_youtube_upload_url_error', $exception, $post_id, $field);
do_action('wpspaghetti_uftyfacf_field_wp_ajax_upload_video_to_youtube_before', $post_id, $field, $uploaded_file);
do_action('wpspaghetti_uftyfacf_field_wp_ajax_upload_video_to_youtube_after', $video_id, $post_id, $field);
do_action('wpspaghetti_uftyfacf_field_wp_ajax_upload_video_to_youtube_error', $exception, $post_id, $field_key);
do_action('wpspaghetti_uftyfacf_field_wp_ajax_get_video_id_from_upload_before', $upload_id, $post_id, $field_key);
do_action('wpspaghetti_uftyfacf_field_wp_ajax_get_video_id_from_upload_after', $video_id, $upload_id, $post_id, $field_key);
do_action('wpspaghetti_uftyfacf_field_wp_ajax_get_video_id_from_upload_error', $exception, $video_id, $upload_id, $post_id, $field_key);
do_action('wpspaghetti_uftyfacf_field_wp_ajax_save_youtube_video_id_before', $post_id, $video_id, $field_key);
do_action('wpspaghetti_uftyfacf_field_wp_ajax_save_youtube_video_id_after', $post_id, $video_id, $field_key);
do_action('wpspaghetti_uftyfacf_field_wp_ajax_save_youtube_video_id_error', $exception, $post_id, $video_id, $field_key);
do_action('wpspaghetti_uftyfacf_field_wp_ajax_get_videos_by_playlist_before', $field_key, $field, $playlist_id);
do_action('wpspaghetti_uftyfacf_field_wp_ajax_get_videos_by_playlist_after', $field_key, $field, $playlist_id, $result);
do_action('wpspaghetti_uftyfacf_field_wp_ajax_get_videos_by_playlist_error', $exception, $field_key, $field, $playlist_id);

// Utility actions
do_action('wpspaghetti_uftyfacf_field_save_video_id_to_field_before', $post_id, $video_id, $field_key);
do_action('wpspaghetti_uftyfacf_field_save_video_id_to_field_after', $post_id, $video_id, $field_key);
```

</details>

<details>
<summary>ActivationService Class</summary>

```php
// Plugin activation
do_action('wpspaghetti_uftyfacf_activationservice_activate_before');
do_action('wpspaghetti_uftyfacf_activationservice_activate_after');
do_action('wpspaghetti_uftyfacf_activationservice_activate_error', $exception);

// MU-plugin activation
do_action('wpspaghetti_uftyfacf_activationservice_activate_mu_plugin_before');
do_action('wpspaghetti_uftyfacf_activationservice_activate_mu_plugin_after');
do_action('wpspaghetti_uftyfacf_activationservice_activate_mu_plugin_error', $exception);
```

</details>

<details>
<summary>DeactivationService Class</summary>

```php
// Plugin deactivation
do_action('wpspaghetti_uftyfacf_deactivationservice_deactivate_before', $network_deactivating);
do_action('wpspaghetti_uftyfacf_deactivationservice_deactivate_after', $network_deactivating);
do_action('wpspaghetti_uftyfacf_deactivationservice_deactivate_error', $exception, $network_deactivating);
```

</details>

<details>
<summary>CacheHandler Class</summary>

```php
// Token management
do_action('wpspaghetti_uftyfacf_cachehandler_get_access_token_before', $cache_info);
do_action('wpspaghetti_uftyfacf_cachehandler_get_access_token_after', $token, $cache_info);
do_action('wpspaghetti_uftyfacf_cachehandler_save_access_token_before', $token);
do_action('wpspaghetti_uftyfacf_cachehandler_save_access_token_after', $token, $cache_info);
do_action('wpspaghetti_uftyfacf_cachehandler_save_access_token_error', $token, $cache_info);
do_action('wpspaghetti_uftyfacf_cachehandler_save_access_token_invalid_token_format', $token);
do_action('wpspaghetti_uftyfacf_cachehandler_delete_access_token_before', $cache_info);
do_action('wpspaghetti_uftyfacf_cachehandler_delete_access_token_after', $cache_info);

// Cache management
do_action('wpspaghetti_uftyfacf_cachehandler_cache_info_after', $cache_info);
do_action('wpspaghetti_uftyfacf_cachehandler_clear_all_caches_before');
do_action('wpspaghetti_uftyfacf_cachehandler_clear_all_caches_after', $cache_clear_methods);
do_action('wpspaghetti_uftyfacf_cachehandler_aggressive_cache_clear_before');
do_action('wpspaghetti_uftyfacf_cachehandler_aggressive_cache_clear_after');
```

</details>

<details>
<summary>CronService Class</summary>

```php
// Cron scheduling
do_action('wpspaghetti_uftyfacf_cronservice_schedule_before', $hook, $schedule);
do_action('wpspaghetti_uftyfacf_cronservice_schedule_after', $hook, $schedule, $result);
do_action('wpspaghetti_uftyfacf_cronservice_schedule_error', $hook, $schedule);

// Cron unscheduling
do_action('wpspaghetti_uftyfacf_cronservice_unschedule_before', $hook, $timestamp);
do_action('wpspaghetti_uftyfacf_cronservice_unschedule_after', $hook, $timestamp);
do_action('wpspaghetti_uftyfacf_cronservice_unschedule_error', $hook, $timestamp);

// Cron execution
do_action('wpspaghetti_uftyfacf_cronservice_execute_cron_before');
do_action('wpspaghetti_uftyfacf_cronservice_execute_cron_after');
do_action('wpspaghetti_uftyfacf_cronservice_execute_cron_error', $exception);

// Cron rescheduling
do_action('wpspaghetti_uftyfacf_cronservice_reschedule_before', $new_schedule);
do_action('wpspaghetti_uftyfacf_cronservice_reschedule_after', $new_schedule, $result);

// Cron hook callback (actual cron event that fires)
do_action('wpspaghetti_uftyfacf_cron');
```

</details>

<details>
<summary>GoogleClientManager Class</summary>

```php
// Google Client configuration
do_action('wpspaghetti_uftyfacf_googleclientmanager_set_google_client_after', $client);
```

</details>

<details>
<summary>MigrationService Class</summary>

```php
// Migration
do_action('wpspaghetti_uftyfacf_migrationservice_migrate_all_before');
do_action('wpspaghetti_uftyfacf_migrationservice_migrate_all_after');

// Options migration
do_action('wpspaghetti_uftyfacf_migrationservice_migrate_options_before');
do_action('wpspaghetti_uftyfacf_migrationservice_migrate_options_after', $migrations, $migrated_count);

// Single option migration
do_action('wpspaghetti_uftyfacf_migrationservice_migrate_option_before', $old_key, $new_key);
do_action('wpspaghetti_uftyfacf_migrationservice_migrate_option_after', $old_key, $new_key, $old_value);
do_action('wpspaghetti_uftyfacf_migrationservice_migrate_option_error', $old_key, $new_key, $old_value);

// Scheduled events migration
do_action('wpspaghetti_uftyfacf_migrationservice_migrate_scheduled_events_before');
do_action('wpspaghetti_uftyfacf_migrationservice_migrate_scheduled_events_after', $migrations, $migrated_count);

// Single scheduled event migration
do_action('wpspaghetti_uftyfacf_migrationservice_migrate_scheduled_event_before', $old_hook, $new_hook);
do_action('wpspaghetti_uftyfacf_migrationservice_migrate_scheduled_event_after', $old_hook, $new_hook, $schedule);
do_action('wpspaghetti_uftyfacf_migrationservice_migrate_scheduled_event_error', $old_hook, $new_hook);
```

</details>

<details>
<summary>YoutubeApiService Class</summary>

```php
// Playlist operations
do_action('wpspaghetti_uftyfacf_youtubeapiservice_get_playlists_by_privacy_status_before', $privacy_status);
do_action('wpspaghetti_uftyfacf_youtubeapiservice_get_playlists_by_privacy_status_after', $privacy_status, $result);
do_action('wpspaghetti_uftyfacf_youtubeapiservice_get_playlists_by_privacy_status_error', $exception, $privacy_status);

// Upload operations
do_action('wpspaghetti_uftyfacf_youtubeapiservice_get_youtube_upload_url_before', $post_id, $field);
do_action('wpspaghetti_uftyfacf_youtubeapiservice_get_youtube_upload_url_after', $upload_url, $post_id, $field);
do_action('wpspaghetti_uftyfacf_youtubeapiservice_upload_video_to_youtube_before', $post_id, $field, $file_data);
do_action('wpspaghetti_uftyfacf_youtubeapiservice_upload_video_to_youtube_after', $video_id, $post_id, $field);
do_action('wpspaghetti_uftyfacf_youtubeapiservice_get_video_id_from_upload_before', $upload_id);
do_action('wpspaghetti_uftyfacf_youtubeapiservice_get_video_id_from_upload_after', $video_id, $upload_id);

// Video management
do_action('wpspaghetti_uftyfacf_youtubeapiservice_get_videos_by_playlist_before', $playlist_id, $privacy_status);
do_action('wpspaghetti_uftyfacf_youtubeapiservice_get_videos_by_playlist_after', $playlist_id, $privacy_status, $response, $result);

// Resumable uploads
do_action('wpspaghetti_uftyfacf_youtubeapiservice_create_resumable_upload_request_before', $post_id, $field);
do_action('wpspaghetti_uftyfacf_youtubeapiservice_create_resumable_upload_request_after', $request_data, $post_id, $field);
```

</details>

## Filters

<details>
<summary>Container Configuration</summary>

```php
// Container definitions
apply_filters('wpspaghetti_uftyfacf_container_definitions', $definitions);

// Field defaults
apply_filters('wpspaghetti_uftyfacf_field_defaults', $defaults);

// Environment settings
apply_filters('wpspaghetti_uftyfacf_env_settings', $settings);

// MIME types
apply_filters('wpspaghetti_uftyfacf_allowed_video_mime_types', $mime_types);

// HTML tags
apply_filters('wpspaghetti_uftyfacf_allowed_html', $allowed_html);
```

</details>

<details>
<summary>Bootstrap Class</summary>

```php
// Field instance filtering
apply_filters('wpspaghetti_uftyfacf_bootstrap_init_field_instance', $field_instance);
```

</details>

<details>
<summary>Field Class</summary>

```php
// Field rendering
apply_filters('wpspaghetti_uftyfacf_field_render_field_field', $field);

// Field validation
apply_filters('wpspaghetti_uftyfacf_field_validate_value_should_update', $should_update, $value, $field, $input, $post_id);

// Field updates  
apply_filters('wpspaghetti_uftyfacf_field_update_value_should_update', $should_update, $value, $post_id, $field);
apply_filters('wpspaghetti_uftyfacf_field_update_value_data', $data, $value, $post_id, $field);

// Post deletion
apply_filters('wpspaghetti_uftyfacf_field_before_delete_post_should_delete', $api_delete_on_post_delete, $post_id, $field);

// AJAX field retrieval
apply_filters('wpspaghetti_uftyfacf_field_wp_ajax_get_youtube_upload_url_field', $field_object);
```

</details>

<details>
<summary>CacheHandler Class</summary>

```php
// Cache information
apply_filters('wpspaghetti_uftyfacf_cachehandler_cache_info', $cache_info);
apply_filters('wpspaghetti_uftyfacf_cachehandler_cache_clear_methods', $cache_clear_methods);

// Token management
apply_filters('wpspaghetti_uftyfacf_cachehandler_get_access_token_token', $token, $cache_info);
apply_filters('wpspaghetti_uftyfacf_cachehandler_save_access_token_token', $token);
```

</details>

<details>
<summary>CronService Class</summary>

```php
// Cron schedule customization
apply_filters('wpspaghetti_uftyfacf_cronservice_get_cron_schedule', $schedule);

// Cron status information
apply_filters('wpspaghetti_uftyfacf_cronservice_get_status', $status);
```

</details>

<details>
<summary>GoogleClientManager Class</summary>

```php
// Google Client configuration
apply_filters('wpspaghetti_uftyfacf_googleclientmanager_set_google_client_data', $data);
apply_filters('wpspaghetti_uftyfacf_googleclientmanager_set_google_client_scopes', $scopes);
apply_filters('wpspaghetti_uftyfacf_googleclientmanager_set_google_client_client', $client);
```

</details>

<details>
<summary>MigrationService Class</summary>

```php
// Add custom option migrations
apply_filters('wpspaghetti_uftyfacf_migrationservice_migrate_options_migrations', $migrations);

// Add custom scheduled event migrations
apply_filters('wpspaghetti_uftyfacf_migrationservice_migrate_scheduled_events_migrations', $migrations);
```

</details>

## Logging Configuration

**Upload Field to YouTube for ACF** includes comprehensive logging through the integrated [WP Logger](https://github.com/wp-spaghetti/wp-logger) library.

For detailed information about log levels, log files, and advanced logging configuration, see the [WP Logger documentation](https://github.com/wp-spaghetti/wp-logger).

## External Services

This plugin connects to the **YouTube Data API v3** provided by Google LLC to upload and manage videos on YouTube.

### What data is sent

* Video files selected by the user through the ACF field
* Video metadata (title, description, tags, privacy settings, category)
* OAuth authentication tokens for YouTube channel access
* User's YouTube channel information

### When data is sent

* During video upload operations initiated by the user
* When retrieving video information from YouTube playlists
* During OAuth authentication and token refresh processes
* When fetching playlist data from the user's YouTube channel

### Service Information

* **Service Provider:** Google LLC
* **API Endpoint:** https://www.googleapis.com/upload/youtube/v3/
* **Terms of Service:** https://developers.google.com/youtube/terms/api-services-terms-of-service
* **Privacy Policy:** https://policies.google.com/privacy

This information is provided for legal compliance and transparency. Users should review Google's terms and privacy policy before using this plugin to upload content to YouTube.


## More info

See [LINKS](docs/LINKS.md) file.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for a detailed list of changes for each release.

We follow [Semantic Versioning](https://semver.org/) and use [Conventional Commits](https://www.conventionalcommits.org/) to automatically generate our changelog.

### Release Process

- **Major versions** (1.0.0 → 2.0.0): Breaking changes
- **Minor versions** (1.0.0 → 1.1.0): New features, backward compatible
- **Patch versions** (1.0.0 → 1.0.1): Bug fixes, backward compatible

All releases are automatically created when changes are pushed to the `main` branch, based on commit message conventions.

## Contributing

For your contributions please use:

- [Conventional Commits](https://www.conventionalcommits.org)
- [git-flow workflow](https://danielkummer.github.io/git-flow-cheatsheet/)
- [Pull request workflow](https://docs.github.com/en/get-started/exploring-projects-on-github/contributing-to-a-project)

See [CONTRIBUTING](.github/CONTRIBUTING.md) for detailed guidelines.

## Sponsor

[<img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" width="200" alt="Buy Me A Coffee">](https://buymeacoff.ee/frugan)

## License

(ɔ) Copyleft 2025 [Frugan](https://frugan.it).  
[GNU GPLv3](https://choosealicense.com/licenses/gpl-3.0/), see [LICENSE](LICENSE) file.
