<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\DependencyInjection;

use Composer\InstalledVersions;
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

    /**
     * A maximum below the minimum leaves no amount the shop accepts, so it could not sell a gift card at all. The two
     * are compared once every configuration file is merged, so they may be set in different files
     *
     * @param list<array<string, mixed>> $configs
     *
     * @dataProvider provideMaximumAmountsBelowTheMinimumAmount
     *
     * @test
     */
    public function it_rejects_a_maximum_amount_below_the_minimum_amount(array $configs, string $expectedMessage): void
    {
        $this->assertConfigurationIsInvalid($configs, $expectedMessage);
    }

    /**
     * @return iterable<string, array{list<array<string, mixed>>, string}>
     */
    public static function provideMaximumAmountsBelowTheMinimumAmount(): iterable
    {
        yield 'far below' => [
            [['purchase' => ['minimum_amount' => 1000, 'maximum_amount' => 500]]],
            'Invalid configuration for path "setono_sylius_gift_card.purchase": The maximum_amount (500) must be at least the minimum_amount (1000)',
        ];

        yield 'one below' => [
            [['purchase' => ['minimum_amount' => 1000, 'maximum_amount' => 999]]],
            'Invalid configuration for path "setono_sylius_gift_card.purchase": The maximum_amount (999) must be at least the minimum_amount (1000)',
        ];

        yield 'below the default minimum' => [
            [['purchase' => ['maximum_amount' => 50]]],
            'Invalid configuration for path "setono_sylius_gift_card.purchase": The maximum_amount (50) must be at least the minimum_amount (100)',
        ];

        yield 'set in different files' => [
            [['purchase' => ['maximum_amount' => 5000]], ['purchase' => ['minimum_amount' => 10000]]],
            'Invalid configuration for path "setono_sylius_gift_card.purchase": The maximum_amount (5000) must be at least the minimum_amount (10000)',
        ];
    }

    /**
     * A maximum equal to the minimum is a shop selling gift cards of a single amount
     *
     * @test
     */
    public function it_allows_a_maximum_amount_equal_to_the_minimum_amount(): void
    {
        $this->assertProcessedConfigurationEquals([['purchase' => ['minimum_amount' => 5000, 'maximum_amount' => 5000]]], [
            'purchase' => ['minimum_amount' => 5000, 'maximum_amount' => 5000, 'maximum_message_length' => 200],
        ], 'purchase');
    }

    /** @test */
    public function it_allows_a_maximum_amount_above_the_minimum_amount(): void
    {
        $this->assertProcessedConfigurationEquals([['purchase' => ['minimum_amount' => 1000, 'maximum_amount' => 50000]]], [
            'purchase' => ['minimum_amount' => 1000, 'maximum_amount' => 50000, 'maximum_message_length' => 200],
        ], 'purchase');

        $this->assertProcessedConfigurationEquals([['purchase' => ['minimum_amount' => 1000, 'maximum_amount' => 1001]]], [
            'purchase' => ['minimum_amount' => 1000, 'maximum_amount' => 1001, 'maximum_message_length' => 200],
        ], 'purchase');
    }

    /**
     * Without a maximum there is nothing for the minimum to exceed, however high it is
     *
     * @test
     */
    public function it_allows_any_minimum_amount_without_a_maximum_amount(): void
    {
        $this->assertProcessedConfigurationEquals([['purchase' => ['minimum_amount' => 10000000]]], [
            'purchase' => ['minimum_amount' => 10000000, 'maximum_amount' => null, 'maximum_message_length' => 200],
        ], 'purchase');
    }

    /**
     * While it compiles the container, Symfony holds a placeholder for a value taken from an environment variable,
     * and the value itself is only known at runtime. The amounts are then not compared, rather than the placeholder
     * being compared as though it were an amount
     *
     * @param array{minimum_amount: int|string, maximum_amount: int|string} $purchase
     *
     * @dataProvider provideAmountsTakenFromEnvironmentVariables
     *
     * @test
     */
    public function it_does_not_compare_an_amount_taken_from_an_environment_variable(array $purchase): void
    {
        if (version_compare((string) InstalledVersions::getVersion('symfony/config'), '6.4.37', '<')) {
            self::markTestSkipped('Before symfony/config 6.4.37 an integer node held the dummy value of an environment variable to its minimum, so no integer option with a minimum took an environment variable');
        }

        $container = new ContainerBuilder();
        $container->register('env_var_processor', EnvVarProcessor::class)->addTag('container.env_var_processor');
        $container->registerExtension(new SetonoSyliusGiftCardExtension());
        $container->loadFromExtension('setono_sylius_gift_card', ['purchase' => $purchase]);

        (new RegisterEnvVarProcessorsPass())->process($container);
        (new MergeExtensionConfigurationPass())->process($container);
        (new ValidateEnvPlaceholdersPass())->process($container);

        foreach ($purchase as $option => $value) {
            self::assertSame($value, $container->resolveEnvPlaceholders($container->getParameter('setono_sylius_gift_card.purchase.' . $option)));
        }
    }

    /**
     * @return iterable<string, array{array{minimum_amount: int|string, maximum_amount: int|string}}>
     */
    public static function provideAmountsTakenFromEnvironmentVariables(): iterable
    {
        yield 'the minimum' => [['minimum_amount' => '%env(int:GIFT_CARD_MINIMUM_AMOUNT)%', 'maximum_amount' => 50000]];
        yield 'the maximum' => [['minimum_amount' => 1000, 'maximum_amount' => '%env(int:GIFT_CARD_MAXIMUM_AMOUNT)%']];
        yield 'both' => [['minimum_amount' => '%env(int:GIFT_CARD_MINIMUM_AMOUNT)%', 'maximum_amount' => '%env(int:GIFT_CARD_MAXIMUM_AMOUNT)%']];
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
