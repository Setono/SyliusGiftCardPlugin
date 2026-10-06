<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\Type;

use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeNormalizerInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardDesignProviderInterface;
use Sylius\Bundle\ChannelBundle\Form\Type\ChannelChoiceType;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;

final class GiftCardType extends AbstractResourceType
{
    /**
     * @param RepositoryInterface<CurrencyInterface> $currencyRepository
     * @param class-string<GiftCardDesignInterface> $designClass
     * @param RepositoryInterface<ChannelInterface> $channelRepository
     * @param list<string> $validationGroups
     */
    public function __construct(
        string $dataClass,
        private readonly RepositoryInterface $currencyRepository,
        private readonly GiftCardCodeGeneratorInterface $giftCardCodeGenerator,
        private readonly GiftCardCodeNormalizerInterface $giftCardCodeNormalizer,
        private readonly int $minimumCodeLength,
        private readonly string $designClass,
        private readonly GiftCardDesignProviderInterface $designProvider,
        private readonly RepositoryInterface $channelRepository,
        array $validationGroups = [],
    ) {
        parent::__construct($dataClass, $validationGroups);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
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
                    // A card created disabled or already expired is not emailed: the customer would receive a
                    // gift that does not work. It can be sent from the gift card page once it is usable
                    'help' => 'setono_sylius_gift_card.form.gift_card.send_notification_email_help',
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

            // A new card is issued with the code its form shows: the generated one, or one the admin types over it.
            // The field is submitted for that reason, since Sylius builds a fresh card on the POST whose own generated
            // code would otherwise be saved instead of the one the admin may have written down. Once the card exists
            // its code is what the customer was given, so it is shown, but locked
            $isNew = null === $giftCard->getId();
            $event->getForm()->add('code', TextType::class, [
                'label' => 'sylius.ui.code',
                'disabled' => !$isNew,
                'help' => $isNew ? 'setono_sylius_gift_card.form.gift_card.code_help' : null,
                'help_translation_parameters' => ['%minimum%' => $this->minimumCodeLength],
            ]);

            $channel = $giftCard->getChannel();
            $preferredCurrency = $channel instanceof ChannelInterface ? $channel->getBaseCurrency() : null;
            $preferredChoices = $preferredCurrency instanceof CurrencyInterface ? [$preferredCurrency->getCode()] : [];

            // The currency is part of the card's value: the balance and every ledger row are integers in its minor
            // units, so changing it after issuance would silently revalue the card. Like the channel it is chosen
            // while the card is new; afterwards it is shown, but locked
            $event->getForm()->add('currencyCode', ChoiceType::class, [
                'label' => 'sylius.ui.currency',
                'choices' => $this->currencyRepository->findAll(),
                'choice_label' => 'code',
                'choice_value' => 'code',
                'preferred_choices' => $preferredChoices,
                'disabled' => null !== $giftCard->getId(),
            ]);

            // How the card reaches the customer is settled when it is issued. A card bought in the shop takes it from
            // its variant, which also decides whether the order ships it, so changing it afterwards would only make
            // the card disagree with its order: nothing is shipped, or held back, because of it
            $event->getForm()->add('deliveryType', EnumType::class, [
                'label' => 'setono_sylius_gift_card.ui.delivery_type',
                'class' => GiftCardDeliveryType::class,
                'choice_label' => static fn (GiftCardDeliveryType $deliveryType): string => 'setono_sylius_gift_card.ui.delivery_type_' . $deliveryType->value,
                // A blank submission means the default, rather than a null the card cannot hold
                'empty_data' => GiftCardDeliveryType::Virtual->value,
                'disabled' => !$isNew,
                'help' => $isNew ? 'setono_sylius_gift_card.form.gift_card.delivery_type_help' : null,
            ]);

            $this->addDesign($event->getForm(), $giftCard);
        });

        // The cart normalizes the code a customer types before looking it up, so a code stored the way the admin typed
        // it, in lowercase or with dashes and spaces, could never be redeemed. It is normalized before it is validated,
        // which also makes the uniqueness check compare codes the way the cart tells them apart, and an invalid form
        // shows the admin the code the card would have been given
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if (!is_array($data) || !isset($data['code']) || !is_string($data['code'])) {
                return;
            }

            $data['code'] = $this->giftCardCodeNormalizer->normalize($data['code']);
            $event->setData($data);
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

            $minorUnits = round($amount * 100);

            // PHP wraps a float beyond its integer range around to an arbitrary integer, which the constraints could
            // take for a valid amount. The number field holds a number to these same bounds, but only before it is
            // multiplied here. (float) PHP_INT_MAX is 2^63, one more than the largest integer
            if ($minorUnits >= \PHP_INT_MAX || $minorUnits <= -\PHP_INT_MAX) {
                throw new TransformationFailedException(sprintf('The amount %s is beyond the range of an integer in minor units.', $amount));
            }

            return (int) $minorUnits;
        }));
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_gift_card_gift_card';
    }

    /**
     * The design only decides what the card's PDF looks like, so it can be changed at any time. It is optional: a card
     * without one prints the default layout.
     *
     * An existing card offers the designs its channel offers its customers, along with the one the card already has,
     * so the card keeps its design when it is edited even if that design has been disabled since. A new card has its
     * channel chosen on this same form, so it offers the designs of every channel, each telling which channels offer
     * it, and a design its channel does not offer is turned down when the card is validated
     *
     * @param FormInterface<mixed> $form
     */
    private function addDesign(FormInterface $form, GiftCardInterface $giftCard): void
    {
        /** @var array<string, GiftCardDesignInterface> $designs by code */
        $designs = [];

        /** @var array<string, list<string>> $channelCodes the codes of the channels offering each design, by design code */
        $channelCodes = [];

        $channel = $giftCard->getChannel();
        if (null !== $giftCard->getId() && null !== $channel) {
            foreach ($this->designProvider->getDesigns($channel) as $design) {
                $designs[(string) $design->getCode()] = $design;
            }

            $current = $giftCard->getDesign();
            if (null !== $current) {
                $designs[(string) $current->getCode()] ??= $current;
            }
        } else {
            foreach ($this->channelRepository->findAll() as $offeringChannel) {
                foreach ($this->designProvider->getDesigns($offeringChannel) as $design) {
                    $designs[(string) $design->getCode()] ??= $design;
                    $channelCodes[(string) $design->getCode()][] = (string) $offeringChannel->getCode();
                }
            }
        }

        $form->add('design', EntityType::class, [
            'label' => 'setono_sylius_gift_card.ui.design',
            'class' => $this->designClass,
            'choices' => array_values($designs),
            'choice_label' => 'name',
            'choice_value' => 'code',
            // The template shows each design's front image, and narrows a new card's designs to its channel
            'choice_attr' => static function (GiftCardDesignInterface $design) use ($channelCodes): array {
                $attr = ['data-image-path' => $design->getFrontImage()?->getPath() ?? ''];
                if (isset($channelCodes[(string) $design->getCode()])) {
                    $attr['data-channels'] = implode(' ', $channelCodes[(string) $design->getCode()]);
                }

                return $attr;
            },
            'expanded' => true,
            'required' => false,
            'placeholder' => 'setono_sylius_gift_card.form.gift_card.no_design',
            'help' => 'setono_sylius_gift_card.form.gift_card.design_help',
        ]);
    }
}
