<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\DependencyInjection\SetonoSyliusGiftCardExtension;
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
