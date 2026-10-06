<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\DependencyInjection\Definition\Builder;

use Setono\SyliusGiftCardPlugin\DependencyInjection\Definition\NullableIntegerNode;
use Symfony\Component\Config\Definition\Builder\IntegerNodeDefinition;
use Symfony\Component\Config\Definition\IntegerNode;

/**
 * Defines a {@see NullableIntegerNode}, with min() and max() like an integer node
 *
 * @internal
 */
final class NullableIntegerNodeDefinition extends IntegerNodeDefinition
{
    protected function instantiateNode(): IntegerNode
    {
        return new NullableIntegerNode($this->name, $this->parent, $this->min, $this->max, $this->pathSeparator);
    }
}
