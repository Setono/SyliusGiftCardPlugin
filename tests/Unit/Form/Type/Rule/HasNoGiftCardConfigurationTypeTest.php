<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Form\Type\Rule;

use Setono\SyliusGiftCardPlugin\Form\Type\Rule\HasNoGiftCardConfigurationType;
use Symfony\Component\Form\Test\TypeTestCase;

/**
 * Sylius renders a rule's configuration form under the rule type choice and stores what it submits as the rule's
 * configuration, which PromotionRule only accepts as an array. The has-no-gift-card rule takes no settings
 */
final class HasNoGiftCardConfigurationTypeTest extends TypeTestCase
{
    /** @test */
    public function it_asks_for_nothing(): void
    {
        $form = $this->factory->create(HasNoGiftCardConfigurationType::class);

        self::assertCount(0, $form);
        self::assertSame([], $form->createView()->children);
    }

    /**
     * The admin sends no configuration fields for this rule at all, which must still end up as an empty array
     *
     * @test
     *
     * @dataProvider emptySubmissions
     *
     * @param array<string, mixed>|null $submitted
     */
    public function it_submits_an_empty_configuration(?array $submitted): void
    {
        $form = $this->factory->create(HasNoGiftCardConfigurationType::class);
        $form->submit($submitted);

        self::assertTrue($form->isSynchronized());
        self::assertSame([], $form->getData());
    }

    /**
     * A form theme reaches the rule's configuration through the block prefix derived from the class name, which is
     * what an application that themed it relies on
     *
     * @test
     */
    public function it_is_themed_through_the_block_prefix_derived_from_its_class_name(): void
    {
        $vars = $this->factory->create(HasNoGiftCardConfigurationType::class)->createView()->vars;

        self::assertIsArray($vars);
        self::assertIsArray($vars['block_prefixes']);
        self::assertSame(['form', 'has_no_gift_card_configuration'], array_slice($vars['block_prefixes'], 0, 2));
    }

    /**
     * @return iterable<string, array{?array<string, mixed>}>
     */
    public static function emptySubmissions(): iterable
    {
        yield 'nothing submitted' => [null];
        yield 'an empty configuration' => [[]];
    }
}
