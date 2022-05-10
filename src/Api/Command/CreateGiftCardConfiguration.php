<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Api\Command;

class CreateGiftCardConfiguration
{
    public string $code;

    public bool $default;

    public bool $enabled;

    public ?string $defaultValidityPeriod = null;

    public ?string $pageSize = null;

    public ?string $orientation = null;

    public ?string $template = null;

    public function __construct(
        string $code,
        bool $default,
        bool $enabled,
        ?string $defaultValidityPeriod = null,
        ?string $pageSize = null,
        ?string $orientation = null,
        ?string $template = null
    ) {
        $this->code = $code;
        $this->default = $default;
        $this->enabled = $enabled;
        $this->defaultValidityPeriod = $defaultValidityPeriod;
        $this->pageSize = $pageSize;
        $this->orientation = $orientation;
        $this->template = $template;
    }
}
