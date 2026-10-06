<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\DependencyInjection\Definition\Builder;

use Setono\SyliusGiftCardPlugin\DependencyInjection\Definition\StrtotimeIntervalNode;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\ScalarNode;

/**
 * Defines a {@see StrtotimeIntervalNode}
 *
 * @internal
 */
final class StrtotimeIntervalNodeDefinition extends ScalarNodeDefinition
{
    protected function instantiateNode(): ScalarNode
    {
        return new StrtotimeIntervalNode($this->name, $this->parent, $this->pathSeparator);
    }
}
