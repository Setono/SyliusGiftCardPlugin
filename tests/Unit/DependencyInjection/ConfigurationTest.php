<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\DependencyInjection;

use Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait;
use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\DependencyInjection\Configuration;
use Setono\SyliusGiftCardPlugin\Doctrine\ORM\GiftCardConfigurationRepository;
use Setono\SyliusGiftCardPlugin\Doctrine\ORM\GiftCardRepository;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardChannelConfigurationType;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardConfigurationImageType;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardConfigurationType;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardType;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardChannelConfiguration;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationImage;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
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
            'pdf_rendering' => [
                'default_orientation' => 'Landscape',
                'available_orientations' => [
                    'Portrait',
                    'Landscape',
                ],
                'default_page_size' => 'A6',
                'available_page_sizes' => [
                    'A0',
                    'A1',
                    'A2',
                    'A3',
                    'A4',
                    'A5',
                    'A6',
                    'A7',
                    'A8',
                    'A9',
                    'B0',
                    'B1',
                    'B2',
                    'B3',
                    'B4',
                    'B5',
                    'B6',
                    'B7',
                    'B8',
                    'B9',
                    'B10',
                    'C5E',
                    'Comm10E',
                    'DLE',
                    'Executive',
                    'Folio',
                    'Ledger',
                    'Legal',
                    'Letter',
                    'Tabloid',
                ],
                'preferred_page_sizes' => [
                    'A4',
                    'A5',
                    'A6',
                ],
            ],
            'driver' => SyliusResourceBundle::DRIVER_DOCTRINE_ORM,
            'resources' => [
                'gift_card' => [
                    'classes' => [
                        'model' => GiftCard::class,
                        'controller' => ResourceController::class,
                        'repository' => GiftCardRepository::class,
                        'form' => GiftCardType::class,
                        'factory' => Factory::class,
                    ],
                ],
                'gift_card_configuration' => [
                    'classes' => [
                        'model' => GiftCardConfiguration::class,
                        'controller' => ResourceController::class,
                        'repository' => GiftCardConfigurationRepository::class,
                        'form' => GiftCardConfigurationType::class,
                        'factory' => Factory::class,
                    ],
                ],
                'gift_card_configuration_image' => [
                    'classes' => [
                        'model' => GiftCardConfigurationImage::class,
                        'controller' => ResourceController::class,
                        'repository' => EntityRepository::class,
                        'form' => GiftCardConfigurationImageType::class,
                        'factory' => Factory::class,
                    ],
                ],
                'gift_card_channel_configuration' => [
                    'classes' => [
                        'model' => GiftCardChannelConfiguration::class,
                        'controller' => ResourceController::class,
                        'repository' => EntityRepository::class,
                        'form' => GiftCardChannelConfigurationType::class,
                        'factory' => Factory::class,
                    ],
                ],
            ],
        ]);
    }
}
