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
 * Service for handling plugin activation.
 *
 * Centralizes all activation logic including cron scheduling,
 * migrations, and initial setup.
 */
class ActivationService
{
    use HookTrait;

    public function __construct(
        private Container $container,
        private CronService $cron_service,
        private MigrationService $migration_service,
        private Logger $logger
    ) {
        $this->init_hook($this->container);
    }

    /**
     * Execute plugin activation.
     *
     * @throws \Exception if an error occurs during activation
     */
    public function activate(): void
    {
        $this->do_action(__FUNCTION__.'_before');

        try {
            // Run migrations first
            $this->migration_service->migrate_all();

            // Schedule cron job
            $this->cron_service->schedule();

            $this->logger->info('Plugin activated successfully');

            $this->do_action(__FUNCTION__.'_after');
        } catch (\Exception $exception) {
            $this->logger->error($exception);

            $this->do_action(__FUNCTION__.'_error', $exception);

            throw $exception;
        }
    }

    /**
     * Execute activation for mu-plugin mode.
     *
     * Called during init when running as mu-plugin and not yet activated.
     */
    public function activate_mu_plugin(): void
    {
        $this->do_action(__FUNCTION__.'_before');

        try {
            $this->activate();

            // Mark as activated for mu-plugin
            update_option($this->container->get('plugin_prefix').'_activated', true);

            $this->logger->info('MU-plugin activated successfully');

            $this->do_action(__FUNCTION__.'_after');
        } catch (\Exception $exception) {
            $this->logger->error($exception);

            $this->do_action(__FUNCTION__.'_error', $exception);
        }
    }

    /**
     * Check if the plugin is activated (for mu-plugin mode).
     *
     * @return bool true if activated
     */
    public function is_activated(): bool
    {
        return (bool) get_option($this->container->get('plugin_prefix').'_activated', false);
    }
}
