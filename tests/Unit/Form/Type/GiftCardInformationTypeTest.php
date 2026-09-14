<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Form\Type;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardInformationType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesign;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Order\GiftCardInformation;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardAmountLimits;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardAmountLimitsProviderInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardDesignProviderInterface;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\ValidGiftCardAmountValidator;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;

final class GiftCardInformationTypeTest extends TypeTestCase
{
    use ProphecyTrait;

    private GiftCardDesignInterface $design;

    /** @test */
    public function it_maps_a_valid_submission(): void
    {
        $information = $this->createInformation();

        $form = $this->factory->create(GiftCardInformationType::class, $information);
        $form->submit([
            'amount' => '50.00',
            'customMessage' => 'Happy birthday',
            'design' => 'classic',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));

        // Sylius money fields are minor units on the model side
        self::assertSame(5000, $information->getAmount());
        self::assertSame('Happy birthday', $information->getCustomMessage());
        self::assertSame($this->design, $information->getDesign());
    }

    /**
     * The data mapper writes the submitted amount into the information object during submit(), long before the
     * POST_SUBMIT validation listener runs, so a non-nullable setter turns a blank amount into a 500
     *
     * @test
     */
    public function it_reports_a_blank_amount_as_a_validation_error(): void
    {
        $information = $this->createInformation();

        $form = $this->factory->create(GiftCardInformationType::class, $information);
        $form->submit([
            'amount' => '',
            'customMessage' => '',
            'design' => 'classic',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertFalse($form->isValid());
        self::assertNull($information->getAmount());
        self::assertGreaterThan(0, $form->get('amount')->getErrors()->count());
    }

    /**
     * Mirrors GiftCardInformationFactory, which seeds the information object with the order item's unit price
     */
    private function createInformation(): GiftCardInformation
    {
        return new GiftCardInformation(2500);
    }

    /**
     * @return list<FormExtensionInterface>
     */
    protected function getExtensions(): array
    {
        $design = $this->prophesize(GiftCardDesignInterface::class);
        $design->getCode()->willReturn('classic');
        $design->getName()->willReturn('Classic');
        $design->getFrontImage()->willReturn(null);
        $this->design = $design->reveal();

        $currency = $this->prophesize(CurrencyInterface::class);
        $currency->getCode()->willReturn('USD');

        $channel = $this->prophesize(ChannelInterface::class);
        $channel->getBaseCurrency()->willReturn($currency->reveal());

        $channelContext = $this->prophesize(ChannelContextInterface::class);
        $channelContext->getChannel()->willReturn($channel->reveal());

        $designProvider = $this->prophesize(GiftCardDesignProviderInterface::class);
        $designProvider->getDesigns($channel->reveal())->willReturn([$this->design]);

        $amountLimitsProvider = $this->prophesize(GiftCardAmountLimitsProviderInterface::class);
        $amountLimitsProvider->getLimits($channel->reveal())->willReturn(new GiftCardAmountLimits(100, null));

        $type = new GiftCardInformationType(
            GiftCardInformation::class,
            GiftCardDesign::class,
            $channelContext->reveal(),
            $designProvider->reveal(),
        );

        // The design picker is an EntityType, but the choices are handed to it explicitly, so only the
        // identifier metadata is ever read from Doctrine
        $entityType = new EntityType($this->createManagerRegistry());

        $amountValidator = new ValidGiftCardAmountValidator(
            $channelContext->reveal(),
            $amountLimitsProvider->reveal(),
            $this->prophesize(MoneyFormatterInterface::class)->reveal(),
        );

        $validator = Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                ValidGiftCardAmountValidator::class => $amountValidator,
            ]))
            ->getValidator()
        ;

        return [
            new PreloadedExtension([$type, $entityType], []),
            new ValidatorExtension($validator),
        ];
    }

    private function createManagerRegistry(): ManagerRegistry
    {
        $classMetadata = $this->prophesize(ClassMetadata::class);
        $classMetadata->getIdentifierFieldNames()->willReturn(['id']);
        $classMetadata->getTypeOfField('id')->willReturn('integer');
        $classMetadata->hasAssociation('id')->willReturn(false);

        $manager = $this->prophesize(ObjectManager::class);
        $manager->getClassMetadata(GiftCardDesign::class)->willReturn($classMetadata->reveal());

        $registry = $this->prophesize(ManagerRegistry::class);
        $registry->getManagerForClass(GiftCardDesign::class)->willReturn($manager->reveal());

        return $registry->reveal();
    }
}
