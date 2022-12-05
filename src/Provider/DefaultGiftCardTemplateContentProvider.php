<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

final class DefaultGiftCardTemplateContentProvider implements DefaultGiftCardTemplateContentProviderInterface
{
    public function getContent(): string
    {
        return <<<TWIG
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300&display=swap" rel="stylesheet">
<style>
body {
    background: transparent url("{{ configuration.image }}") no-repeat left bottom;
    background-size: 100px;
    font-family: 'Open Sans', sans-serif;
}
.amount {
    font-size: 50px;
    text-align: center;
}
.code {
    font-size: 30px;
    text-align: center;
}
.expires {
    position: absolute;
    right: 0;
    bottom: 0;
    color: #6b6b6b;
}
</style>
<p class="amount">{{ giftCard.amount }}</p>
<p class="code">{{ giftCard.code }}</p>
<p class="expires">Expires: {{ giftCard.expiresAt|date }}</p>
TWIG;
    }
}
