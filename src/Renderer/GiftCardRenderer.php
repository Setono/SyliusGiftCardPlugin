<?php
declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Renderer;

use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Twig\Environment;
use Webmozart\Assert\Assert;

final class GiftCardRenderer implements GiftCardRendererInterface
{
    private Environment $twig;
    private ChannelContextInterface $channelContext;
    private LocaleContextInterface $localeContext;

    public function __construct(
        Environment $twig,
        ChannelContextInterface $channelContext,
        LocaleContextInterface $localeContext
    ) {
        $this->twig = $twig;
        $this->channelContext = $channelContext;
        $this->localeContext = $localeContext;
    }

    public function render(
        GiftCardInterface $giftCard,
        GiftCardConfigurationInterface $giftCardConfiguration,
        array $options
    ): string {
        $options = $this->resolveOptions($options);

        $template = $giftCardConfiguration->getTemplate();
        Assert::notNull($template);

        $template = '{% extends "@SetonoSyliusGiftCardPlugin/Shop/GiftCard/pdf_layout.html.twig" %}{% block content %}' . $template . '{% endblock %}';

        return $this->twig->render($this->twig->createTemplate($template), [
            'channel' => $options['channel'],
            'localeCode' => $options['localeCode'],
            'giftCard' => $giftCard,
        ]);
    }

    /**
     * @psalm-assert bool $options['base64']
     * @psalm-assert ChannelContextInterface $options['channel']
     * @psalm-assert string $options['localeCode']
     */
    private function resolveOptions(array $options): array {
        $optionsResolver = new OptionsResolver();

        $optionsResolver
            ->setDefault('base64', false)
            ->setAllowedTypes('base64', 'bool')

            ->setDefault('channel', $this->channelContext->getChannel())
            ->setAllowedTypes('channel', ChannelInterface::class)

            ->setDefault('localeCode', $this->localeContext->getLocaleCode())
            ->setAllowedTypes('localeCode', 'string')
        ;

        return $optionsResolver->resolve($options);
    }
}
