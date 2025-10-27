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
 * Service for managing WordPress cron jobs.
 *
 * Centralizes all cron-related operations including scheduling,
 * unscheduling, status checks, and hook management.
 */
class CronService
{
    use HookTrait;

    /**
     * @var array<string, mixed>
     */
    private array $env;

    public function __construct(
        private Container $container,
        private Logger $logger
    ) {
        $this->init_hook($this->container);
        $this->env = $this->container->get('env_settings');

        // Register cron callback
        /** @psalm-suppress HookNotFound */
        add_action($this->get_cron_hook(), [$this, 'execute_cron']);
    }

    /**
     * Schedule the cron event.
     *
     * @return bool true if scheduled successfully, false otherwise
     */
    public function schedule(): bool
    {
        $hook = $this->get_cron_hook();
        $schedule = $this->get_cron_schedule();

        $this->do_action(__FUNCTION__.'_before', $hook, $schedule);

        if (wp_next_scheduled($hook)) {
            $this->logger->info('Cron event already scheduled', [
                'hook' => $hook,
                'next_run' => wp_next_scheduled($hook),
            ]);

            return true;
        }

        $result = wp_schedule_event(time(), $schedule, $hook);

        if (false === $result) {
            $this->logger->error('Failed to schedule cron event', [
                'hook' => $hook,
                'schedule' => $schedule,
                'default_schedule' => $this->env['cron_schedule'],
            ]);

            $this->do_action(__FUNCTION__.'_error', $hook, $schedule);

            return false;
        }

        $this->logger->info('Cron event scheduled successfully', [
            'hook' => $hook,
            'schedule' => $schedule,
            'next_run' => wp_next_scheduled($hook),
            'default_schedule' => $this->env['cron_schedule'],
        ]);

        $this->do_action(__FUNCTION__.'_after', $hook, $schedule, $result);

        return true;
    }

    /**
     * Unschedule the cron event.
     *
     * @return bool true if unscheduled successfully, false otherwise
     */
    public function unschedule(): bool
    {
        $hook = $this->get_cron_hook();
        $timestamp = wp_next_scheduled($hook);

        $this->do_action(__FUNCTION__.'_before', $hook, $timestamp);

        if (!$timestamp) {
            $this->logger->info('Cron event not scheduled', ['hook' => $hook]);

            return true;
        }

        $result = wp_unschedule_event($timestamp, $hook);

        if (false === $result) {
            $this->logger->warning('Failed to unschedule cron event', [
                'hook' => $hook,
                'timestamp' => $timestamp,
            ]);

            $this->do_action(__FUNCTION__.'_error', $hook, $timestamp);

            return false;
        }

        $this->logger->info('Cron event unscheduled successfully', [
            'hook' => $hook,
            'timestamp' => $timestamp,
        ]);

        $this->do_action(__FUNCTION__.'_after', $hook, $timestamp);

        return true;
    }

    /**
     * Execute the cron job callback.
     *
     * This is the actual callback that runs when the cron fires.
     */
    public function execute_cron(): void
    {
        $this->do_action(__FUNCTION__.'_before');

        try {
            // Token maintenance
            $google_client_manager = $this->container->get(GoogleClientManager::class);
            $google_client_manager->check_oauth_token();

            // Add more maintenance tasks here...

            $this->logger->info('Cron job executed successfully');
        } catch (\Exception $exception) {
            $this->logger->error($exception);

            $this->do_action(__FUNCTION__.'_error', $exception);
        }

        $this->do_action(__FUNCTION__.'_after');
    }

    /**
     * Get the cron hook name.
     *
     * @return string the cron hook name
     */
    public function get_cron_hook(): string
    {
        return $this->container->get('plugin_prefix').'_cron';
    }

    /**
     * Get the cron schedule for OAuth token checks.
     *
     * @return string the cron schedule identifier
     */
    public function get_cron_schedule(): string
    {
        // Allow customization via filter
        $schedule = $this->apply_filters(__FUNCTION__, $this->env['cron_schedule']);

        // Validate schedule exists in WordPress
        $schedules = wp_get_schedules();
        if (!isset($schedules[$schedule])) {
            $this->logger->warning('Invalid cron schedule specified, falling back to daily', [
                'requested_schedule' => $schedule,
                'available_schedules' => array_keys($schedules),
                'default_schedule' => $this->env['cron_schedule'],
            ]);
            $schedule = 'daily';
        }

        return $schedule;
    }

    /**
     * Get detailed cron status information.
     *
     * @return array<string, mixed> associative array with cron status details
     */
    public function get_status(): array
    {
        $hook = $this->get_cron_hook();
        $schedule = $this->get_cron_schedule();
        $next_run = wp_next_scheduled($hook);

        $status = [
            'hook' => $hook,
            'schedule' => $schedule,
            'default_schedule' => $this->env['cron_schedule'],
            'is_scheduled' => (bool) $next_run,
            'next_run' => $next_run,
            'next_run_formatted' => $next_run ? wp_date('Y-m-d H:i:s', $next_run) : null,
            'available_schedules' => array_keys(wp_get_schedules()),
            'debug' => $this->env['debug'],
        ];

        // Allow third parties to add info
        return $this->apply_filters(__FUNCTION__, $status);
    }

    /**
     * Reschedule the cron event with a new schedule.
     *
     * @param string $new_schedule the new schedule identifier
     *
     * @return bool true if rescheduled successfully, false otherwise
     */
    public function reschedule(string $new_schedule): bool
    {
        $this->do_action(__FUNCTION__.'_before', $new_schedule);

        if (!$this->unschedule()) {
            return false;
        }

        // Temporarily override the schedule
        $old_schedule = $this->env['cron_schedule'];
        $this->env['cron_schedule'] = $new_schedule;

        $result = $this->schedule();

        // Restore original schedule
        $this->env['cron_schedule'] = $old_schedule;

        $this->do_action(__FUNCTION__.'_after', $new_schedule, $result);

        return $result;
    }
}
