<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\Type;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Bundle\MoneyBundle\Form\Type\MoneyType;
use Sylius\Bundle\ResourceBundle\Form\EventSubscriber\AddCodeFormSubscriber;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class GiftCardType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->addEventSubscriber(new AddCodeFormSubscriber())
            ->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) {
                /** @var GiftCardInterface $giftCardCode */
                $giftCardCode = $event->getData();

                /** @var ChannelInterface $channel */
                $channel = $giftCardCode->getChannel();

                /** @var CurrencyInterface $currency */
                $currency = $channel->getBaseCurrency();

                $form = $event->getForm();
                $form
                    ->add('amount', MoneyType::class, [
                        'label' => 'setono_sylius_gift_card.ui.amount',
                        'currency' => $currency->getCode(),
                    ])
                ;
            })
            ->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event) {
                /** @var GiftCardInterface $giftCardCode */
                $giftCardCode = $event->getData();

                /** @var ChannelInterface $channel */
                $channel = $giftCardCode->getChannel();

                /** @var CurrencyInterface $currency */
                $currency = $channel->getBaseCurrency();

                $giftCardCode->setCurrencyCode(
                    $currency->getCode()
                );

                $giftCardCode->enable();
            })
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_gift_card_gift_card_code';
    }
}
