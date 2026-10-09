<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\Extension;

use Setono\SyliusGiftCardPlugin\Cart\CartGiftCardHandlerInterface;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardInformationType;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Setono\SyliusGiftCardPlugin\Order\AddToCartCommandInterface;
use Sylius\Bundle\CoreBundle\Form\Type\Order\AddToCartType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AddToCartTypeExtension extends AbstractTypeExtension
{
    /**
     * @param class-string<AddToCartCommandInterface> $commandClass
     */
    public function __construct(
        private readonly CartGiftCardHandlerInterface $cartGiftCardHandler,
        private readonly string $commandClass,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, $this->addGiftCardInformation(...));
        $builder->addEventListener(FormEvents::POST_SUBMIT, $this->handleGiftCard(...), -10);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // The decorated command factory produces our AddToCartCommand, so the form must bind to it instead of the
        // core command class (otherwise the form's view data is rejected as the wrong type)
        $resolver->setDefault('data_class', $this->commandClass);
    }

    public function addGiftCardInformation(FormEvent $event): void
    {
        $command = $event->getData();
        if (!$command instanceof AddToCartCommandInterface) {
            return;
        }

        $form = $event->getForm();
        if (!self::addsGiftCard($form)) {
            return;
        }

        $form->add('giftCardInformation', GiftCardInformationType::class, [
            'label' => false,
        ]);
    }

    public function handleGiftCard(FormEvent $event): void
    {
        $command = $event->getData();
        if (!$command instanceof AddToCartCommandInterface) {
            return;
        }

        $form = $event->getForm();
        if (!$form->isValid()) {
            return;
        }

        if (!self::addsGiftCard($form)) {
            return;
        }

        $this->cartGiftCardHandler->handle($command);
    }

    public static function getExtendedTypes(): iterable
    {
        return [AddToCartType::class];
    }

    /**
     * Whether the form adds a gift card product to the cart, told by the form's product option rather than by the line.
     * Sylius' add to cart routes look the product up by the id they are given and pass it as that option, which
     * AddToCartType cannot be built without. The line has no variant when none of the product's variants is enabled,
     * as Sylius makes it with the product's first enabled one, and the variant a request chooses is only known once
     * the form is submitted. Both listeners go by the same product, so a submission is only handed on when its gift
     * card information was asked for and validated
     *
     * @param FormInterface<mixed> $form
     */
    private static function addsGiftCard(FormInterface $form): bool
    {
        $product = $form->getConfig()->getOption('product');

        return $product instanceof ProductInterface && $product->isGiftCard();
    }
}
