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

namespace WpSpaghetti\UFTYFACF\Tests;

use DI\Container;
use PHPUnit\Framework\TestCase;
use WpSpaghetti\UFTYFACF\Field;
use WpSpaghetti\UFTYFACF\Service\CacheHandler;
use WpSpaghetti\UFTYFACF\Service\GoogleClientManager;
use WpSpaghetti\UFTYFACF\Service\YoutubeApiService;
use WpSpaghetti\UFTYFACF\Trait\BaseHookTrait;
use WpSpaghetti\WpLogger\Logger;

/**
 * Basic integration test for Field class.
 *
 * @internal
 *
 * @coversNothing
 */
final class FieldIntegrationTest extends TestCase
{
    private Container $container;
    private GoogleClientManager $google_client_manager;
    private YoutubeApiService $youtube_api_service;
    private CacheHandler $cache_handler;
    private Logger $logger;
    private Field $field;

    protected function setUp(): void
    {
        parent::setUp();
        \WP_Mock::setUp();

        // Mock translation functions to ALWAYS return non-empty strings
        \WP_Mock::userFunction('__')
            ->zeroOrMoreTimes()
            ->andReturnUsing(static function ($text, $domain = 'default') {
                if (\is_string($text) && !empty($text)) {
                    return $text;
                }

                return 'mock_translated_text'; // Never return null
            })
        ;

        \WP_Mock::userFunction('esc_html__')
            ->zeroOrMoreTimes()
            ->andReturnUsing(static function ($text, $domain = 'default') {
                if (\is_string($text) && !empty($text)) {
                    return $text;
                }

                return 'mock_escaped_translated_text'; // Never return null
            })
        ;

        \WP_Mock::userFunction('plugin_dir_url')
            ->zeroOrMoreTimes()
            ->andReturn('http://example.com/wp-content/plugins/upload-field-to-youtube-for-acf/')
        ;

        // Mock WordPress hook functions
        \WP_Mock::userFunction('do_action')
            ->zeroOrMoreTimes()
            ->andReturnNull()
        ;

        \WP_Mock::userFunction('apply_filters')
            ->zeroOrMoreTimes()
            ->andReturnUsing(static fn ($hook, $value) => $value)
        ;

        \WP_Mock::userFunction('remove_action')
            ->zeroOrMoreTimes()
            ->andReturn(true)
        ;

        \WP_Mock::userFunction('remove_filter')
            ->zeroOrMoreTimes()
            ->andReturn(true)
        ;

        // Mock ACF functions
        \WP_Mock::userFunction('acf_render_field_setting')
            ->zeroOrMoreTimes()
            ->andReturnNull()
        ;

        // Create mock services using Mockery
        $this->container = \Mockery::mock(Container::class);
        $this->google_client_manager = \Mockery::mock(GoogleClientManager::class);
        $this->youtube_api_service = \Mockery::mock(YoutubeApiService::class);
        $this->cache_handler = \Mockery::mock(CacheHandler::class);
        $this->logger = \Mockery::mock(Logger::class);

        // Configure container mock to return valid strings
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

        // Create Field instance with all required parameters
        $this->field = new Field(
            $this->container,
            $this->google_client_manager,
            $this->youtube_api_service,
            $this->cache_handler,
            $this->logger
        );
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
    public function testFieldIsInstantiable(): void
    {
        self::assertInstanceOf(Field::class, $this->field);
    }

    /**
     * @group fixme
     */
    public function testFieldHasRequiredMethods(): void
    {
        self::assertTrue(method_exists($this->field, 'render_field'));
        self::assertTrue(method_exists($this->field, 'render_field_settings'));
        self::assertTrue(method_exists($this->field, 'validate_value'));
    }

    /**
     * @group fixme
     */
    public function testFieldHasCorrectName(): void
    {
        $reflection = new \ReflectionClass($this->field);
        $name_property = $reflection->getProperty('name');
        $name_property->setAccessible(true);
        $name = $name_property->getValue($this->field);

        self::assertSame('upload_field_to_youtube_for_acf', $name);
    }

    /**
     * @group fixme
     */
    public function testFieldHasValidDefaults(): void
    {
        $reflection = new \ReflectionClass($this->field);
        $defaults_property = $reflection->getProperty('defaults');
        $defaults_property->setAccessible(true);
        $defaults = $defaults_property->getValue($this->field);

        self::assertIsArray($defaults);
        self::assertNotEmpty($defaults);
    }

    /**
     * @group fixme
     */
    public function testFieldImplementsBaseHookTrait(): void
    {
        $traits = class_uses($this->field);

        self::assertContains(BaseHookTrait::class, $traits);
    }
}
