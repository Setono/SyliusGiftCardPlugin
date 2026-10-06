<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Validator\Constraints;

use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardDesignProviderInterface;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardDesignIsAvailableInChannel;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardDesignIsAvailableInChannelValidator;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<GiftCardDesignIsAvailableInChannelValidator>
 */
final class GiftCardDesignIsAvailableInChannelValidatorTest extends ConstraintValidatorTestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<GiftCardDesignProviderInterface> */
    private ObjectProphecy $designProvider;

    private ChannelInterface $channel;

    /** @test */
    public function it_accepts_a_design_the_channel_offers(): void
    {
        $classic = $this->design('classic', 'Classic');
        $this->designProvider->getDesigns($this->channel)->willReturn([$this->design('birthday', 'Birthday'), $classic]);

        $this->validator->validate($this->newGiftCard($classic), new GiftCardDesignIsAvailableInChannel());

        $this->assertNoViolation();
    }

    /** @test */
    public function it_refuses_a_design_the_channel_does_not_offer(): void
    {
        $this->designProvider->getDesigns($this->channel)->willReturn([$this->design('classic', 'Classic')]);

        $this->validator->validate($this->newGiftCard($this->design('christmas', 'Christmas')), new GiftCardDesignIsAvailableInChannel());

        $this->buildViolation('setono_sylius_gift_card.gift_card.design.not_available_in_channel')
            ->setParameter('{{ design }}', 'Christmas')
            ->setParameter('{{ channel }}', 'Web store')
            ->atPath('property.path.design')
            ->assertRaised();
    }

    /**
     * The provider can be decorated, and a decorator may well hand out designs it loaded itself
     *
     * @test
     */
    public function it_recognizes_an_offered_design_by_its_code(): void
    {
        $this->designProvider->getDesigns($this->channel)->willReturn([$this->design('classic', 'Classic')]);

        $this->validator->validate($this->newGiftCard($this->design('classic', 'Classic')), new GiftCardDesignIsAvailableInChannel());

        $this->assertNoViolation();
    }

    /**
     * A card without a design prints the default layout
     *
     * @test
     */
    public function it_accepts_a_card_without_a_design(): void
    {
        $this->validator->validate($this->newGiftCard(null), new GiftCardDesignIsAvailableInChannel());

        $this->assertNoViolation();
        $this->designProvider->getDesigns(Argument::any())->shouldNotHaveBeenCalled();
    }

    /** @test */
    public function it_has_nothing_to_hold_the_design_of_a_card_without_a_channel_to(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setDesign($this->design('classic', 'Classic'));

        $this->validator->validate($giftCard, new GiftCardDesignIsAvailableInChannel());

        $this->assertNoViolation();
        $this->designProvider->getDesigns(Argument::any())->shouldNotHaveBeenCalled();
    }

    /**
     * A design disabled, or taken out of the channel, after the card was issued still prints on the card, and the card
     * is validated whenever it is edited
     *
     * @test
     */
    public function it_leaves_the_design_of_an_existing_card_alone(): void
    {
        $this->designProvider->getDesigns($this->channel)->willReturn([]);

        $giftCard = $this->newGiftCard($this->design('retired', 'Retired'));
        (new \ReflectionProperty(GiftCard::class, 'id'))->setValue($giftCard, 1);

        $this->validator->validate($giftCard, new GiftCardDesignIsAvailableInChannel());

        $this->assertNoViolation();
    }

    /** @test */
    public function it_accepts_null(): void
    {
        $this->validator->validate(null, new GiftCardDesignIsAvailableInChannel());

        $this->assertNoViolation();
    }

    /** @test */
    public function it_only_validates_gift_cards(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(new \stdClass(), new GiftCardDesignIsAvailableInChannel());
    }

    /** @test */
    public function it_only_validates_its_own_constraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate($this->newGiftCard(null), new NotBlank());
    }

    protected function createValidator(): GiftCardDesignIsAvailableInChannelValidator
    {
        $this->designProvider = $this->prophesize(GiftCardDesignProviderInterface::class);

        $this->channel = new Channel();
        $this->channel->setCode('WEB');
        $this->channel->setName('Web store');

        return new GiftCardDesignIsAvailableInChannelValidator($this->designProvider->reveal());
    }

    private function newGiftCard(?GiftCardDesignInterface $design): GiftCard
    {
        $giftCard = new GiftCard();
        $giftCard->setChannel($this->channel);
        $giftCard->setDesign($design);

        return $giftCard;
    }

    private function design(string $code, string $name): GiftCardDesignInterface
    {
        $design = $this->prophesize(GiftCardDesignInterface::class);
        $design->getCode()->willReturn($code);
        $design->getName()->willReturn($name);

        return $design->reveal();
    }
}
