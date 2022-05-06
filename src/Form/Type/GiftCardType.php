<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\Type;

use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Bundle\ResourceBundle\Form\EventSubscriber\AddCodeFormSubscriber;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class GiftCardType extends AbstractResourceType
{
    private RepositoryInterface $currencyRepository;

    private GiftCardCodeGeneratorInterface $giftCardCodeGenerator;

    public function __construct(
        string $dataClass,
        RepositoryInterface $currencyRepository,
        GiftCardCodeGeneratorInterface $giftCardCodeGenerator,
        array $validationGroups = []
    ) {
        parent::__construct($dataClass, $validationGroups);

        $this->currencyRepository = $currencyRepository;
        $this->giftCardCodeGenerator = $giftCardCodeGenerator;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->addEventSubscriber(new AddCodeFormSubscriber())
            ->add('customer', CustomerAutocompleteChoiceType::class, [
                'label' => 'sylius.ui.customer',
            ])
            ->add('amount', NumberType::class, [
                'label' => 'sylius.ui.amount',
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'sylius.ui.enabled',
                'required' => false,
            ])
            ->add('customMessage', TextareaType::class, [
                'label' => 'setono_sylius_gift_card.form.gift_card.custom_message',
                'required' => false,
            ])
            ->add('expiresAt', DateTimeType::class, [
                'label' => 'setono_sylius_gift_card.form.gift_card.expires_at',
                'widget' => 'single_text',
                'html5' => true,
            ])
            ->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
                /** @var GiftCardInterface $giftCard */
                $giftCard = $event->getData();

                if ($giftCard->getCode() === null) {
                    $giftCard->setCode($this->giftCardCodeGenerator->generate());
                }

                /** @var ChannelInterface $channel */
                $channel = $giftCard->getChannel();

                /** @var CurrencyInterface $currency */
                $currency = $channel->getBaseCurrency();

                $form = $event->getForm();
                $form
                    ->add('currencyCode', ChoiceType::class, [
                        'label' => 'sylius.ui.currency',
                        'choices' => $this->currencyRepository->findAll(),
                        'choice_label' => 'code',
                        'choice_value' => 'code',
                        'preferred_choices' => [$currency->getCode()],
                    ]);
            })
        ;

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
