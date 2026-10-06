<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\DependencyInjection\SetonoSyliusGiftCardExtension;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGenerator;
use Setono\SyliusGiftCardPlugin\Grid\Filter\GiftCardExpiredFilter;
use Setono\SyliusGiftCardPlugin\Grid\Filter\GiftCardPendingFilter;
use Setono\SyliusGiftCardPlugin\Grid\Filter\GiftCardSpentFilter;
use Setono\SyliusGiftCardPlugin\Twig\Runtime\GiftCardSetupRuntime;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Contracts\Service\ResetInterface;

final class SetonoSyliusGiftCardExtensionTest extends TestCase
{
    /**
     * Sylius ends the product form with render_rest: false, so the checkbox the form extension adds only
     * reaches the request if a UI block renders it. Without the block every product save submits the field
     * as unchecked and silently un-flags the product.
     *
     * @test
     */
    public function it_renders_the_gift_card_checkbox_on_the_product_details_tab(): void
    {
        $blocks = $this->blocksForEvent('sylius.admin.product.tab_details');

        self::assertArrayHasKey('setono_gift_card', $blocks);

        $template = $blocks['setono_gift_card']['template'];
        self::assertSame('@SetonoSyliusGiftCardPlugin/admin/product/_gift_card.html.twig', $template);
        self::assertFileExists($this->resolveTemplate($template));
    }

    /**
     * The service files are not autoconfigured, so a service implementing ResetInterface is only reset between
     * requests under a worker runtime (FrankenPHP's worker mode, RoadRunner) when it carries the kernel.reset tag
     * itself. Without it, whatever it memoised stays for every later request the worker serves
     *
     * @test
     */
    public function it_tags_every_resettable_service_for_the_kernel_to_reset(): void
    {
        $container = new ContainerBuilder();

        (new SetonoSyliusGiftCardExtension())->load([], $container);

        $resettable = [];
        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass() ?? $id;
            if (!str_starts_with($class, 'Setono\\SyliusGiftCardPlugin\\') || !is_a($class, ResetInterface::class, true)) {
                continue;
            }

            $resettable[] = $id;

            self::assertSame(
                [['method' => 'reset']],
                $definition->getTag('kernel.reset'),
                sprintf('%s implements %s, but is not tagged kernel.reset', $id, ResetInterface::class),
            );
        }

