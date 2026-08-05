<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\DependencyInjection;

use Setono\SyliusGiftCardPlugin\Operator\OrderGiftCardOperator;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class SetonoSyliusGiftCardExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        /**
         * @var array{
         *     code_length: int,
         *     default_validity_period: string|null,
         *     purchase: array{minimum_amount: int, maximum_amount: int|null},
         *     redemption: array{mode: string, payment_method_code: string},
         *     pdf: array{page_size: string},
         *     resources: array<string, mixed>,
         * } $config
         */
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        $container->setParameter('setono_sylius_gift_card.code_length', $config['code_length']);
        $container->setParameter('setono_sylius_gift_card.default_validity_period', $config['default_validity_period']);
        $container->setParameter('setono_sylius_gift_card.purchase.minimum_amount', $config['purchase']['minimum_amount']);
        $container->setParameter('setono_sylius_gift_card.purchase.maximum_amount', $config['purchase']['maximum_amount']);
        $container->setParameter('setono_sylius_gift_card.redemption.mode', $config['redemption']['mode']);
        $container->setParameter('setono_sylius_gift_card.redemption.payment_method_code', $config['redemption']['payment_method_code']);
        $container->setParameter('setono_sylius_gift_card.pdf.page_size', $config['pdf']['page_size']);
        $container->setParameter(
            'setono_sylius_gift_card.default_design_image_path',
            dirname(__DIR__) . '/Resources/fixtures/default_background.png',
        );

        $this->registerResources(
            'setono_sylius_gift_card',
            SyliusResourceBundle::DRIVER_DOCTRINE_ORM,
            $config['resources'],
            $container,
        );

        $loader->load('services.xml');
        $loader->load(sprintf('services/redemption/%s.xml', $config['redemption']['mode']));
    }

    /**
     * Prepends configuration for other bundles so the host application
     * does not have to import any configuration files manually
     */
    public function prepend(ContainerBuilder $container): void
    {
        $operator = '@' . OrderGiftCardOperator::class;

        $configuration = [
            'winzou_state_machine' => [
                'sylius_order_checkout' => [
                    'callbacks' => [
                        'after' => [
                            'setono_sylius_gift_card__reconcile_gift_cards' => [
                                'on' => ['complete'],
                                'do' => [$operator, 'reconcile'],
                                'args' => ['object'],
                            ],
                        ],
                    ],
                ],
                'sylius_order_payment' => [
                    'callbacks' => [
                        'after' => [
                            'setono_sylius_gift_card__enable_gift_cards' => [
                                'on' => ['pay'],
                                'do' => [$operator, 'enable'],
                                'args' => ['object'],
                            ],
                            'setono_sylius_gift_card__send_gift_cards' => [
                                'on' => ['pay'],
                                'do' => [$operator, 'send'],
                                'args' => ['object'],
                            ],
                        ],
                    ],
                ],
                'sylius_order' => [
                    'callbacks' => [
                        'after' => [
                            'setono_sylius_gift_card__commit_redemption' => [
                                'on' => ['create'],
                                'do' => ['@setono_sylius_gift_card.redemption_method', 'commit'],
                                'args' => ['object'],
                            ],
                            'setono_sylius_gift_card__rollback_redemption' => [
                                'on' => ['cancel'],
                                'do' => ['@setono_sylius_gift_card.redemption_method', 'rollback'],
                                'args' => ['object'],
                            ],
                            'setono_sylius_gift_card__disable_gift_cards' => [
                                'on' => ['cancel'],
                                'do' => [$operator, 'disable'],
                                'args' => ['object'],
                            ],
                        ],
                    ],
                ],
            ],
            'liip_imagine' => [
                'filter_sets' => [
                    'setono_sylius_gift_card_design_thumbnail' => [
                        'filters' => [
                            'thumbnail' => ['size' => [240, 152], 'mode' => 'inset'],
                        ],
                    ],
                    'setono_sylius_gift_card_design_preview' => [
                        'filters' => [
                            'thumbnail' => ['size' => [1200, 1200], 'mode' => 'inset'],
                        ],
                    ],
                ],
            ],
            'sylius_mailer' => [
                'emails' => [
                    'setono_sylius_gift_card__gift_card' => [
                        'subject' => 'setono_sylius_gift_card.email.new_gift_card',
                        'template' => '@SetonoSyliusGiftCardPlugin/email/gift_card.html.twig',
                    ],
                    'setono_sylius_gift_card__gift_cards_from_order' => [
                        'subject' => 'setono_sylius_gift_card.email.your_gift_cards_you_bought_in_the_order',
                        'template' => '@SetonoSyliusGiftCardPlugin/email/gift_cards_from_order.html.twig',
                    ],
                ],
            ],
            'sylius_ui' => [
                'events' => [
                    'sylius.shop.product.show.add_to_cart_form' => [
                        'blocks' => [
                            'setono_gift_card_information' => [
                                'template' => '@SetonoSyliusGiftCardPlugin/shop/product/show/_gift_card_information.html.twig',
                                'priority' => 10,
                            ],
                        ],
                    ],
                    'sylius.shop.cart.summary' => [
                        'blocks' => [
                            'setono_gift_cards' => [
                                'template' => '@SetonoSyliusGiftCardPlugin/shop/cart/_gift_cards.html.twig',
                                'priority' => 10,
                            ],
                        ],
                    ],
                    'sylius.shop.cart.summary.totals' => [
                        'blocks' => [
                            'setono_gift_card_totals' => [
                                'template' => '@SetonoSyliusGiftCardPlugin/shop/cart/_gift_card_totals.html.twig',
                                'priority' => 10,
                            ],
                        ],
                    ],
                ],
            ],
            'sylius_grid' => [
                'grids' => [
                    'setono_sylius_gift_card_admin_gift_card' => [
                        'driver' => [
                            'name' => 'doctrine/orm',
                            'options' => [
                                'class' => '%setono_sylius_gift_card.model.gift_card.class%',
                                'repository' => [
                                    'method' => 'createListQueryBuilder',
                                ],
                            ],
                        ],
                        'limits' => [100, 200, 500, 1000],
                        'sorting' => [
                            'createdAt' => 'desc',
                        ],
                        'fields' => [
                            'code' => [
                                'type' => 'string',
                                'label' => 'sylius.ui.code',
                                'sortable' => null,
                            ],
                            'customer' => [
                                'type' => 'twig',
                                'label' => 'sylius.ui.customer',
                                'options' => [
                                    'template' => '@SetonoSyliusGiftCardPlugin/admin/gift_card/grid/field/customer.html.twig',
                                ],
                            ],
                            'amount' => [
                                'type' => 'twig',
                                'label' => 'sylius.ui.amount',
                                'path' => '.',
                                'options' => [
                                    'template' => '@SetonoSyliusGiftCardPlugin/admin/gift_card/grid/field/amount.html.twig',
                                ],
                            ],
                            'deliveryType' => [
                                'type' => 'twig',
                                'label' => 'setono_sylius_gift_card.ui.delivery_type',
                                'path' => '.',
                                'sortable' => null,
                                'options' => [
                                    'template' => '@SetonoSyliusGiftCardPlugin/admin/gift_card/grid/field/delivery_type.html.twig',
                                ],
                            ],
                            'enabled' => [
                                'type' => 'twig',
                                'label' => 'sylius.ui.enabled',
                                'options' => [
                                    'template' => '@SyliusUi/Grid/Field/enabled.html.twig',
                                ],
                                'sortable' => null,
                            ],
                            'createdAt' => [
                                'type' => 'datetime',
                                'label' => 'sylius.ui.created_at',
                                'sortable' => null,
                                'options' => [
                                    'format' => 'Y-m-d H:i',
                                ],
                            ],
                        ],
                        'filters' => [
                            'code' => [
                                'type' => 'string',
                                'label' => 'sylius.ui.code',
                            ],
                            'enabled' => [
                                'type' => 'boolean',
                                'label' => 'sylius.ui.enabled',
                            ],
                        ],
                        'actions' => [
                            'main' => [
                                'create' => [
                                    'type' => 'create',
                                ],
                                'create_product' => [
                                    'type' => 'default',
                                    'label' => 'setono_sylius_gift_card.ui.create_gift_card_product',
                                    'options' => [
                                        'link' => [
                                            'route' => 'setono_sylius_gift_card_admin_create_gift_card_product',
                                        ],
                                    ],
                                    'icon' => 'shopping bag',
                                ],
                            ],
                            'item' => [
                                'show' => [
                                    'type' => 'show',
                                    'options' => [
                                        'link' => [
                                            'route' => 'setono_sylius_gift_card_admin_gift_card_show',
                                            'parameters' => [
                                                'id' => 'resource.id',
                                            ],
                                        ],
                                    ],
                                ],
                                'update' => [
                                    'type' => 'update',
                                ],
                                'download_pdf' => [
                                    'type' => 'default',
                                    'label' => 'setono_sylius_gift_card.ui.download_pdf',
                                    'options' => [
                                        'link' => [
                                            'route' => 'setono_sylius_gift_card_admin_gift_card_pdf',
                                            'parameters' => [
                                                'id' => 'resource.id',
                                            ],
                                        ],
                                    ],
                                    'icon' => 'download',
                                ],
                                'adjust_balance' => [
                                    'type' => 'default',
                                    'label' => 'setono_sylius_gift_card.ui.adjust_balance',
                                    'options' => [
                                        'link' => [
                                            'route' => 'setono_sylius_gift_card_admin_gift_card_adjust_balance',
                                            'parameters' => [
                                                'id' => 'resource.id',
                                            ],
                                        ],
                                    ],
                                    'icon' => 'balance scale',
                                ],
                                'delete' => [
                                    'type' => 'delete',
                                ],
                            ],
                        ],
                    ],
                    'setono_sylius_gift_card_admin_gift_card_design' => [
                        'driver' => [
                            'name' => 'doctrine/orm',
                            'options' => [
                                'class' => '%setono_sylius_gift_card.model.gift_card_design.class%',
                            ],
                        ],
                        'sorting' => [
                            'position' => 'asc',
                        ],
                        'fields' => [
                            'image' => [
                                'type' => 'twig',
                                'label' => 'setono_sylius_gift_card.ui.image',
                                'path' => '.',
                                'options' => [
                                    'template' => '@SetonoSyliusGiftCardPlugin/admin/gift_card_design/grid/field/image.html.twig',
                                ],
                            ],
                            'code' => [
                                'type' => 'string',
                                'label' => 'sylius.ui.code',
                                'sortable' => null,
                            ],
                            'name' => [
                                'type' => 'string',
                                'label' => 'sylius.ui.name',
                                'path' => 'translation.name',
                            ],
                            'position' => [
                                'type' => 'string',
                                'label' => 'setono_sylius_gift_card.ui.position',
                                'sortable' => null,
                            ],
                            'enabled' => [
                                'type' => 'twig',
                                'label' => 'sylius.ui.enabled',
                                'options' => [
                                    'template' => '@SyliusUi/Grid/Field/enabled.html.twig',
                                ],
                                'sortable' => null,
                            ],
                        ],
                        'actions' => [
                            'main' => [
                                'create' => [
                                    'type' => 'create',
                                ],
                            ],
                            'item' => [
                                'update' => [
                                    'type' => 'update',
                                ],
                                'delete' => [
                                    'type' => 'delete',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($configuration as $extension => $config) {
            $container->prependExtensionConfig($extension, $config);
        }
    }
}
