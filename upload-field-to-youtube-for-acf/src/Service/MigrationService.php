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
 * Service for managing data migrations.
 *
 * Handles migration of options and scheduled events from old
 * naming conventions to new ones.
 */
class MigrationService
{
    use HookTrait;

    public function __construct(
        private Container $container,
        private CronService $cron_service,
        private Logger $logger
    ) {
        $this->init_hook($this->container);
    }

    /**
     * Run all migrations.
     */
    public function migrate_all(): void
    {
        $this->do_action(__FUNCTION__.'_before');

        $this->migrate_options();
        $this->migrate_scheduled_events();

        $this->do_action(__FUNCTION__.'_after');
    }

    /**
     * Migrate options from old hyphen format to underscore format.
     */
    public function migrate_options(): void
    {
        $this->do_action(__FUNCTION__.'_before');

        // Define migrations: old_key => new_key
        $migrations = [
            $this->container->get('plugin_name').'__access_token' => $this->container->get('plugin_prefix').'_access_token',
            $this->container->get('plugin_name').'__activated' => $this->container->get('plugin_prefix').'_activated',
        ];

        // Allow third parties to add migrations
        $migrations = $this->apply_filters(__FUNCTION__.'_migrations', $migrations);

        $migrated_count = 0;

        foreach ($migrations as $old_key => $new_key) {
            if ($this->migrate_option($old_key, $new_key)) {
                ++$migrated_count;
            }
        }

        if ($migrated_count > 0) {
            $this->logger->info('Options migrated successfully', [
                'migrated_count' => $migrated_count,
                'total_migrations' => \count($migrations),
            ]);
        }

        $this->do_action(__FUNCTION__.'_after', $migrations, $migrated_count);
    }

    /**
     * Migrate scheduled events from old hooks to new hooks.
     */
    public function migrate_scheduled_events(): void
    {
        $this->do_action(__FUNCTION__.'_before');

        // Define migrations: old_hook => new_hook
        $migrations = [
            $this->container->get('plugin_name').'__check_oauth_token' => $this->cron_service->get_cron_hook(),
            $this->container->get('plugin_prefix').'_field_check_oauth_token' => $this->cron_service->get_cron_hook(),
        ];

        // Allow third parties to add migrations
        $migrations = $this->apply_filters(__FUNCTION__.'_migrations', $migrations);

        $migrated_count = 0;

        foreach ($migrations as $old_hook => $new_hook) {
            if ($this->migrate_scheduled_event($old_hook, $new_hook)) {
                ++$migrated_count;
            }
        }

        if ($migrated_count > 0) {
            $this->logger->info('Scheduled events migrated successfully', [
                'migrated_count' => $migrated_count,
                'total_migrations' => \count($migrations),
            ]);
        }

        $this->do_action(__FUNCTION__.'_after', $migrations, $migrated_count);
    }

    /**
     * Check if migrations are needed.
     *
     * @return bool true if any migrations are pending
     */
    public function needs_migration(): bool
    {
        // Check for old options
        $old_option = get_option($this->container->get('plugin_name').'__access_token');
        if ($old_option) {
            return true;
        }

        // Check for old scheduled events
        $old_hook = $this->container->get('plugin_name').'__check_oauth_token';
        if (wp_next_scheduled($old_hook)) {
            return true;
        }

        $old_hook = $this->container->get('plugin_prefix').'_field_check_oauth_token';
        if (wp_next_scheduled($old_hook)) {
            return true;
        }

        return false;
    }

    /**
     * Migrate a single option from old key to new key.
     *
     * @param string $old_key the old option key
     * @param string $new_key the new option key
     *
     * @return bool true if migrated, false if skipped
     */
    private function migrate_option(string $old_key, string $new_key): bool
    {
        if ($old_key === $new_key) {
            return false;
        }

        $this->do_action(__FUNCTION__.'_before', $old_key, $new_key);

        $old_value = get_option($old_key);

        // Only migrate if old value exists and new key doesn't exist yet
        if ($old_value && !get_option($new_key)) {
            $result = update_option($new_key, $old_value);

            if ($result) {
                delete_option($old_key);

                $this->logger->info('Option migrated', [
                    'old_key' => $old_key,
                    'new_key' => $new_key,
                ]);

                $this->do_action(__FUNCTION__.'_after', $old_key, $new_key, $old_value);

                return true;
            }
            $this->logger->warning('Failed to migrate option', [
                'old_key' => $old_key,
                'new_key' => $new_key,
            ]);

            $this->do_action(__FUNCTION__.'_error', $old_key, $new_key, $old_value);
        }

        return false;
    }

    /**
     * Migrate a scheduled event from old hook to new hook.
     *
     * @param string $old_hook the old hook name
     * @param string $new_hook the new hook name
     *
     * @return bool true if migrated, false if skipped
     */
    private function migrate_scheduled_event(string $old_hook, string $new_hook): bool
    {
        if ($old_hook === $new_hook) {
            return false;
        }

        $this->do_action(__FUNCTION__.'_before', $old_hook, $new_hook);

        $old_timestamp = wp_next_scheduled($old_hook);
        $new_timestamp = wp_next_scheduled($new_hook);

        // Only migrate if old event exists and new event doesn't exist yet
        if ($old_timestamp && !$new_timestamp) {
            // Unschedule old event
            wp_unschedule_event($old_timestamp, $old_hook);

            // Schedule new event
            $schedule = $this->cron_service->get_cron_schedule();
            $result = wp_schedule_event(time(), $schedule, $new_hook);

            if (false !== $result) {
                $this->logger->info('Scheduled event migrated', [
                    'old_hook' => $old_hook,
                    'new_hook' => $new_hook,
                    'schedule' => $schedule,
                ]);

                $this->do_action(__FUNCTION__.'_after', $old_hook, $new_hook, $schedule);

                return true;
            }
            $this->logger->warning('Failed to migrate scheduled event', [
                'old_hook' => $old_hook,
                'new_hook' => $new_hook,
            ]);

            $this->do_action(__FUNCTION__.'_error', $old_hook, $new_hook);
        }

        return false;
    }
}
