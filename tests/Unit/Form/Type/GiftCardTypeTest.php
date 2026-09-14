<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Form\Type;

use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Form\Type\CustomerAutocompleteChoiceType;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardType;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Sylius\Bundle\ChannelBundle\Form\Type\ChannelChoiceType;
use Sylius\Bundle\ResourceBundle\Form\Type\ResourceAutocompleteChoiceType;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Registry\ServiceRegistryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class GiftCardTypeTest extends TypeTestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_maps_a_valid_submission_and_seeds_the_initial_amount(): void
    {
        $giftCard = new GiftCard();

        $form = $this->factory->create(GiftCardType::class, $giftCard);
        $form->submit([
            'code' => 'GIFTCARDCODE',
            'amount' => '50',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));

        // The admin types major units; the model keeps minor units
        self::assertSame(5000, $giftCard->getAmount());
        self::assertSame(5000, $giftCard->getInitialAmount());
    }

    /**
     * The data mapper writes the submitted amount into the non-nullable setter during submit(), before the
     * POST_SUBMIT validation listener runs, so a blank amount used to end the request in a 500
     *
     * @test
     */
    public function it_reports_a_blank_amount_as_a_validation_error(): void
    {
        $giftCard = new GiftCard();

        $form = $this->factory->create(GiftCardType::class, $giftCard);
        $form->submit([
            'code' => 'GIFTCARDCODE',
            'amount' => '',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertFalse($form->isValid());
        self::assertSame(0, $giftCard->getAmount());
        self::assertGreaterThan(0, $form->get('amount')->getErrors()->count());
    }

    /**
     * @return list<FormExtensionInterface>
     */
    protected function getExtensions(): array
    {
        /** @var RepositoryInterface<CurrencyInterface> $currencyRepository */
        $currencyRepository = $this->prophesize(RepositoryInterface::class)->reveal();

        $codeGenerator = $this->prophesize(GiftCardCodeGeneratorInterface::class);
        $codeGenerator->generate()->willReturn('GENERATEDCODE');

        $type = new GiftCardType(
            GiftCard::class,
            $currencyRepository,
            $codeGenerator->reveal(),
            ['setono_sylius_gift_card'],
        );

        $channelRepository = $this->prophesize(RepositoryInterface::class);
        $channelRepository->findAll()->willReturn([]);

        $customerRepository = $this->prophesize(RepositoryInterface::class);

        $resourceRepositoryRegistry = $this->prophesize(ServiceRegistryInterface::class);
        $resourceRepositoryRegistry->get('sylius.customer')->willReturn($customerRepository->reveal());

        $preloaded = new PreloadedExtension([
            $type,
            new ChannelChoiceType($channelRepository->reveal()),
            new CustomerAutocompleteChoiceType($this->prophesize(UrlGeneratorInterface::class)->reveal()),
            new ResourceAutocompleteChoiceType($resourceRepositoryRegistry->reveal()),
        ], []);

        return [$preloaded, new ValidatorExtension($this->createValidator())];
    }

    private function createValidator(): ValidatorInterface
    {
        $uniqueEntityValidator = new class() extends ConstraintValidator {
            public function validate(mixed $value, Constraint $constraint): void
            {
                // Uniqueness of the code is a database lookup; this test only exercises the amount
            }
        };

        return Validation::createValidatorBuilder()
            ->addXmlMapping(__DIR__ . '/../../../../src/Resources/config/validation/GiftCard.xml')
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                'doctrine.orm.validator.unique' => $uniqueEntityValidator,
            ]))
            ->getValidator()
        ;
    }
}
