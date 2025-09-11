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

namespace WpSpaghetti\UFTYFACF\Tests\Trait;

use DI\Container;
use PHPUnit\Framework\TestCase;
use WpSpaghetti\UFTYFACF\Trait\BaseHookTrait;

/**
 * Test case for BaseHookTrait using WP_Mock.
 *
 * @internal
 *
 * @coversNothing
 */
final class BaseHookTraitTest extends TestCase
{
    private $testClass;
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        \WP_Mock::setUp();

        // Create a mock container using Mockery
        $this->container = \Mockery::mock(Container::class);
        $this->container->shouldReceive('get')
            ->with('plugin_prefix')
            ->andReturn('wpspaghetti_uftyfacf')
            ->byDefault()
        ;

        // Create anonymous class using the trait
        $this->testClass = new class($this->container) {
            use BaseHookTrait;

            public function __construct(Container $container)
            {
                $this->init_hook($container);
            }

            public function getHookPrefix(): string
            {
                return $this->get_hook_prefix();
            }

            public function testDoAction(string $action, ...$args): void
            {
                $this->do_action($action, ...$args);
            }

            public function testApplyFilters(string $filter, $value, ...$args)
            {
                return $this->apply_filters($filter, $value, ...$args);
            }

            public function testRemoveAction(string $action, callable $callback, int $priority = 10): bool
            {
                return $this->remove_action($action, $callback, $priority);
            }

            public function testRemoveFilter(string $filter, callable $callback, int $priority = 10): bool
            {
                return $this->remove_filter($filter, $callback, $priority);
            }
        };
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        \WP_Mock::tearDown();
        parent::tearDown();
    }

    public function testInitHookSetsCorrectPrefix(): void
    {
        $prefix = $this->testClass->getHookPrefix();

        self::assertStringStartsWith('wpspaghetti_uftyfacf_', $prefix);
        self::assertStringEndsWith('_', $prefix);
        self::assertStringContainsString('class@anonymous', $prefix);
    }

    /**
     * @group fixme
     */
    public function testDoActionFiresWordPressAction(): void
    {
        $action_name = 'test_action';
        $arg1 = 'argument1';
        $arg2 = 'argument2';
        $expected_hook = $this->testClass->getHookPrefix().$action_name;

        \WP_Mock::expectAction($expected_hook, $arg1, $arg2);
        $this->testClass->testDoAction($action_name, $arg1, $arg2);
        $this->addToAssertionCount(1);
    }

    /**
     * @group fixme
     */
    public function testApplyFiltersCallsWordPressFilter(): void
    {
        $filter_name = 'test_filter';
        $initial_value = 'initial';
        $expected_value = 'filtered';
        $extra_arg = 'extra';

        // Get the expected hook name using the class prefix
        $expected_hook = $this->testClass->getHookPrefix().$filter_name;

        // Mock the apply_filters function to return the expected value
        WP_Mock::onFilter($expected_hook)
            ->with($initial_value, $extra_arg)
            ->reply($expected_value)
        ;

        $result = $this->testClass->testApplyFilters($filter_name, $initial_value, $extra_arg);

        self::assertSame($expected_value, $result);
    }

    /**
     * @group fixme
     */
    public function testRemoveActionCallsWordPressFunction(): void
    {
        $action_name = 'test_action';
        $callback = static fn () => 'test';
        $priority = 15;

        // Get the expected hook name using the class prefix
        $expected_hook = $this->testClass->getHookPrefix().$action_name;

        // Mock the remove_action function to return true
        \WP_Mock::userFunction('remove_action')
            ->once()
            ->with($expected_hook, $callback, $priority)
            ->andReturn(true)
        ;

        $result = $this->testClass->testRemoveAction($action_name, $callback, $priority);

        self::assertTrue($result);
    }

    /**
     * @group fixme
     */
    public function testRemoveFilterCallsWordPressFunction(): void
    {
        $filter_name = 'test_filter';
        $callback = static fn ($value) => $value;
        $priority = 20;

        // Get the expected hook name using the class prefix
        $expected_hook = $this->testClass->getHookPrefix().$filter_name;

        // Mock the remove_filter function to return true
        \WP_Mock::userFunction('remove_filter')
            ->once()
            ->with($expected_hook, $callback, $priority)
            ->andReturn(true)
        ;

        $result = $this->testClass->testRemoveFilter($filter_name, $callback, $priority);

        self::assertTrue($result);
    }

    public function testGetShortNameReturnsCorrectValue(): void
    {
        $prefix = $this->testClass->getHookPrefix();

        // Should contain the class name in lowercase
        self::assertStringContainsString('class@anonymous', $prefix);
    }
}
