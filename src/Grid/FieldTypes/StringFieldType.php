<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Grid\FieldTypes;

use InvalidArgumentException;
use Sylius\Component\Grid\Definition\Field;
use Sylius\Component\Grid\FieldTypes\FieldTypeInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Throwable;
use Webmozart\Assert\Assert;

final class StringFieldType implements FieldTypeInterface
{
    private PropertyAccessorInterface $propertyAccessor;

    public function __construct(PropertyAccessorInterface $propertyAccessor)
    {
        $this->propertyAccessor = $propertyAccessor;
    }

    public function render(Field $field, $data, array $options): string
    {
        try {
            if (!is_object($data) && !is_array($data)) {
                throw new InvalidArgumentException('The $data should be either an array or an object');
            }

            /** @var mixed $value */
            $value = $this->propertyAccessor->getValue($data, $field->getPath());
            Assert::true(self::isStringable($value));
        } catch (Throwable $e) {
            return '';
        }

        return htmlspecialchars((string) $value);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
    }

    /**
     * @param mixed $value
     * @psalm-assert-if-true null|scalar|object $value
     */
    private static function isStringable($value): bool
    {
        return $value === null || is_scalar($value) || (is_object($value) && method_exists($value, '__toString'));
    }
}
