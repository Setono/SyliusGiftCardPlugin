<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Serializer\Normalizer;

use ArrayObject;
use Setono\SyliusGiftCardPlugin\Exception\UnexpectedTypeException;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Webmozart\Assert\Assert;

final class GiftCardConfigurationNormalizer implements ContextAwareNormalizerInterface
{
    private ObjectNormalizer $objectNormalizer;

    private RequestStack $requestStack;

    private string $publicMediaDirectory;

    public function __construct(
        ObjectNormalizer $objectNormalizer,
        RequestStack $requestStack,
        string $publicMediaDirectory
    ) {
        $this->objectNormalizer = $objectNormalizer;
        $this->requestStack = $requestStack;
        $this->publicMediaDirectory = trim($publicMediaDirectory, '/');
    }

    /**
     * @param GiftCardConfigurationInterface|mixed $object
     * @param string $format
     */
    public function normalize($object, $format = null, array $context = []): array
    {
        Assert::isInstanceOf($object, GiftCardConfigurationInterface::class);

        $data = $this->objectNormalizer->normalize($object, $format, $context);
        if (!is_array($data) && !$data instanceof ArrayObject) {
            throw new UnexpectedTypeException($data, 'array', ArrayObject::class);
        }

        if ($data instanceof ArrayObject) {
            $data = $data->getArrayCopy();
        }

        $data['image'] = '';

        $request = $this->requestStack->getMainRequest();
        if (null !== $request) {
            $data['image'] = $request->getSchemeAndHttpHost() . '/bundles/setonosyliusgiftcardplugin/setono-logo.png';
        }

        $image = $object->getBackgroundImage();
        if (null === $image) {
            return $data;
        }

        $path = $image->getPath();
        if (null !== $path && null !== $request) {
            $data['image'] = sprintf('%s/%s/%s', $request->getSchemeAndHttpHost(), $this->publicMediaDirectory, $path);
        }

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
            'setono:sylius-gift-card:render',
            $groups,
            true
        );
    }
}
