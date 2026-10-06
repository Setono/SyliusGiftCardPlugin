<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Form\Type;

use PHPUnit\Framework\Assert;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;

/**
 * Reads the errors a form or field holds itself, not those of its children, so a test can assert what a field reports
 */
trait FormErrorsTrait
{
    /**
     * The message templates as the validation mapping declares them, before any parameter is filled in
     *
     * @param FormInterface<mixed> $form
     *
     * @return list<string>
     */
    private static function errorMessageTemplates(FormInterface $form): array
    {
        return array_map(static fn (FormError $error): string => $error->getMessageTemplate(), self::errors($form));
    }

    /**
     * The messages with their parameters filled in
     *
     * @param FormInterface<mixed> $form
     *
     * @return list<string>
     */
    private static function errorMessages(FormInterface $form): array
    {
        return array_map(static fn (FormError $error): string => $error->getMessage(), self::errors($form));
    }

    /**
     * @param FormInterface<mixed> $form
     *
     * @return list<FormError>
     */
    private static function errors(FormInterface $form): array
    {
        $errors = [];
        foreach ($form->getErrors() as $error) {
            Assert::assertInstanceOf(FormError::class, $error);
            $errors[] = $error;
        }

        return $errors;
    }
}
