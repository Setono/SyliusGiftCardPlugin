<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Twig\Runtime;

use Setono\SyliusGiftCardPlugin\Checker\GiftCardSetupCheckerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Twig\Extension\RuntimeExtensionInterface;

final class GiftCardSetupRuntime implements RuntimeExtensionInterface
{
    /**
     * The warning is rendered in the top bar of every admin page and again as a message on the pages that can
     * fix it, so the answer is kept for the request rather than queried once per rendering
     *
     * @var list<ChannelInterface>|null
     */
    private ?array $channelsWithoutDesign = null;

    public function __construct(private readonly GiftCardSetupCheckerInterface $setupChecker)
    {
    }

    /**
     * @return list<ChannelInterface>
     */
    public function getChannelsWithoutDesign(): array
    {
        return $this->channelsWithoutDesign ??= $this->setupChecker->getChannelsWithoutDesign();
    }
}
