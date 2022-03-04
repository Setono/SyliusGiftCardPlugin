<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

final class PdfAvailableCssOptionProvider implements PdfAvailableCssOptionProviderInterface
{
    /**
     * @return array<array-key, array<array-key, string>>
     */
    public function getAvailableOptions(): array
    {
        return [
            '%logo_url%' => [
                'hint' => 'setono_sylius_gift_card.form.gift_card_configuration.pdf_live_rendering.css_option.logo_url_hint',
                'accessPath' => 'path',
            ],
        ];
    }

    public function getOptionsValue(array $context): array
    {
        $availableOptions = $this->getAvailableOptions();
        $options = [];
        foreach ($availableOptions as $optionName => $definition) {
            /** @psalm-suppress MixedAssignment */
            $options[$optionName] = $context[$definition['accessPath']];
        }

        return $options;
    }

    public function getOptionsHint(): array
    {
        $availableOptions = $this->getAvailableOptions();
        $hints = [];
        foreach ($availableOptions as $optionName => $definition) {
            $hints[$optionName] = $definition['hint'];
        }

        return $hints;
    }
}
