# UPGRADE FROM `0.11` TO `0.12`

## Gift cards are now configurable

From now on, Gift cards can have a custom amount given by the customer, and a custom message that can later be used to
send emails for example

1. Added an override of `Sylius\Bundle\OrderBundle\Controller\AddToCardCommand` so if your application was referencing 
   this class instead of the interface, you should change it. Please also note that a new parameter 
   `setono_sylius_gift_card.order.model.add_to_cart_command.class` has been introduced to allow an easier override

1. Added an override of `Sylius\Component\Core\Model\OrderItem` with a new Trait: 
   `Setono\SyliusGiftCardPlugin\Model\OrderItemTrait`. See [Installation](/README.md#Extend entities) for procedure

1. Added property `giftCardAmountConfigurable` to `Product`. Override the template `@SyliusAdmin/Product/Tab/_details.html.twig`
   to add
    ```twig
    {{ form_row(form.giftCardAmountConfigurable) }}
    ```

1. Removed method `create(OrderInterface $order): void` from `Setono\SyliusGiftCardPlugin\Operator\OrderGiftCardOperatorInterface`
   and its associate SM callback on payment

1. Added method `associateToCustomer(OrderInterface $order): void` to `Setono\SyliusGiftCardPlugin\Operator\OrderGiftCardOperatorInterface`
   along with a sm callback on checkout complete
