<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\DependencyInjection;

use Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait;
use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\DependencyInjection\Configuration;
use Setono\SyliusGiftCardPlugin\Doctrine\ORM\GiftCardRepository;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardType;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Sylius\Component\Resource\Factory\Factory;

final class ConfigurationTest extends TestCase
{
    use ConfigurationTestCaseTrait;

    protected function getConfiguration(): Configuration
    {
        return new Configuration();
    }

    /**
     * @test
     */
    public function processed_configuration_for_array_node_1(): void
    {
        $this->assertProcessedConfigurationEquals([
            'setono_sylius_gift_card' => [],
        ], [
            'code_length' => 20,
            'driver' => SyliusResourceBundle::DRIVER_DOCTRINE_ORM,
            'resources' => [
                'gift_card' => [
                    'classes' => [
                        'model' => GiftCard::class,
                        'interface' => GiftCardInterface::class,
                        'controller' => ResourceController::class,
                        'repository' => GiftCardRepository::class,
                        'form' => GiftCardType::class,
                        'factory' => Factory::class,
                    ],
                ],
            ],
        ]);
    }
}
