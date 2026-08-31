<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AddGiftCardToOrderExtension extends AbstractExtension
{
    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('ssgc_add_gift_card_to_order_form_view', [AddGiftCardToOrderRuntime::class, 'createFormView']),
        ];
    }
}
