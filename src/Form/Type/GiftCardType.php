<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\Type;

use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Bundle\ChannelBundle\Form\Type\ChannelChoiceType;
use Sylius\Bundle\ResourceBundle\Form\EventSubscriber\AddCodeFormSubscriber;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class GiftCardType extends AbstractResourceType
{
    /**
     * @param RepositoryInterface<CurrencyInterface> $currencyRepository
     * @param list<string> $validationGroups
     */
    public function __construct(
        string $dataClass,
        private readonly RepositoryInterface $currencyRepository,
        private readonly GiftCardCodeGeneratorInterface $giftCardCodeGenerator,
        array $validationGroups = [],
    ) {
        parent::__construct($dataClass, $validationGroups);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventSubscriber(new AddCodeFormSubscriber());
        $builder->add('customer', CustomerAutocompleteChoiceType::class, [
            'label' => 'sylius.ui.customer',
        ]);
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            /** @var GiftCardInterface $giftCard */
            $giftCard = $event->getData();

            // The channel can only be chosen while the gift card is new; afterwards it is fixed
            if (null === $giftCard->getId()) {
                $event->getForm()->add('channel', ChannelChoiceType::class, [
                    'label' => 'sylius.ui.channel',
                ]);
                $event->getForm()->add('sendNotificationEmail', CheckboxType::class, [
                    'required' => false,
                    'label' => 'setono_sylius_gift_card.form.gift_card.send_notification_email',
                ]);
            } else {
                // The balance is only settable while the card is being issued. Afterwards it belongs to the
                // balance operator, which records every movement in the transaction ledger — editing the
                // amount here would move the balance without leaving any trace of why. It is removed rather
                // than never added, so the minor units transformer below still has a field to attach to
                $event->getForm()->remove('amount');
            }
        });
        $builder->add('amount', NumberType::class, [
            'label' => 'sylius.ui.amount',
        ]);

        // A card issued from the admin starts its life at the amount it was created with
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            /** @var GiftCardInterface $giftCard */
            $giftCard = $event->getData();

            if (null === $giftCard->getId()) {
                $giftCard->setInitialAmount($giftCard->getAmount());
            }
        });
        $builder->add('enabled', CheckboxType::class, [
            'label' => 'sylius.ui.enabled',
            'required' => false,
        ]);
        $builder->add('customMessage', TextareaType::class, [
            'label' => 'setono_sylius_gift_card.form.gift_card.custom_message',
            'required' => false,
            'attr' => [
                'placeholder' => 'setono_sylius_gift_card.form.gift_card.custom_message_placeholder',
            ],
        ]);
        $builder->add('expiresAt', DateType::class, [
            'label' => 'setono_sylius_gift_card.form.gift_card.expires_at',
            'widget' => 'single_text',
            'html5' => true,
            'input' => 'datetime',
            'required' => false,
        ]);
        // A gift card stays valid through the end of its expiry day, so the admin only picks a date and the time is
        // pinned to 23:59:59
        $builder->get('expiresAt')->addModelTransformer(new CallbackTransformer(
            static fn (?\DateTimeInterface $date): ?\DateTimeInterface => $date,
            static function (?\DateTimeInterface $date): ?\DateTimeInterface {
                if (null === $date) {
                    return null;
                }

                return \DateTime::createFromInterface($date)->setTime(23, 59, 59);
            },
        ));
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            /** @var GiftCardInterface $giftCard */
            $giftCard = $event->getData();

            if ($giftCard->getCode() === null) {
                $giftCard->setCode($this->giftCardCodeGenerator->generate());
            }

            $channel = $giftCard->getChannel();
            $preferredCurrency = $channel instanceof ChannelInterface ? $channel->getBaseCurrency() : null;
            $preferredChoices = $preferredCurrency instanceof CurrencyInterface ? [$preferredCurrency->getCode()] : [];

            $event->getForm()->add('currencyCode', ChoiceType::class, [
                'label' => 'sylius.ui.currency',
                'choices' => $this->currencyRepository->findAll(),
                'choice_label' => 'code',
                'choice_value' => 'code',
                'preferred_choices' => $preferredChoices,
            ]);
        });

        $builder->get('amount')->addModelTransformer(new CallbackTransformer(static function (?int $amount): ?float {
            if (null === $amount) {
                return null;
            }

            return round($amount / 100, 2);
        }, static function (?float $amount): ?int {
            if (null === $amount) {
                return null;
            }

            return (int) round($amount * 100);
        }));
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_gift_card_gift_card';
    }
}
