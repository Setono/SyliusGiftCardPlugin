<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\DependencyInjection;

use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class SetonoSyliusGiftCardExtension extends AbstractResourceExtension
{
    public function load(array $config, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration([], $container), $config);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        $container->setParameter('setono_sylius_gift_card.code_length', $config['code_length']);
        $useSameInputForPromotionAndGiftCard = false;
        if (array_key_exists('cart', $config) && array_key_exists('use_same_input_for_promotion_and_gift_card', $config['cart'])) {
            $useSameInputForPromotionAndGiftCard = $config['cart']['use_same_input_for_promotion_and_gift_card'];
        }
        $container->setParameter('setono_sylius_gift_card.cart.use_same_input_for_promotion_and_gift_card', $useSameInputForPromotionAndGiftCard);

        $this->registerResources('setono_sylius_gift_card', $config['driver'], $config['resources'], $container);

        $loader->load('services.xml');
    }
}
