<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

final class DefaultGiftCardTemplateContentProvider implements DefaultGiftCardTemplateContentProviderInterface
{
    public function getContent(): string
    {
        return <<<TWIG
<style>
body {
    background: transparent url("{{ giftCardConfiguration.image }}") no-repeat left bottom;
    background-size: 100px;
}
</style>
<p class="code">{{ giftCard.code }}</p>
<p class="amount">{{ giftCard.amount }}</p>
TWIG;
    }
}
