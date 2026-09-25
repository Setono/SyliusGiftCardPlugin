<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImage;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImageInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Order\GiftCardInformation;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The constraints live in the validation mapping under src/Resources/config/validation, where a host application
 * overrides them, and several are sized by configuration rather than by the mapping. This runs them through the
 * application's validator, so it covers that the mapping is loaded, that the constraint validators are wired with
 * the configured limits and that the rules needing the database or the channel context get them
 */
final class GiftCardValidationTest extends GiftCardFunctionalTestCase
{
    private const GROUPS = ['setono_sylius_gift_card'];

    private const HOSTNAME = 'shop.example.test';

    protected function setUp(): void
    {
        parent::setUp();

        $channel = $this->getChannel();
        $channel->setHostname(self::HOSTNAME);
        $this->manager->flush();

        // the purchase rules resolve the channel being shopped in from the current request
        /** @var RequestStack $requestStack */
        $requestStack = self::getContainer()->get('request_stack');
        $requestStack->push(Request::create(sprintf('http://%s/', self::HOSTNAME)));
    }

    /** @test */
    public function it_accepts_the_gift_card_information_of_a_valid_purchase(): void
    {
        self::assertSame([], $this->violations(new GiftCardInformation(5000, 'Happy birthday')));
    }

    /** @test */
    public function it_requires_an_amount_to_buy_a_gift_card_for(): void
    {
        self::assertSame(
            ['amount: This value should not be blank.'],
            $this->violations(new GiftCardInformation(null)),
        );
    }

    /**
     * The minimum is setono_sylius_gift_card.purchase.minimum_amount, 100 by default, quoted in the channel's base
     * currency
     *
     * @test
     */
    public function it_rejects_an_amount_below_the_configured_minimum(): void
    {
        self::assertSame([], $this->violations(new GiftCardInformation(100)));

        $violations = $this->validate(new GiftCardInformation(99));

        self::assertCount(1, $violations);
        self::assertSame('amount', $violations[0]->getPropertyPath());
        self::assertSame('setono_sylius_gift_card.gift_card_information.amount.too_low', $violations[0]->getMessageTemplate());
        self::assertSame(['{{ minimum }}' => '$1.00'], $violations[0]->getParameters());
    }

    /**
     * The limit is setono_sylius_gift_card.purchase.maximum_message_length, 200 by default
     *
     * @test
     */
    public function it_rejects_a_message_longer_than_the_configured_limit(): void
    {
        self::assertSame([], $this->violations(new GiftCardInformation(5000, str_repeat('a', 200))));

        $violations = $this->validate(new GiftCardInformation(5000, str_repeat('a', 201)));

        self::assertCount(1, $violations);
        self::assertSame('customMessage', $violations[0]->getPropertyPath());
        self::assertSame(['{{ limit }}' => '200'], $violations[0]->getParameters());
    }

    /** @test */
    public function it_requires_a_design_once_the_channel_offers_one(): void
    {
        self::assertSame([], $this->violations(new GiftCardInformation(5000)), 'precondition: the channel offers no design');

        $this->createDesign('classic');

        self::assertSame(
            ['design: setono_sylius_gift_card.gift_card_information.design.required'],
            $this->violations(new GiftCardInformation(5000), templates: true),
        );
    }

    /** @test */
    public function it_accepts_a_gift_card_issued_in_the_channels_base_currency(): void
    {
        self::assertSame([], $this->violations($this->createGiftCard('VALIDCARD0000001')));
    }

    /**
     * Codes are what a customer redeems a card by, so two cards can never share one
     *
     * @test
     */
    public function it_rejects_a_code_another_gift_card_already_has(): void
    {
        $existing = $this->createGiftCard('TAKEN0000000001');
        $this->manager->persist($existing);
        $this->manager->flush();

        self::assertSame(
            ['code: setono_sylius_gift_card.gift_card.code.unique'],
            $this->violations($this->createGiftCard('TAKEN0000000001'), templates: true),
        );
    }

    /** @test */
    public function it_rejects_a_gift_card_without_the_basics(): void
    {
        $giftCard = $this->createGiftCard('');
        $giftCard->setAmount(0);
        $giftCard->setCurrencyCode('');

        self::assertSame([
            'amount: setono_sylius_gift_card.gift_card.amount.greater_than_or_equal',
            'code: setono_sylius_gift_card.gift_card.code.not_blank',
            'currencyCode: setono_sylius_gift_card.gift_card.currency_code.not_blank',
        ], $this->violations($giftCard, templates: true));
    }

    /**
     * EUR is a real currency, just not the one the channel keeps its orders in
     *
     * @test
     */
    public function it_rejects_a_gift_card_in_another_currency_than_the_channels_base_currency(): void
    {
        $giftCard = $this->createGiftCard('EURCARD00000001');
        $giftCard->setCurrencyCode('EUR');

        self::assertSame(
            ['currencyCode: setono_sylius_gift_card.gift_card.currency_code.not_base_currency'],
            $this->violations($giftCard, templates: true),
        );
    }

