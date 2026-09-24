<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Validator\Constraints;

use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardDesignProviderInterface;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardDesignRequired;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardDesignRequiredValidator;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Channel\Model\ChannelInterface as BaseChannelInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<GiftCardDesignRequiredValidator>
 */
final class GiftCardDesignRequiredValidatorTest extends ConstraintValidatorTestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ChannelContextInterface> */
    private ObjectProphecy $channelContext;

    /** @var ObjectProphecy<GiftCardDesignProviderInterface> */
    private ObjectProphecy $designProvider;

    /** @test */
    public function it_requires_a_design_when_the_channel_offers_any(): void
    {
        $this->channelOffers([$this->prophesize(GiftCardDesignInterface::class)->reveal()]);

        $this->validator->validate(null, new GiftCardDesignRequired());

        $this->buildViolation('setono_sylius_gift_card.gift_card_information.design.required')->assertRaised();
    }

    /** @test */
    public function it_does_not_require_a_design_the_channel_cannot_offer(): void
    {
        $this->channelOffers([]);

        $this->validator->validate(null, new GiftCardDesignRequired());

        $this->assertNoViolation();
    }

    /** @test */
    public function it_is_satisfied_by_a_chosen_design_without_asking_anyone(): void
    {
        $this->validator->validate($this->prophesize(GiftCardDesignInterface::class)->reveal(), new GiftCardDesignRequired());

        $this->assertNoViolation();
        $this->channelContext->getChannel()->shouldNotHaveBeenCalled();
    }

    /** @test */
    public function it_has_nothing_to_require_outside_a_channel(): void
    {
        $this->channelContext->getChannel()->willThrow(new ChannelNotFoundException());

        $this->validator->validate(null, new GiftCardDesignRequired());

        $this->assertNoViolation();
    }

    /**
     * Designs are offered per shop channel, so a channel of another kind has none to require one of
     *
     * @test
     */
    public function it_has_nothing_to_require_in_a_channel_that_is_not_a_shop_channel(): void
    {
        $this->channelContext->getChannel()->willReturn($this->prophesize(BaseChannelInterface::class)->reveal());

        $this->validator->validate(null, new GiftCardDesignRequired());

        $this->assertNoViolation();
        $this->designProvider->getDesigns(Argument::any())->shouldNotHaveBeenCalled();
    }

    /** @test */
    public function it_only_validates_its_own_constraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(null, new NotBlank());
    }

    protected function createValidator(): GiftCardDesignRequiredValidator
    {
        $this->channelContext = $this->prophesize(ChannelContextInterface::class);
        $this->designProvider = $this->prophesize(GiftCardDesignProviderInterface::class);

        return new GiftCardDesignRequiredValidator($this->channelContext->reveal(), $this->designProvider->reveal());
    }

    /**
     * @param list<GiftCardDesignInterface> $designs
     */
    private function channelOffers(array $designs): void
    {
        $channel = $this->prophesize(ChannelInterface::class)->reveal();

        $this->channelContext->getChannel()->willReturn($channel);
        $this->designProvider->getDesigns($channel)->willReturn($designs);
    }
}
