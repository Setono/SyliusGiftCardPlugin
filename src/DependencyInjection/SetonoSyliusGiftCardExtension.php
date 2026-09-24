<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\DependencyInjection;

use Setono\SyliusGiftCardPlugin\Controller\Action\Admin\CreateGiftCardProductAction;
use Setono\SyliusGiftCardPlugin\Controller\Action\Admin\SendGiftCardEmailAction;
use Setono\SyliusGiftCardPlugin\Operator\OrderGiftCardOperatorInterface;
use Setono\SyliusGiftCardPlugin\StateMachine\GiftCardCoverageGuardInterface;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class SetonoSyliusGiftCardExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    /**
     * The name of the per session rate limiter prepended onto framework.rate_limiter, i.e. the limiter factory
     * is available as the "limiter.setono_sylius_gift_card_apply" service
     */
    public const RATE_LIMITER_NAME = 'setono_sylius_gift_card_apply';

    /**
     * The name of the per client IP rate limiter prepended onto framework.rate_limiter, i.e. the limiter factory
     * is available as the "limiter.setono_sylius_gift_card_apply_ip" service
     */
    public const IP_RATE_LIMITER_NAME = 'setono_sylius_gift_card_apply_ip';

    public function load(array $configs, ContainerBuilder $container): void
    {
        /**
         * @var array{
         *     code_length: int,
         *     default_validity_period: string|null,
         *     purchase: array{minimum_amount: int, maximum_amount: int|null, maximum_message_length: int},
         *     delivery: array{email_physical_cards: bool},
         *     redemption: array{payment_method_code: string, rate_limiter: string|null, ip_rate_limiter: string|null},
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
        $container->setParameter('setono_sylius_gift_card.purchase.maximum_message_length', $config['purchase']['maximum_message_length']);
        $container->setParameter('setono_sylius_gift_card.delivery.email_physical_cards', $config['delivery']['email_physical_cards']);
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
        $loader->load('services/redemption/payment.xml');

        // AddGiftCardToOrderAction asks for these aliases with on-invalid="null", so leaving one out is what turns
        // that bucket off. Pointing one at a service that does not exist fails at compile time, as it should
        foreach (['rate_limiter', 'ip_rate_limiter'] as $option) {
            if (null !== $config['redemption'][$option]) {
                $container->setAlias(sprintf('setono_sylius_gift_card.redemption.%s', $option), $config['redemption'][$option]);
            }
        }
    }

    /**
     * Prepends configuration for other bundles so the host application
     * does not have to import any configuration files manually
     */
    public function prepend(ContainerBuilder $container): void
    {
        $operator = '@' . OrderGiftCardOperatorInterface::class;
        $guard = '@' . GiftCardCoverageGuardInterface::class;

        // winzou runs callbacks in ascending priority order and Sylius' own sit at -800..-100, so a callback
        // without a priority (0) always runs after everything Sylius does. Every callback below states where
        // it belongs relative to Sylius', and its Symfony Workflow twin in EventSubscriber/Workflow carries
        // the negated priority (Symfony dispatches descending); StateMachineCallbackParityTest keeps them equal
        $configuration = [
            'winzou_state_machine' => [
                'sylius_order_checkout' => [
                    'callbacks' => [
                        'guard' => [
                            'setono_sylius_gift_card__guard_gift_card_coverage' => [
                                'on' => ['complete'],
                                'do' => [$guard, 'isSatisfiedBy'],
                                'args' => ['object'],
                                // A cart whose applied gift cards no longer pay what they did when they were applied (spent
                                // from another cart, disabled, adjusted) must not be placed: nothing below re-checks them,
                                // and an order with no payments at all is paid on the spot. Sylius registers no guard on
                                // complete, so there is nothing to order against
                                'priority' => 0,
                            ],
                        ],
                        'after' => [
                            'setono_sylius_gift_card__reconcile_gift_cards' => [
                                'on' => ['complete'],
                                'do' => [$operator, 'reconcile'],
                                'args' => ['object'],
                                // Before sylius_create_order (-400) cascades the order into existence and before
                                // sylius_control_payment_state (-200) can pay it on the spot (an order with nothing
                                // left to pay is paid right here), so every unit has its card before anything can
                                // enable and email them
                                'priority' => -500,
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
                                // After Sylius' own pay callbacks (sylius_order_paid -200, sylius_resolve_state -100),
                                // so the cards go live only once Sylius has settled the paid order
                                'priority' => -50,
                            ],
                            'setono_sylius_gift_card__send_gift_cards' => [
                                'on' => ['pay'],
                                'do' => [$operator, 'send'],
                                'args' => ['object'],
                                // After enable, so a card is never emailed before it is enabled
                                'priority' => -40,
                            ],
                            'setono_sylius_gift_card__disable_gift_cards' => [
                                'on' => ['refund'],
                                'do' => [$operator, 'disable'],
                                'args' => ['object'],
                                // The money for an order refunded in full went back, so the cards it bought must
                                // not be spendable. Deliberately not on partially_refund: a partial refund does not
                                // say which payments or items the money went back for, so the cards stay usable
                                // and the merchant disables them by hand where that is what the refund meant.
                                // Sylius registers nothing on refund, so there is nothing to order against
                                'priority' => 0,
                            ],
                        ],
                    ],
                ],
                'sylius_payment' => [
                    'callbacks' => [
                        'after' => [
                            'setono_sylius_gift_card__rollback_payment' => [
                                'on' => ['refund'],
                                'do' => ['@setono_sylius_gift_card.redemption_method', 'rollbackPayment'],
                                'args' => ['object'],
                                // Runs for every refunded payment (the redemption method ignores those that are not
                                // gift card payments) and is also what restores the balance when an order is
                                // cancelled, since rollback refunds the gift card payments. Before sylius_resolve_state
                                // (-100) works out the order's payment state from the refunded payment, so the card
                                // is whole again before anything reacts to the order being (partially) refunded
                                'priority' => -150,
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
                                // After sylius_request_payment (-700) has put the order in awaiting_payment and
                                // sylius_create_payment (-600) has created the gateway payments, so the gift card
                                // payments join a complete set the payment state resolver can settle the order from
                                'priority' => -50,
                            ],
                            'setono_sylius_gift_card__rollback_redemption' => [
                                'on' => ['cancel'],
                                'do' => ['@setono_sylius_gift_card.redemption_method', 'rollback'],
                                'args' => ['object'],
                                // Before sylius_cancel_payment (-600) walks the order's payments, so the gift card
                                // payments are already refunded and their balance restored by then
                                'priority' => -650,
                            ],
                            'setono_sylius_gift_card__disable_gift_cards' => [
                                'on' => ['cancel'],
                                'do' => [$operator, 'disable'],
                                'args' => ['object'],
                                // Right after rollback: nothing Sylius does on cancel depends on it, so the plugin's
                                // cancel work is done before Sylius' cascades start
                                'priority' => -640,
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
                    // Sylius renders the product form with render_rest: false, so the checkbox the form extension adds
                    // has to be rendered explicitly or every save submits it as unchecked
                    'sylius.admin.product.tab_details' => [
                        'blocks' => [
                            'setono_gift_card' => [
                                'template' => '@SetonoSyliusGiftCardPlugin/admin/product/_gift_card.html.twig',
                                'priority' => 10,
                            ],
                        ],
                    ],
                    // The warning about a channel selling gift cards without a design: a label in the top bar of every
                    // admin page, and the full message above the header of the two indexes that can fix it
                    // (Sylius' header block sits at priority 20)
                    'sylius.admin.layout.topbar_middle' => [
                        'blocks' => [
                            'setono_gift_card_setup_warning' => [
                                'template' => '@SetonoSyliusGiftCardPlugin/admin/layout/_setup_warning_topbar.html.twig',
                                'priority' => 10,
                            ],
                        ],
                    ],
                    'setono_sylius_gift_card.admin.gift_card.index' => [
                        'blocks' => [
                            'setono_gift_card_setup_warning' => [
                                'template' => '@SetonoSyliusGiftCardPlugin/admin/_setup_warning.html.twig',
                                'priority' => 30,
                            ],
                        ],
                    ],
                    'setono_sylius_gift_card.admin.gift_card_design.index' => [
                        'blocks' => [
                            'setono_gift_card_setup_warning' => [
                                'template' => '@SetonoSyliusGiftCardPlugin/admin/_setup_warning.html.twig',
                                'priority' => 30,
                            ],
                        ],
                    ],
                    'sylius.shop.product.show.add_to_cart_form' => [
                        'blocks' => [
                            'setono_gift_card_information' => [
                                'template' => '@SetonoSyliusGiftCardPlugin/shop/product/show/_gift_card_information.html.twig',
                                'priority' => 10,
                            ],
                        ],
                    ],
                    // Sylius' own blocks on this event are the totals (priority 20), the legacy after totals event
                    // (15) and the checkout button (10). The gift card figures belong right below the order total,
                    // and the redemption form between them and the checkout button, so both get a priority of their
                    // own instead of sharing one with the checkout button and leaving the order to registration
                    'sylius.shop.cart.summary' => [
                        'blocks' => [
                            'setono_gift_card_totals' => [
                                'template' => '@SetonoSyliusGiftCardPlugin/shop/cart/_gift_card_totals.html.twig',
                                'priority' => 18,
                            ],
                            'setono_gift_cards' => [
                                'template' => '@SetonoSyliusGiftCardPlugin/shop/cart/_gift_cards.html.twig',
                                'priority' => 12,
                            ],
                        ],
                    ],
                ],
            ],
            'sylius_grid' => [
                'templates' => [
                    'action' => [
                        // A form that POSTs with a CSRF token, for actions that change state and so must not
                        // be reachable through a plain link
                        'setono_sylius_gift_card_post_link' => '@SetonoSyliusGiftCardPlugin/admin/grid/action/post_link.html.twig',
                    ],
                ],
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
                                // Every hit creates another product, so this is a POST form with a CSRF token
                                // rather than a link, and it asks for confirmation first
                                'create_product' => [
                                    'type' => 'setono_sylius_gift_card_post_link',
                                    'label' => 'setono_sylius_gift_card.ui.create_gift_card_product',
                                    'options' => [
                                        'link' => [
                                            'route' => 'setono_sylius_gift_card_admin_create_gift_card_product',
                                        ],
                                        'csrf_token_id' => CreateGiftCardProductAction::CSRF_TOKEN_ID,
                                        'confirmation' => true,
                                    ],
                                    'icon' => 'shopping bag',
                                ],
                                // Designs and the outstanding balance report live here rather than in the admin
                                // menu, so this plugin only takes up a single menu entry
                                'designs' => [
                                    'type' => 'default',
                                    'label' => 'setono_sylius_gift_card.ui.designs',
                                    'options' => [
                                        'link' => [
                                            'route' => 'setono_sylius_gift_card_admin_gift_card_design_index',
                                        ],
                                    ],
                                    'icon' => 'paint brush',
                                ],
                                'balance' => [
                                    'type' => 'default',
                                    'label' => 'setono_sylius_gift_card.ui.balance',
                                    'options' => [
                                        'link' => [
                                            'route' => 'setono_sylius_gift_card_admin_gift_card_balance',
                                        ],
                                    ],
                                    'icon' => 'balance scale',
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
                                // Sending reaches the customer, so it is a POST form with a CSRF token rather
                                // than a link, and only the icon is shown to keep the row of actions short
                                'send_email' => [
                                    'type' => 'setono_sylius_gift_card_post_link',
                                    'label' => 'setono_sylius_gift_card.ui.send_email',
                                    'options' => [
                                        'link' => [
                                            'route' => 'setono_sylius_gift_card_admin_gift_card_send_email',
                                            'parameters' => [
                                                'id' => 'resource.id',
                                            ],
                                        ],
                                        'csrf_token_id' => SendGiftCardEmailAction::CSRF_TOKEN_ID,
                                        'confirmation' => true,
                                    ],
                                    'icon' => 'envelope',
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
                                // Designs are reached from the gift cards index rather than the admin menu,
                                // so offer the way back here
                                'gift_cards' => [
                                    'type' => 'default',
                                    'label' => 'setono_sylius_gift_card.ui.gift_cards',
                                    'options' => [
                                        'link' => [
                                            'route' => 'setono_sylius_gift_card_admin_gift_card_index',
                                        ],
                                    ],
                                    'icon' => 'gift',
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

        // The limiters the redemption.rate_limiter and redemption.ip_rate_limiter options point at unless the
        // application names others. They are registered whether or not they are used, because the plugin's own
        // configuration is not processed yet when prepending, and their values can be overridden under
        // framework.rate_limiter like any other limiter's
        $configuration['framework'] = [
            'rate_limiter' => [
                'limiters' => [
                    self::RATE_LIMITER_NAME => [
                        'policy' => 'sliding_window',
                        'limit' => 10,
                        'interval' => '1 minute',
                        // Locking would require symfony/lock, which a Sylius application does not
                        // necessarily have, and the race it prevents only ever lets a handful of extra
                        // attempts through, which does not matter for throttling code guesses
                        'lock_factory' => null,
                    ],
                    // Everyone behind one address shares this bucket (an office NAT, a mobile carrier's CGNAT),
                    // so it gets five times the budget of a single visitor, the ratio Symfony's login throttling
                    // keeps between its per IP limiter and its per username one. It still stops a guesser that
                    // discards its session cookie after every attempt
                    self::IP_RATE_LIMITER_NAME => [
                        'policy' => 'sliding_window',
                        'limit' => 50,
                        'interval' => '1 minute',
                        'lock_factory' => null,
                    ],
                ],
            ],
        ];

        foreach ($configuration as $extension => $config) {
            $container->prependExtensionConfig($extension, $config);
        }
    }
}
