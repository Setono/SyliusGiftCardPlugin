<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\Type;

use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Bundle\MoneyBundle\Form\Type\MoneyType;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Webmozart\Assert\Assert;

final class AddToCartGiftCardInformationType extends AbstractType
{
    private string $dataClass;

    private array $validationGroups;

    private CurrencyContextInterface $currencyContext;

    private ProductVariantResolverInterface $productVariantResolver;

    private ChannelContextInterface $channelContext;

    public function __construct(
        string $dataClass,
        array $validationGroups,
        CurrencyContextInterface $currencyContext,
        ProductVariantResolverInterface $productVariantResolver,
        ChannelContextInterface $channelContext
    ) {
        $this->dataClass = $dataClass;
        $this->validationGroups = $validationGroups;
        $this->currencyContext = $currencyContext;
        $this->productVariantResolver = $productVariantResolver;
        $this->channelContext = $channelContext;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options): void {
            $form = $event->getForm();
            /** @var ProductInterface|null $product */
            $product = $options['product'];
            if (null === $product || !$product->isGiftCardAmountConfigurable()) {
                return;
            }

            /** @var ProductVariantInterface|null $variant */
            $variant = $this->productVariantResolver->getVariant($product);
            $defaultAmount = 0;
            if (null !== $variant) {
                /** @var ChannelInterface $channel */
                $channel = $this->channelContext->getChannel();
                Assert::isInstanceOf($channel, ChannelInterface::class);
                $channelPricing = $variant->getChannelPricingForChannel($channel);
                if (null !== $channelPricing) {
                    $defaultAmount = $channelPricing->getPrice();
                }
            }

            $form->add('amount', MoneyType::class, [
                'label' => 'setono_sylius_gift_card.form.add_to_cart.gift_card_information.amount',
                'currency' => $this->currencyContext->getCurrencyCode(),
                'data' => $defaultAmount,
            ]);
        });

        $builder->add('customMessage', TextareaType::class, [
            'required' => false,
            'label' => 'setono_sylius_gift_card.form.add_to_cart.gift_card_information.custom_message',
            'attr' => [
                'placeholder' => 'setono_sylius_gift_card.form.add_to_cart.gift_card_information.custom_message_placeholder',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('data_class', $this->dataClass);
        $resolver->setDefault('validation_groups', $this->validationGroups);
        $resolver->setDefault('product', null);
        $resolver->addAllowedTypes('product', ['null', ProductInterface::class]);
    }
}
