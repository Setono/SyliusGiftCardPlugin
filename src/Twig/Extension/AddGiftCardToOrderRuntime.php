<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Twig\Extension;

use Setono\SyliusGiftCardPlugin\Controller\Action\AddGiftCardToOrderCommand;
use Setono\SyliusGiftCardPlugin\Form\Type\AddGiftCardToOrderType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Twig\Extension\RuntimeExtensionInterface;

final class AddGiftCardToOrderRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
    ) {
    }

    public function createFormView(): FormView
    {
        return $this->formFactory->create(AddGiftCardToOrderType::class, new AddGiftCardToOrderCommand())->createView();
    }
}
