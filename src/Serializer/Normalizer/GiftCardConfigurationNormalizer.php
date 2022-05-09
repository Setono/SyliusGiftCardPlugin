<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Serializer\Normalizer;

use ArrayObject;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Setono\SyliusGiftCardPlugin\Exception\UnexpectedTypeException;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Webmozart\Assert\Assert;

final class GiftCardConfigurationNormalizer implements ContextAwareNormalizerInterface
{
    private ObjectNormalizer $objectNormalizer;

    private CacheManager $cacheManager;

    public function __construct(ObjectNormalizer $objectNormalizer, CacheManager $cacheManager)
    {
        $this->objectNormalizer = $objectNormalizer;
        $this->cacheManager = $cacheManager;
    }

    /**
     * @param GiftCardConfigurationInterface|mixed $object
     *
     * @return array|ArrayObject
     */
    public function normalize($object, string $format = null, array $context = [])
    {
        Assert::isInstanceOf($object, GiftCardConfigurationInterface::class);

        $data = $this->objectNormalizer->normalize($object, $format, $context);
        if (!is_array($data) && !$data instanceof ArrayObject) {
            throw new UnexpectedTypeException($data, 'array', ArrayObject::class);
        }

        $image = $object->getBackgroundImage();
        if (null === $image) {
            return $data;
        }

        $path = $image->getPath();
        if (null === $path) {
            return $data;
        }

        $data['image'] = $this->cacheManager->getBrowserPath($path, 'setono_sylius_gift_card_background');

        return $data;
    }

    /**
     * @param mixed $data
     */
    public function supportsNormalization($data, string $format = null, array $context = []): bool
    {
        $groups = (array) ($context['groups'] ?? []);

        return $data instanceof GiftCardConfigurationInterface && in_array('setono:sylius-gift-card:preview', $groups, true);
    }
}
