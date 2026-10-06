<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\DependencyInjection;

use Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait;
use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\DependencyInjection\Configuration;
use Setono\SyliusGiftCardPlugin\DependencyInjection\SetonoSyliusGiftCardExtension;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\Compiler\RegisterEnvVarProcessorsPass;
use Symfony\Component\DependencyInjection\Compiler\ValidateEnvPlaceholdersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\EnvVarProcessor;

final class ConfigurationTest extends TestCase
{
    use ConfigurationTestCaseTrait;

    /** @test */
    public function it_has_sensible_redemption_defaults(): void
    {
        $this->assertProcessedConfigurationEquals([[]], [
            'redemption' => [
                'payment_method_code' => 'gift_card',
                'rate_limiter' => 'limiter.setono_sylius_gift_card_apply',
                'ip_rate_limiter' => 'limiter.setono_sylius_gift_card_apply_ip',
            ],
        ], 'redemption');
    }

    /**
     * Rate limiting is what keeps a gift card code from being guessed, so it has to be on unless the shop
     * owner deliberately turns it off, and each bucket can be turned off on its own
     *
     * @test
     */
    public function it_allows_the_rate_limiters_to_be_turned_off(): void
    {
        $this->assertProcessedConfigurationEquals([['redemption' => ['rate_limiter' => null, 'ip_rate_limiter' => null]]], [
            'redemption' => [
                'payment_method_code' => 'gift_card',
                'rate_limiter' => null,
                'ip_rate_limiter' => null,
            ],
        ], 'redemption');

        $this->assertProcessedConfigurationEquals([['redemption' => ['ip_rate_limiter' => null]]], [
            'redemption' => [
                'payment_method_code' => 'gift_card',
                'rate_limiter' => 'limiter.setono_sylius_gift_card_apply',
                'ip_rate_limiter' => null,
            ],
        ], 'redemption');
    }

    /** @test */
    public function it_lets_the_application_name_its_own_rate_limiters(): void
    {
        $this->assertProcessedConfigurationEquals([['redemption' => ['rate_limiter' => 'limiter.shop_forms', 'ip_rate_limiter' => 'limiter.shop_forms_per_ip']]], [
            'redemption' => [
                'payment_method_code' => 'gift_card',
                'rate_limiter' => 'limiter.shop_forms',
                'ip_rate_limiter' => 'limiter.shop_forms_per_ip',
            ],
        ], 'redemption');
    }