    /**
     * The admin form shares the message limit with the shop, so a card cannot be issued with a message the
     * customer could not have written
     *
     * @test
     */
    public function it_rejects_a_gift_card_message_longer_than_the_configured_limit(): void
    {
        $giftCard = $this->createGiftCard('LONGMESSAGE0001');
        $giftCard->setCustomMessage(str_repeat('a', 201));

        self::assertSame(
            ['customMessage: setono_sylius_gift_card.gift_card.custom_message.too_long'],
            $this->violations($giftCard, templates: true),
        );
    }

    /**
     * The name is validated on the translation, which Sylius cascades into for every translatable resource
     *
     * @test
     */
    public function it_rejects_a_design_without_a_name_a_position_or_with_a_repeated_image_type(): void
    {
        $design = $this->newDesign('nameless');
        $design->setName(null);
        $design->setPosition(null);

        foreach ([GiftCardDesignImageInterface::TYPE_BACK, GiftCardDesignImageInterface::TYPE_BACK] as $type) {
            $image = new GiftCardDesignImage();
            $image->setType($type);
            $design->addImage($image);
        }

        self::assertSame([
            'images: setono_sylius_gift_card.gift_card_design.images.unique_type',
            'position: setono_sylius_gift_card.gift_card_design.position.not_null',
            'translations[en_US].name: setono_sylius_gift_card.gift_card_design.name.not_blank',
        ], $this->violations($design, templates: true));
    }

    /** @test */
    public function it_accepts_a_design_with_a_name_and_one_image_per_side(): void
    {
        $design = $this->newDesign('classic');

        foreach ([GiftCardDesignImageInterface::TYPE_FRONT, GiftCardDesignImageInterface::TYPE_BACK] as $type) {
            $image = new GiftCardDesignImage();
            $image->setType($type);
            $design->addImage($image);
        }

        self::assertSame([], $this->violations($design));
    }

    /**
     * The code is a unique, non nullable column, so a design without one has to be a field error rather than the
     * database's. An empty text field is submitted as null
     *
     * @test
     */
    public function it_rejects_a_design_without_a_code(): void
    {
        foreach ([null, ''] as $code) {
            $design = $this->newDesign('codeless');
            $design->setCode($code);

            self::assertSame(
                ['code: setono_sylius_gift_card.gift_card_design.code.not_blank'],
                $this->violations($design, templates: true),
                sprintf('code %s', var_export($code, true)),
            );
        }
    }

    /**
     * The fixtures and setono:gift-card:create-default-design find a design by its code, so two designs can never
     * share one
     *
     * @test
     */
    public function it_rejects_a_code_another_design_already_has(): void
    {
        $this->createDesign('classic');

        self::assertSame([], $this->violations($this->newDesign('modern')));
        self::assertSame(
            ['code: setono_sylius_gift_card.gift_card_design.code.unique'],
            $this->violations($this->newDesign('classic'), templates: true),
        );
    }

    /**
     * Saving a design that already exists is not a clash with itself
     *
     * @test
     */
    public function it_accepts_a_design_keeping_its_own_code(): void
    {
        $design = $this->createDesign('classic');
        $design->setName('Classic, renamed');

        self::assertSame([], $this->violations($design));
    }

    /** @test */
    public function it_rejects_a_design_code_longer_than_the_column(): void
    {
        $design = $this->newDesign('long');

        $design->setCode(str_repeat('a', 255));
        self::assertSame([], $this->violations($design));

        $design->setCode(str_repeat('a', 256));
        $violations = $this->validate($design);

        self::assertCount(1, $violations);
        self::assertSame('code', $violations[0]->getPropertyPath());
        self::assertSame(Length::TOO_LONG_ERROR, $violations[0]->getCode());
    }

    /**
     * @return list<ConstraintViolationInterface>
     */
    private function validate(object $value): array
    {
        /** @var ValidatorInterface $validator */
        $validator = self::getContainer()->get('validator');

        return array_values(iterator_to_array($validator->validate($value, null, self::GROUPS)));
    }

    /**
     * The violations as "<property path>: <message>", sorted, with the message template instead of the message when
     * asked for, which is what the plugin's own messages are asserted by, independently of their translation
     *
     * @return list<string>
     */
    private function violations(object $value, bool $templates = false): array
    {
        $violations = [];
        foreach ($this->validate($value) as $violation) {
            $violations[] = sprintf('%s: %s', $violation->getPropertyPath(), $templates ? $violation->getMessageTemplate() : (string) $violation->getMessage());
        }

        sort($violations);

        return $violations;
    }

    private function createGiftCard(string $code): GiftCardInterface
    {
        /** @var GiftCardFactoryInterface $factory */
        $factory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card');

        $giftCard = $factory->createForChannel($this->getChannel());
        $giftCard->setCode($code);
        $giftCard->setInitialAmount(5000);
        $giftCard->setAmount(5000);

        return $giftCard;
    }

    private function newDesign(string $code): GiftCardDesignInterface
    {
        /** @var FactoryInterface<GiftCardDesignInterface> $factory */
        $factory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card_design');

        $design = $factory->createNew();
        $design->setCode($code);
        $design->setCurrentLocale('en_US');
        $design->setFallbackLocale('en_US');
        $design->setName(ucfirst($code));
        $design->setEnabled(true);
        $design->addChannel($this->getChannel());

        return $design;
    }

    private function createDesign(string $code): GiftCardDesignInterface
    {
        $design = $this->newDesign($code);

        $this->manager->persist($design);
        $this->manager->flush();

        return $design;
    }
}
