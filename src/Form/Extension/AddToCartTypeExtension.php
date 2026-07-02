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

final class AddToCartTypeExtension extends AbstractTypeExtension
{
    public function __construct(
        private readonly CartGiftCardHandlerInterface $cartGiftCardHandler,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, $this->addGiftCardInformation(...));
        $builder->addEventListener(FormEvents::POST_SUBMIT, $this->handleGiftCard(...), -10);
    }

    public function addGiftCardInformation(FormEvent $event): void
    {
        $command = $event->getData();
        if (!$command instanceof AddToCartCommandInterface) {
            return;
        }

        $product = $command->getCartItem()->getProduct();
        if (!$product instanceof ProductInterface || !$product->isGiftCard()) {
            return;
        }

        $event->getForm()->add('giftCardInformation', GiftCardInformationType::class, [
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

        $product = $command->getCartItem()->getProduct();
        if (!$product instanceof ProductInterface || !$product->isGiftCard()) {
            return;
        }

        $this->cartGiftCardHandler->handle($command);
    }

    public static function getExtendedTypes(): iterable
    {
        return [AddToCartType::class];
    }
}
