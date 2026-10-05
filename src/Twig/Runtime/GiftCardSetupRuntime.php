<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Twig\Runtime;

use Setono\SyliusGiftCardPlugin\Checker\GiftCardSetupCheckerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Extension\RuntimeExtensionInterface;

final class GiftCardSetupRuntime implements RuntimeExtensionInterface, ResetInterface
{
    /**
     * The warning is rendered in the top bar of every admin page and again as a message on the pages that can
     * fix it, so the answer is kept for the request rather than queried once per rendering
     *
     * @var list<ChannelInterface>|null
     */
    private ?array $channelsWithoutDesign = null;

    private ?bool $paymentMethodMissing = null;

    public function __construct(
        private readonly GiftCardSetupCheckerInterface $setupChecker,
        private readonly string $paymentMethodCode,
    ) {
    }

    /**
     * @return list<ChannelInterface>
     */
    public function getChannelsWithoutDesign(): array
    {
        return $this->channelsWithoutDesign ??= $this->setupChecker->getChannelsWithoutDesign();
    }

    /**
     * The code the payment method gift card payments are made with has to have, while the shop sells gift cards
     * without it, so the warning can tell the merchant which method to create; null when there is nothing to warn about
     */
    public function getMissingPaymentMethodCode(): ?string
    {
        $this->paymentMethodMissing ??= $this->setupChecker->isPaymentMethodMissing();

        return $this->paymentMethodMissing ? $this->paymentMethodCode : null;
    }

    /**
     * The runtime is a shared service, so under a worker runtime (FrankenPHP's worker mode, RoadRunner) it outlives
     * the request. The kernel resets it between requests, so a design or payment method created or disabled since
     * is seen by the next
     */
    public function reset(): void
    {
        $this->channelsWithoutDesign = null;
        $this->paymentMethodMissing = null;
    }
}
