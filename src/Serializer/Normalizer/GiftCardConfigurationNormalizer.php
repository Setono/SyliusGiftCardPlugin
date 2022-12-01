<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Serializer\Normalizer;

use ArrayObject;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Setono\MainRequestTrait\MainRequestTrait;
use Setono\SyliusGiftCardPlugin\Exception\UnexpectedTypeException;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Webmozart\Assert\Assert;

final class GiftCardConfigurationNormalizer implements ContextAwareNormalizerInterface
{
    use MainRequestTrait;

    private ObjectNormalizer $objectNormalizer;

    private CacheManager $cacheManager;

    private RequestStack $requestStack;

    public function __construct(
        ObjectNormalizer $objectNormalizer,
        CacheManager $cacheManager,
        RequestStack $requestStack
    ) {
        $this->objectNormalizer = $objectNormalizer;
        $this->cacheManager = $cacheManager;
        $this->requestStack = $requestStack;
    }

    /**
     * @param GiftCardConfigurationInterface|mixed $object
     * @param string $format
     *
     * @return array|ArrayObject
     */
    public function normalize($object, $format = null, array $context = [])
    {
        Assert::isInstanceOf($object, GiftCardConfigurationInterface::class);

        $data = $this->objectNormalizer->normalize($object, $format, $context);
        if (!is_array($data) && !$data instanceof ArrayObject) {
            throw new UnexpectedTypeException($data, 'array', ArrayObject::class);
        }

        $data['image'] = '';

        $request = $this->getMainRequestFromRequestStack($this->requestStack);
        if (null !== $request) {
            $data['image'] = $request->getSchemeAndHttpHost() . '/bundles/setonosyliusgiftcardplugin/setono-logo.png';
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
     * @param string $format
     */
    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        $groups = (array) ($context['groups'] ?? []);

        return $data instanceof GiftCardConfigurationInterface && in_array(
            'setono:sylius-gift-card:preview',
            $groups,
            true
        );
    }
}
