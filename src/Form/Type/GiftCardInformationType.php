<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\Type;

use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardDesignProviderInterface;
use Sylius\Bundle\MoneyBundle\Form\Type\MoneyType;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Valid;

/**
 * @extends AbstractType<\Setono\SyliusGiftCardPlugin\Order\GiftCardInformationInterface>
 */
final class GiftCardInformationType extends AbstractType
{
    private const VALIDATION_GROUPS = ['setono_sylius_gift_card'];

    public function __construct(
        private readonly string $dataClass,
        private readonly string $designClass,
        private readonly ChannelContextInterface $channelContext,
        private readonly GiftCardDesignProviderInterface $designProvider,
        private readonly int $maximumMessageLength,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var ChannelInterface $channel */
        $channel = $this->channelContext->getChannel();
        $currencyCode = (string) $channel->getBaseCurrency()?->getCode();

        $designs = $this->designProvider->getDesigns($channel);

        $builder
            ->add('amount', MoneyType::class, [
                'label' => 'setono_sylius_gift_card.form.gift_card_information.amount',
                'currency' => $currencyCode,
            ])
            ->add('customMessage', TextareaType::class, [
                'label' => 'setono_sylius_gift_card.form.gift_card_information.custom_message',
                'help' => 'setono_sylius_gift_card.form.gift_card_information.custom_message_help',
                'help_translation_parameters' => ['%limit%' => $this->maximumMessageLength],
                'required' => false,
                'attr' => [
                    'maxlength' => $this->maximumMessageLength,
                    'placeholder' => 'setono_sylius_gift_card.form.gift_card_information.custom_message_placeholder',
                ],
            ])
            ->add('design', EntityType::class, [
                'label' => 'setono_sylius_gift_card.form.gift_card_information.design',
                'class' => $this->designClass,
                'choices' => $designs,
                'choice_label' => 'name',
                'choice_value' => 'code',
                'expanded' => true,
                'multiple' => false,
                'data' => $designs[0] ?? null,
                'choice_attr' => static function (?GiftCardDesignInterface $design): array {
                    $front = $design?->getFrontImage();

                    return ['data-image-path' => $front?->getPath() ?? ''];
                },
                // A channel without designs skips the picker, so a design is only mandatory when there is one to pick
                'constraints' => [] === $designs ? [] : [
                    new NotBlank(['groups' => self::VALIDATION_GROUPS]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => $this->dataClass,
            'validation_groups' => self::VALIDATION_GROUPS,
            // The rules live in the validation mapping of the data class. A form that is not the root of its tree
            // (this one sits inside Sylius' add to cart form) only validates its data object when told to cascade
            // into it, so without this the mapping would apply on its own and never in the shop
            'constraints' => [new Valid()],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_gift_card_gift_card_information';
    }
}
