<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

final class DefaultGiftCardTemplateContentProvider implements DefaultGiftCardTemplateContentProviderInterface
{
    public function getContent(): string
    {
        return <<<TWIG
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <style>
        body {
            background: transparent url("{{ configuration.image }}") no-repeat left bottom;
            background-size: 50px;
            font-family: arial, sans-serif;
        }
        .amount {
            font-size: 50px;
            text-align: center;
        }
        .code {
            margin-top: 20px;
            font-size: 30px;
            text-align: center;
        }
        .message {
            margin-top: 20px;
            color: #6b6b6b;
            padding: 0 20%;
            text-align: center;
        }
        .expires {
            position: absolute;
            right: 0;
            bottom: 0;
            color: #6b6b6b;
            text-align: right;
        }
        </style>
    </head>
    <body>
        <div class="amount">{{ giftCard.amount }}</div>
        <div class="code">{{ giftCard.code }}</div>
        {% if giftCard.customMessage is not null %}
            <div class="message">{{ giftCard.customMessage }}</div>
        {% endif %}
        {% if giftCard.expiresAt is not null %}
            <div class="expires">Expires: {{ giftCard.expiresAt|date('Y-m-d') }}</div>
        {% endif %}
    </body>
</html>
TWIG;
    }
}
