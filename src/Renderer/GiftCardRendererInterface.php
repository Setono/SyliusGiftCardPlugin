<?php
declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Renderer;

use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;

interface GiftCardRendererInterface
{
    /**
     * A boolean indicating whether the rendered PDF will be base64 encoded or not
     *
     * Default: false
     */
    public const OPTION_BASE_64 = 'base64';

    /**
     * The channel to use when rendering the PDF
     *
     * Default: Channel from channel context
     */
    public const OPTION_CHANNEL = 'channel';

    /**
     * The locale code to use when rendering the PDF
     *
     * Default: Locale code from locale context
     */
    public const OPTION_LOCALE_CODE = 'localeCode';

    /**
     * @param array<string, mixed> $options
     */
    public function render(
        GiftCardInterface $giftCard,
        GiftCardConfigurationInterface $giftCardConfiguration,
        array $options
    ): string;
}
