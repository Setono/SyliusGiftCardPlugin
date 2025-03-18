<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Serializer\Normalizer;

use ArrayObject;
use Setono\SyliusGiftCardPlugin\Exception\UnexpectedTypeException;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Webmozart\Assert\Assert;

final class GiftCardNormalizer implements NormalizerInterface
{
    public function __construct(
        private readonly ObjectNormalizer $objectNormalizer,
        private readonly MoneyFormatterInterface $moneyFormatter
    ) {
    }

    /**
     * @param GiftCardInterface|mixed $object
     * @param string $format
     */
    public function normalize($object, $format = null, array $context = []): array
    {
        Assert::isInstanceOf($object, GiftCardInterface::class);

        $data = $this->objectNormalizer->normalize($object, $format, $context);
        if (!is_array($data) && !$data instanceof ArrayObject) {
            throw new UnexpectedTypeException($data, 'array', ArrayObject::class);
        }

        if ($data instanceof ArrayObject) {
            $data = $data->getArrayCopy();
        }

        $localeCode = isset($context['localeCode']) && is_string($context['localeCode']) ? $context['localeCode'] : 'en_US';
        $data['amount'] = $this->moneyFormatter->format($object->getAmount(), (string) $object->getCurrencyCode(), $localeCode);

        return $data;
    }

    /**
     * @param mixed $data
     * @param string $format
     */
    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        $groups = (array) ($context['groups'] ?? []);

        return $data instanceof GiftCardInterface && in_array('setono:sylius-gift-card:render', $groups, true);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            GiftCardInterface::class => true,
        ];
    }
}
