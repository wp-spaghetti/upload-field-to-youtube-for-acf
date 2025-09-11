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

namespace WpSpaghetti\UFTYFACF\Tests\Service;

use DI\Container;
use PHPUnit\Framework\TestCase;
use WpSpaghetti\UFTYFACF\Service\CacheHandler;
use WpSpaghetti\WpLogger\Logger;

/**
 * Basic test for CacheHandler class.
 *
 * @internal
 *
 * @coversNothing
 */
final class CacheHandlerTest extends TestCase
{
    private Container $container;
    private Logger $logger;
    private CacheHandler $cache_handler;

    protected function setUp(): void
    {
        parent::setUp();
        \WP_Mock::setUp();

        // Mock WordPress hook functions
        \WP_Mock::userFunction('do_action')
            ->zeroOrMoreTimes()
            ->andReturnNull()
        ;

        \WP_Mock::userFunction('apply_filters')
            ->zeroOrMoreTimes()
            ->andReturnUsing(static function ($hook, $value) {
                // Always return the original value to prevent null returns
                return $value;
            })
        ;

        // Mock WordPress cache functions
        \WP_Mock::userFunction('wp_cache_flush')
            ->zeroOrMoreTimes()
            ->andReturn(true)
        ;

        \WP_Mock::userFunction('wp_using_ext_object_cache')
            ->zeroOrMoreTimes()
            ->andReturn(false)
        ;

        // Mock plugin detection functions
        \WP_Mock::userFunction('is_plugin_active')
            ->zeroOrMoreTimes()
            ->andReturn(false)
        ;

        // Mock WordPress option functions
        \WP_Mock::userFunction('get_option')
            ->zeroOrMoreTimes()
            ->andReturn(false)
        ;

        \WP_Mock::userFunction('update_option')
            ->zeroOrMoreTimes()
            ->andReturn(true)
        ;

        \WP_Mock::userFunction('delete_option')
            ->zeroOrMoreTimes()
            ->andReturn(true)
        ;

        // Mock WordPress constants that might be used in cache detection
        if (!\defined('W3TC')) {
            \define('W3TC', false);
        }
        if (!\defined('WP_CACHE')) {
            \define('WP_CACHE', false);
        }

        // Create mock services using Mockery
        $this->container = \Mockery::mock(Container::class);
        $this->logger = \Mockery::mock(Logger::class);

        // Configure logger expectations for common methods
        $this->logger->shouldReceive('warning')
            ->zeroOrMoreTimes()
            ->andReturnNull()
        ;

        $this->logger->shouldReceive('error')
            ->zeroOrMoreTimes()
            ->andReturnNull()
        ;

        $this->logger->shouldReceive('debug')
            ->zeroOrMoreTimes()
            ->andReturnNull()
        ;

        $this->logger->shouldReceive('info')
            ->zeroOrMoreTimes()
            ->andReturnNull()
        ;

        // Configure container mock
        $this->container->shouldReceive('get')
            ->with('plugin_prefix')
            ->andReturn('wpspaghetti_uftyfacf')
            ->byDefault()
        ;

        $this->container->shouldReceive('get')
            ->with('plugin_undername')
            ->andReturn('upload_field_to_youtube_for_acf')
            ->byDefault()
        ;

        // Create CacheHandler instance
        $this->cache_handler = new CacheHandler($this->container, $this->logger);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        \WP_Mock::tearDown();
        parent::tearDown();
    }

    /**
     * @group fixme
     */
    public function testGetAccessTokenReturnsNull(): void
    {
        // Mock get_option for this specific test
        \WP_Mock::userFunction('get_option')
            ->once()
            ->andReturn(null)
        ;

        $result = $this->cache_handler->get_access_token();

        self::assertNull($result);
    }

    /**
     * @group fixme
     */
    public function testSaveAccessTokenWithInvalidData(): void
    {
        $invalid_token = 'invalid_data';

        // Mock logger error method for this specific test
        $this->logger->shouldReceive('error')
            ->once()
            ->with('Attempting to save invalid token format', \Mockery::type('array'))
            ->andReturnNull()
        ;

        $result = $this->cache_handler->save_access_token($invalid_token);

        self::assertFalse($result);
    }

    /**
     * @group fixme
     */
    public function testSaveAccessTokenWithValidData(): void
    {
        $valid_token = [
            'access_token' => 'test_token',
            'refresh_token' => 'test_refresh',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ];

        // Configure logger expectations for error method
        $this->logger->shouldReceive('error')
            ->never()
        ;

        // Mock WordPress functions for this specific test
        \WP_Mock::userFunction('update_option')
            ->once()
            ->andReturn(true)
        ;

        $result = $this->cache_handler->save_access_token($valid_token);

        self::assertTrue($result);
    }

    /**
     * @group fixme
     */
    public function testDeleteAccessTokenExecutesSuccessfully(): void
    {
        // Configure logger expectations
        $this->logger->shouldReceive('warning')
            ->never()
        ;

        $this->logger->shouldReceive('error')
            ->never()
        ;

        // Mock WordPress delete_option function
        \WP_Mock::userFunction('delete_option')
            ->once()
            ->andReturn(true)
        ;

        $result = $this->cache_handler->delete_access_token();

        self::assertTrue($result);
    }

    /**
     * Alternative test method that doesn't try to mock internal PHP functions.
     *
     * @group fixme
     */
    public function testGetCacheInfoDetectsNoCache(): void
    {
        // This test checks the get_cache_info method indirectly through get_access_token

        // Mock WordPress functions
        \WP_Mock::userFunction('wp_using_ext_object_cache')
            ->once()
            ->andReturn(false)
        ;

        \WP_Mock::userFunction('get_option')
            ->once()
            ->andReturn(null)
        ;

        // Configure logger to expect no warnings for this specific case
        $this->logger->shouldReceive('warning')
            ->never()
        ;

        $result = $this->cache_handler->get_access_token();

        // The result should be null and no cache should be detected
        self::assertNull($result);
    }

    public function testCacheHandlerIsInstantiable(): void
    {
        self::assertInstanceOf(CacheHandler::class, $this->cache_handler);
    }

    public function testCacheHandlerHasRequiredMethods(): void
    {
        self::assertTrue(method_exists($this->cache_handler, 'get_access_token'));
        self::assertTrue(method_exists($this->cache_handler, 'save_access_token'));
        self::assertTrue(method_exists($this->cache_handler, 'delete_access_token'));
        self::assertTrue(method_exists($this->cache_handler, 'get_cache_info'));
    }
}
