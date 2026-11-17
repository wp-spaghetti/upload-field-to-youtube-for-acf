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
 * Test case for Field class using WP_Mock.
 *
 * @internal
 *
 * @coversNothing
 */
final class FieldTest extends TestCase
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
    public function testFieldExtendsAcfField(): void
    {
        self::assertInstanceOf(\acf_field::class, $this->field);
    }

    /**
     * @group fixme
     */
    public function testFieldHasCorrectProperties(): void
    {
        $reflection = new \ReflectionClass($this->field);

        self::assertTrue($reflection->hasProperty('name'));
        self::assertTrue($reflection->hasProperty('label'));
        self::assertTrue($reflection->hasProperty('category'));
    }

    /**
     * @group fixme
     */
    public function testRenderFieldCreatesOutput(): void
    {
        $field = [
            'name' => 'test_field',
            'value' => '',
        ];

        ob_start();
        $this->field->render_field($field);
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertNotEmpty($output);
    }

    /**
     * @group fixme
     */
    public function testRenderFieldSettingsCallsAcfFunctions(): void
    {
        $field = ['name' => 'test_field'];

        \WP_Mock::userFunction('acf_render_field_setting')
            ->atLeast()
            ->once()
        ;

        $this->field->render_field_settings($field);

        $this->addToAssertionCount(1);
    }

    /**
     * @group fixme
     */
    public function testValidateValueWithValidInput(): void
    {
        $valid = true;
        $value = 'test_value';
        $field = ['name' => 'test_field'];
        $input = 'test_input';

        $result = $this->field->validate_value($valid, $value, $field, $input);

        self::assertTrue($result);
    }

    /**
     * @group fixme
     */
    public function testUpdateValueMethod(): void
    {
        $value = 'test_value';
        $post_id = 1;
        $field = ['name' => 'test_field'];

        $result = $this->field->update_value($value, $post_id, $field);

        self::assertSame($value, $result);
    }

    /**
     * @group fixme
     */
    public function testActivateRegistersHooks(): void
    {
        \WP_Mock::expectActionAdded('acf/include_field_types', [$this->field, 'include_field_types']);

        $this->field->activate();

        $this->addToAssertionCount(1);
    }

    /**
     * @group fixme
     */
    public function testDeactivateUnregistersHooks(): void
    {
        \WP_Mock::userFunction('remove_action')
            ->with('acf/include_field_types', [$this->field, 'include_field_types'])
            ->once()
            ->andReturn(true)
        ;

        $result = $this->field->deactivate();

        self::assertTrue($result);
    }

    /**
     * @group fixme
     */
    public function testInputAdminEnqueueScriptsEnqueuesAssets(): void
    {
        \WP_Mock::userFunction('wp_enqueue_script')
            ->atLeast()
            ->once()
        ;

        \WP_Mock::userFunction('wp_enqueue_style')
            ->atLeast()
            ->once()
        ;

        $this->field->input_admin_enqueue_scripts();

        $this->addToAssertionCount(1);
    }

    /**
     * @group fixme
     */
    public function testBeforeDeletePostWithValidPost(): void
    {
        $post_id = 1;

        \WP_Mock::userFunction('get_fields')
            ->with($post_id)
            ->once()
            ->andReturn([])
        ;

        $this->field->before_delete_post($post_id);

        $this->addToAssertionCount(1);
    }

    /**
     * @group fixme
     */
    public function testAdminNoticesWithNoPageParameter(): void
    {
        $_GET = [];

        ob_start();
        $this->field->admin_notices();
        $output = ob_get_clean();

        self::assertEmpty($output);
    }

    /**
     * @group fixme
     */
    public function testFieldDefaultsAreCorrect(): void
    {
        $reflection = new \ReflectionClass($this->field);
        $defaults_property = $reflection->getProperty('defaults');
        $defaults_property->setAccessible(true);
        $defaults = $defaults_property->getValue($this->field);

        self::assertIsArray($defaults);
        self::assertArrayHasKey('instructions', $defaults);
        self::assertArrayHasKey('required', $defaults);
    }

    /**
     * @group fixme
     */
    public function testFieldUsesBaseHookTrait(): void
    {
        $traits = class_uses($this->field);

        self::assertContains(BaseHookTrait::class, $traits);
    }
}