        self::assertContains(GiftCardSetupRuntime::class, $resettable);
    }

    /**
     * A code length taken from an environment variable is only known at runtime, and the code generator is what
     * compares code_length with minimum_code_length then, so it has to be handed both rather than fall back on the
     * default minimum
     *
     * @test
     */
    public function it_hands_the_code_generator_both_code_lengths(): void
    {
        $container = new ContainerBuilder();

        (new SetonoSyliusGiftCardExtension())->load([], $container);

        $generator = $container->getDefinition(GiftCardCodeGenerator::class);
        self::assertSame('%setono_sylius_gift_card.code_length%', $generator->getArgument(1));
        self::assertSame('%setono_sylius_gift_card.minimum_code_length%', $generator->getArgument(2));
    }

    /**
     * The status column takes the place of the enabled flag, and both it and the delete button render through the
     * plugin's own templates
     *
     * @test
     */
    public function it_shows_the_status_in_the_gift_card_grid_and_only_offers_to_delete_a_deletable_card(): void
    {
        $config = $this->gridConfig();
        $grid = self::valueAt($config, 'grids', 'setono_sylius_gift_card_admin_gift_card');

        self::assertArrayNotHasKey('enabled', self::arrayAt($grid, 'fields'));
        self::assertSame('.', self::valueAt($grid, 'fields', 'status', 'path'));
        self::assertFileExists($this->resolveTemplate(self::stringAt($grid, 'fields', 'status', 'options', 'template')));

        $deleteType = self::stringAt($grid, 'actions', 'item', 'delete', 'type');
        self::assertSame('setono_sylius_gift_card_gift_card_delete', $deleteType);
        self::assertFileExists($this->resolveTemplate(self::stringAt($config, 'templates', 'action', $deleteType)));
    }

    /**
     * The list query left joins the customer as "customer", so a card without one stays listed when sorting by it, and
     * the amount column shows the whole card, so it names the field to sort by
     *
     * @test
     */
    public function it_sorts_the_gift_card_grid_by_customer_and_amount(): void
    {
        $fields = self::valueAt($this->gridConfig(), 'grids', 'setono_sylius_gift_card_admin_gift_card', 'fields');

        self::assertSame('customer.email', self::valueAt($fields, 'customer', 'sortable'));
        self::assertSame('amount', self::valueAt($fields, 'amount', 'sortable'));
    }

    /**
     * Sylius only applies a default to a grid opened without criteria, and a filter without one is not applied then,
     * so the empty default is what hides the pending cards on the grid an admin opens. Every filter type of the plugin
     * needs a template of its own, or the grid fails to render
     *
     * @test
     */
    public function it_hides_the_pending_cards_by_default_and_renders_every_filter_of_its_own(): void
    {
        $config = $this->gridConfig();
        $filters = self::valueAt($config, 'grids', 'setono_sylius_gift_card_admin_gift_card', 'filters');

        self::assertSame('', self::valueAt($filters, 'pending', 'default_value'));
        self::assertSame(
            [GiftCardPendingFilter::SHOW, GiftCardPendingFilter::ONLY],
            array_values(self::arrayAt($filters, 'pending', 'form_options', 'choices')),
        );
        self::assertSame('setono_sylius_gift_card.ui.pending_hide', self::valueAt($filters, 'pending', 'form_options', 'placeholder'));

        self::assertSame(['customer.email'], self::valueAt($filters, 'customer', 'options', 'fields'));
        self::assertSame('contains', self::valueAt($filters, 'customer', 'form_options', 'type'));
        self::assertSame(['virtual', 'physical'], array_values(self::arrayAt($filters, 'deliveryType', 'form_options', 'choices')));
        self::assertSame('code', self::valueAt($filters, 'currencyCode', 'form_options', 'choice_value'));
        self::assertTrue(self::valueAt($filters, 'createdAt', 'options', 'inclusive_to'));

        foreach ([GiftCardExpiredFilter::NAME => 'expired', GiftCardSpentFilter::NAME => 'spent', GiftCardPendingFilter::NAME => 'pending'] as $type => $filter) {
            self::assertSame($type, self::valueAt($filters, $filter, 'type'));
            self::assertArrayHasKey($type, self::arrayAt($config, 'templates', 'filter'));
        }
    }

    /**
     * The name shown is a translation, which only the grid's own query joins to sort by
     *
     * @test
     */
    public function it_sorts_the_design_grid_by_the_translated_name_and_lists_the_channels(): void
    {
        $grid = self::valueAt($this->gridConfig(), 'grids', 'setono_sylius_gift_card_admin_gift_card_design');

        self::assertSame('createListQueryBuilder', self::valueAt($grid, 'driver', 'options', 'repository', 'method'));
        self::assertSame('translation.name', self::valueAt($grid, 'fields', 'name', 'sortable'));
        self::assertSame('@SyliusAdmin/Grid/Field/_channels.html.twig', self::valueAt($grid, 'fields', 'channels', 'options', 'template'));
    }

    /**
     * What the applied gift cards cover and what remains to pay are shown below the summary of the cart and of every
     * checkout step up to placing the order, all from one template. Sylius' own blocks on these events have fixed
     * priorities, so the priority is what puts the figures right below the summary. The functional
     * CheckoutGiftCardFiguresTest checks the resulting order against Sylius' blocks as the test application has them
     *
     * @test
     *
     * @dataProvider summaryEvents
     */
    public function it_shows_the_gift_card_figures_right_below_each_summary(string $event, int $priority): void
    {
        $blocks = $this->blocksForEvent($event);

        self::assertArrayHasKey('setono_gift_card_totals', $blocks);

        $template = $blocks['setono_gift_card_totals']['template'];
        self::assertSame('@SetonoSyliusGiftCardPlugin/shop/cart/_gift_card_totals.html.twig', $template);
        self::assertFileExists($this->resolveTemplate($template));
        self::assertSame($priority, $blocks['setono_gift_card_totals']['priority'] ?? null);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function summaryEvents(): iterable
    {
        yield 'cart, below the totals (20) and above the legacy after totals event (15)' => ['sylius.shop.cart.summary', 18];
        yield 'address, shipping and payment steps, below the summary (20) and above the shipping step\'s legacy before support event (15)' => ['sylius.shop.checkout.sidebar', 18];
        yield 'complete step, below the order summary (10) and above the legacy after summary event (5)' => ['sylius.shop.checkout.complete.summary', 8];
    }

    /**
     * The sylius_grid configuration prepend() hands Sylius
     *
     * @return array<array-key, mixed>
     */
    private function gridConfig(): array
    {
        $container = new ContainerBuilder();

        (new SetonoSyliusGiftCardExtension())->prepend($container);

        $configs = $container->getExtensionConfig('sylius_grid');
        self::assertCount(1, $configs);

        return $configs[0];
    }

    /**
     * The value at the given path of keys
     */
    private static function valueAt(mixed $config, string ...$keys): mixed
    {
        foreach ($keys as $key) {
            self::assertIsArray($config);
            self::assertArrayHasKey($key, $config);
            $config = $config[$key];
        }

        return $config;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function arrayAt(mixed $config, string ...$keys): array
    {
        $value = self::valueAt($config, ...$keys);
        self::assertIsArray($value);

        return $value;
    }

    private static function stringAt(mixed $config, string ...$keys): string
    {
        $value = self::valueAt($config, ...$keys);
        self::assertIsString($value);

        return $value;
    }

    /**
     * @return array<string, array{template: string, priority?: int}>
     */
    private function blocksForEvent(string $event): array
    {
        $container = new ContainerBuilder();

        (new SetonoSyliusGiftCardExtension())->prepend($container);

        foreach ($container->getExtensionConfig('sylius_ui') as $config) {
            /** @var array<string, array{blocks: array<string, array{template: string, priority?: int}>}> $events */
            $events = $config['events'] ?? [];

            if (isset($events[$event])) {
                return $events[$event]['blocks'];
            }
        }

        self::fail(sprintf('No sylius_ui blocks are registered on the "%s" event', $event));
    }

    private function resolveTemplate(string $template): string
    {
        return dirname(__DIR__, 3) . '/src/Resources/views/' .
            str_replace('@SetonoSyliusGiftCardPlugin/', '', $template);
    }
}
