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
 * Service for handling plugin deactivation cleanup.
 *
 * This service centralizes all deactivation logic to avoid code duplication
 * and can be called safely even when ACF is not available.
 */
class DeactivationService
{
    use HookTrait;

    public function __construct(
        private Container $container,
        private CronService $cron_service,
        private CacheHandler $cache_handler,
        private Logger $logger
    ) {
        $this->init_hook($this->container);
    }

    /**
     * Execute cleanup operations on plugin deactivation.
     *
     * @param bool $network_deactivating whether this is a network deactivation
     *
     * @throws \Exception if an error occurs during deactivation
     */
    public function deactivate(bool $network_deactivating = false): void
    {
        $this->do_action(__FUNCTION__.'_before', $network_deactivating);

        try {
            // Delete access token
            $this->cache_handler->delete_access_token();

            // Unschedule cron
            $this->cron_service->unschedule();

            $this->logger->info('Plugin deactivated successfully', [
                'network_deactivating' => $network_deactivating,
            ]);

            $this->do_action(__FUNCTION__.'_after', $network_deactivating);
        } catch (\Exception $exception) {
            $this->logger->error($exception, [
                'network_deactivating' => $network_deactivating,
            ]);

            $this->do_action(__FUNCTION__.'_error', $exception, $network_deactivating);

            throw $exception;
        }
    }
}