    /**
     * @dataProvider provideRateLimiterOptions
     *
     * @test
     */
    public function it_rejects_a_rate_limiter_that_is_not_a_service_id(string $option): void
    {
        $this->assertConfigurationIsInvalid(
            [['redemption' => [$option => '']]],
            sprintf('The %s option must be the id of a rate limiter factory service', $option),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideRateLimiterOptions(): iterable
    {
        yield 'rate_limiter' => ['rate_limiter'];
        yield 'ip_rate_limiter' => ['ip_rate_limiter'];
    }

    /**
     * A gift card code is a bearer token, so a code short enough to be guessed is a configuration error
     * rather than a choice
     *
     * @test
     */
    public function it_rejects_a_guessable_code_length(): void
    {
        $this->assertConfigurationIsInvalid([['code_length' => 4]], 'code_length');

        $this->assertProcessedConfigurationEquals([['code_length' => 12]], [
            'code_length' => 12,
        ], 'code_length');
    }

    /**
     * The same reasoning holds for a code an admin types, so the floor of the minimum is not a choice either; a shop
     * can only raise it
     *
     * @test
     */
    public function it_holds_issued_codes_to_a_minimum_length_that_can_only_be_raised(): void
    {
        $this->assertProcessedConfigurationEquals([[]], [
            'minimum_code_length' => 12,
        ], 'minimum_code_length');

        $this->assertProcessedConfigurationEquals([['minimum_code_length' => 16]], [
            'minimum_code_length' => 16,
        ], 'minimum_code_length');

        $this->assertConfigurationIsInvalid([['minimum_code_length' => 11]], 'minimum_code_length');
    }

    /**
     * A generated code has to meet the minimum like a typed one
     *
     * @test
     */
    public function it_rejects_a_code_length_below_the_minimum_code_length(): void
    {
        $this->assertConfigurationIsInvalid(
            [['minimum_code_length' => 20, 'code_length' => 16]],
            'The code_length (16) must be at least the minimum_code_length (20)',
        );

        $this->assertConfigurationIsInvalid(
            [['minimum_code_length' => 20]],
            'The code_length (16) must be at least the minimum_code_length (20)',
        );

        $this->assertConfigurationIsValid([['minimum_code_length' => 20, 'code_length' => 20]]);
    }

    /** @test */
    public function it_has_sensible_purchase_defaults(): void
    {
        $this->assertProcessedConfigurationEquals([[]], [
            'purchase' => ['minimum_amount' => 100, 'maximum_amount' => null, 'maximum_message_length' => 200],
        ], 'purchase');
    }

    /**
     * Null is what the option says to write for no maximum, so it must mean the same as leaving the key out, and
     * like any other value it overrides what an earlier configuration file set
     *
     * @test
     */
    public function it_takes_null_for_no_maximum_amount(): void
    {
        $this->assertProcessedConfigurationEquals([[]], [
            'purchase' => ['maximum_amount' => null],
        ], 'purchase.maximum_amount');

        $this->assertProcessedConfigurationEquals([['purchase' => ['maximum_amount' => null]]], [
            'purchase' => ['maximum_amount' => null],
        ], 'purchase.maximum_amount');

        $this->assertProcessedConfigurationEquals([
            ['purchase' => ['maximum_amount' => 50000]],
            ['purchase' => ['maximum_amount' => null]],
        ], [
            'purchase' => ['maximum_amount' => null],
        ], 'purchase.maximum_amount');
    }

    /** @test */
    public function it_allows_a_maximum_amount(): void
    {
        $this->assertProcessedConfigurationEquals([['purchase' => ['maximum_amount' => 50000]]], [
            'purchase' => ['maximum_amount' => 50000],
        ], 'purchase.maximum_amount');

        $this->assertProcessedConfigurationEquals([['purchase' => ['maximum_amount' => 1]]], [
            'purchase' => ['maximum_amount' => 1],
        ], 'purchase.maximum_amount');
    }

    /**
     * @dataProvider provideInvalidMaximumAmounts
     *
     * @test
     */
    public function it_rejects_a_maximum_amount_that_is_not_a_whole_number_of_at_least_one(mixed $maximumAmount, string $expectedMessage): void
    {
        $this->assertConfigurationIsInvalid([['purchase' => ['maximum_amount' => $maximumAmount]]], $expectedMessage);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function provideInvalidMaximumAmounts(): iterable
    {
        yield 'zero' => [0, 'The value 0 is too small for path "setono_sylius_gift_card.purchase.maximum_amount". Should be greater than or equal to 1'];
        yield 'negative' => [-500, 'The value -500 is too small for path "setono_sylius_gift_card.purchase.maximum_amount". Should be greater than or equal to 1'];
        yield 'numeric string' => ['500', 'Invalid type for path "setono_sylius_gift_card.purchase.maximum_amount". Expected "int", but got "string".'];
        yield 'float' => [12.5, 'Invalid type for path "setono_sylius_gift_card.purchase.maximum_amount". Expected "int", but got "float".'];
        yield 'boolean' => [true, 'Invalid type for path "setono_sylius_gift_card.purchase.maximum_amount". Expected "int", but got "bool".'];
    }

    /**
     * While it compiles the container, Symfony checks a value taken from an environment variable against a dummy
     * value, 0 for an int. A validate() rule holding the maximum to at least 1 would refuse that, so the minimum is
     * left to the value the variable has at runtime, the way an integer node leaves it
     *
     * @test
     */
    public function it_takes_the_maximum_amount_from_an_environment_variable(): void
    {
        $container = new ContainerBuilder();
        $container->register('env_var_processor', EnvVarProcessor::class)->addTag('container.env_var_processor');
        $container->registerExtension(new SetonoSyliusGiftCardExtension());
        $container->loadFromExtension('setono_sylius_gift_card', [
            'purchase' => ['maximum_amount' => '%env(int:GIFT_CARD_MAXIMUM_AMOUNT)%'],
        ]);

        (new RegisterEnvVarProcessorsPass())->process($container);
        (new MergeExtensionConfigurationPass())->process($container);
        (new ValidateEnvPlaceholdersPass())->process($container);

        self::assertSame(
            '%env(int:GIFT_CARD_MAXIMUM_AMOUNT)%',
            $container->resolveEnvPlaceholders($container->getParameter('setono_sylius_gift_card.purchase.maximum_amount')),
        );
    }

    /**
     * A physical gift card is shipped with its code printed on it, so the code is not emailed unless the
     * merchant asks for it
     *
     * @test
     */
    public function it_does_not_email_physical_gift_cards_by_default(): void
    {
        $this->assertProcessedConfigurationEquals([[]], [
            'delivery' => ['email_physical_cards' => false],
        ], 'delivery');
    }

    /** @test */
    public function it_allows_emailing_physical_gift_cards(): void
    {
        $this->assertProcessedConfigurationEquals([['delivery' => ['email_physical_cards' => true]]], [
            'delivery' => ['email_physical_cards' => true],
        ], 'delivery');
    }

    /** @test */
    public function it_allows_the_maximum_message_length_to_be_changed(): void
    {
        $this->assertProcessedConfigurationEquals([['purchase' => ['maximum_message_length' => 80]]], [
            'purchase' => ['minimum_amount' => 100, 'maximum_amount' => null, 'maximum_message_length' => 80],
        ], 'purchase');
    }

    /** @test */
    public function it_rejects_a_maximum_message_length_below_one(): void
    {
        $this->assertPartialConfigurationIsInvalid(
            [['purchase' => ['maximum_message_length' => 0]]],
            'purchase.maximum_message_length',
        );
    }

    /** @test */
    public function it_rejects_a_maximum_message_length_the_column_cannot_hold(): void
    {
        $this->assertPartialConfigurationIsInvalid(
            [['purchase' => ['maximum_message_length' => 65536]]],
            'purchase.maximum_message_length',
        );
    }

    /** @test */
    public function it_has_sensible_scalar_defaults(): void
    {
        $this->assertProcessedConfigurationEquals([[]], [
            'code_length' => 16,
        ], 'code_length');

        $this->assertProcessedConfigurationEquals([[]], [
            'default_validity_period' => '3 years',
        ], 'default_validity_period');
    }

    /** @test */
    public function it_rejects_an_empty_payment_method_code(): void
    {
        $this->assertPartialConfigurationIsInvalid(
            [['redemption' => ['payment_method_code' => '']]],
            'redemption.payment_method_code',
        );
    }

    /** @test */
    public function it_rejects_an_invalid_validity_period(): void
    {
        $this->assertConfigurationIsInvalid(
            [['default_validity_period' => 'not a period']],
            'strtotime',
        );
    }

    protected function getConfiguration(): ConfigurationInterface
    {
        return new Configuration();
    }
}
