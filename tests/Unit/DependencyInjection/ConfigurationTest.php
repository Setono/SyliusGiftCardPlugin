<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\DependencyInjection;

use Composer\InstalledVersions;
use Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait;
use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\DependencyInjection\Configuration;
use Setono\SyliusGiftCardPlugin\DependencyInjection\SetonoSyliusGiftCardExtension;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
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

    /**
     * While it compiles the container, Symfony holds a placeholder for a value taken from an environment variable,
     * and the value itself is only known at runtime. The two lengths are then not compared, rather than the
     * placeholder being compared as though it were a length; the code generator compares them at runtime instead
     *
     * @param array<string, int|string> $config
     *
     * @dataProvider provideCodeLengthsTakenFromEnvironmentVariables
     *
     * @test
     */
    public function it_does_not_compare_a_code_length_taken_from_an_environment_variable(array $config): void
    {
        if (version_compare((string) InstalledVersions::getVersion('symfony/config'), '6.4.37', '<')) {
            self::markTestSkipped('Before symfony/config 6.4.37 an integer node held the dummy value of an environment variable to its minimum, so no integer option with a minimum took an environment variable');
        }

        $container = self::processThroughTheCompilerPasses($config);

        foreach ($config as $option => $value) {
            self::assertSame($value, $container->resolveEnvPlaceholders($container->getParameter('setono_sylius_gift_card.' . $option)));
        }
    }

    /**
     * @return iterable<string, array{array<string, int|string>}>
     */
    public static function provideCodeLengthsTakenFromEnvironmentVariables(): iterable
    {
        yield 'the minimum' => [['code_length' => 24, 'minimum_code_length' => '%env(int:GIFT_CARD_MINIMUM_CODE_LENGTH)%']];
        yield 'the minimum, next to the default code length' => [['minimum_code_length' => '%env(int:GIFT_CARD_MINIMUM_CODE_LENGTH)%']];
        yield 'the code length' => [['code_length' => '%env(int:GIFT_CARD_CODE_LENGTH)%', 'minimum_code_length' => 20]];
        yield 'both' => [['code_length' => '%env(int:GIFT_CARD_CODE_LENGTH)%', 'minimum_code_length' => '%env(int:GIFT_CARD_MINIMUM_CODE_LENGTH)%']];

        // A placeholder carries the name of its variable, so compared as text, whether a pair compiled came down to
        // the alphabetical order of the two names: above, the code length's variable sorts first, here it sorts last
        yield 'both, the code length on a variable sorting after the minimum\'s' => [['code_length' => '%env(int:SHOP_CODE_LENGTH)%', 'minimum_code_length' => '%env(int:GIFT_CARD_MINIMUM_CODE_LENGTH)%']];
    }

    /**
     * Only a value taken from an environment variable waits for runtime. An integer is still held to the rules while
     * the container compiles, with the same messages, even next to one that is taken from a variable
     *
     * @param array<string, int|string> $config
     *
     * @dataProvider provideCodeLengthsRefusedWhileCompiling
     *
     * @test
     */
    public function it_refuses_an_integer_code_length_that_breaks_the_rules_while_compiling(array $config, string $expectedMessage): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($expectedMessage);

        self::processThroughTheCompilerPasses($config);
    }

    /**
     * @return iterable<string, array{array<string, int|string>, string}>
     */
    public static function provideCodeLengthsRefusedWhileCompiling(): iterable
    {
        yield 'a code length below the minimum' => [
            ['code_length' => 16, 'minimum_code_length' => 20],
            'Invalid configuration for path "setono_sylius_gift_card": The code_length (16) must be at least the minimum_code_length (20)',
        ];

        yield 'a guessable code length, next to a minimum taken from an environment variable' => [
            ['code_length' => 11, 'minimum_code_length' => '%env(int:GIFT_CARD_MINIMUM_CODE_LENGTH)%'],
            'The value 11 is too small for path "setono_sylius_gift_card.code_length". Should be greater than or equal to 12',
        ];

        yield 'a guessable minimum, next to a code length taken from an environment variable' => [
            ['code_length' => '%env(int:GIFT_CARD_CODE_LENGTH)%', 'minimum_code_length' => 11],
            'The value 11 is too small for path "setono_sylius_gift_card.minimum_code_length". Should be greater than or equal to 12',
        ];
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

        // 1 is the lowest maximum there is, and it takes a minimum as low, since the maximum cannot be below the minimum
        $this->assertProcessedConfigurationEquals([['purchase' => ['minimum_amount' => 1, 'maximum_amount' => 1]]], [
            'purchase' => ['minimum_amount' => 1, 'maximum_amount' => 1, 'maximum_message_length' => 200],
        ], 'purchase');
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
        if (version_compare((string) InstalledVersions::getVersion('symfony/config'), '6.4.37', '<')) {
            self::markTestSkipped('Before symfony/config 6.4.37 an integer node held the dummy value to its minimum as well, so no integer option with a minimum took an environment variable');
        }

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
     * A gift card's balance is a signed 32-bit integer column, so it holds at most 2147483647 minor units, and the
     * purchase limits may go as far as that
     *
     * @test
     *
     * @dataProvider provideAmountOptions
     */
    public function it_allows_an_amount_limit_of_the_most_a_gift_card_can_hold(string $option): void
    {
        $this->assertProcessedConfigurationEquals([['purchase' => [$option => 2147483647]]], [
            'purchase' => [$option => Configuration::MAXIMUM_AMOUNT],
        ], 'purchase.' . $option);
    }

    /**
     * Beyond what a card can hold, a minimum would leave no amount the shop accepts, and a maximum would be quoted on the
     * product page while the shop refused the amounts above the ceiling
     *
     * @test
     *
     * @dataProvider provideAmountOptions
     */
    public function it_rejects_an_amount_limit_beyond_the_most_a_gift_card_can_hold(string $option): void
    {
        $this->assertConfigurationIsInvalid(
            [['purchase' => [$option => 2147483648]]],
            sprintf('The value 2147483648 is too big for path "setono_sylius_gift_card.purchase.%s". Should be less than or equal to 2147483647', $option),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAmountOptions(): iterable
    {
        yield 'minimum_amount' => ['minimum_amount'];
        yield 'maximum_amount' => ['maximum_amount'];
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
     * Without a maximum there is nothing for the minimum to exceed, however high it is: with the key left out, with
     * null, or with the option missing from the tree altogether, as in a tree processed for one path of it
     *
     * @test
     */
    public function it_allows_any_minimum_amount_without_a_maximum_amount(): void
    {
        $this->assertProcessedConfigurationEquals([['purchase' => ['minimum_amount' => 10000000]]], [
            'purchase' => ['minimum_amount' => 10000000, 'maximum_amount' => null, 'maximum_message_length' => 200],
        ], 'purchase');

        $this->assertProcessedConfigurationEquals([['purchase' => ['minimum_amount' => 10000000, 'maximum_amount' => null]]], [
            'purchase' => ['minimum_amount' => 10000000, 'maximum_amount' => null, 'maximum_message_length' => 200],
        ], 'purchase');

        $this->assertProcessedConfigurationEquals([['purchase' => ['minimum_amount' => 10000000]]], [
            'purchase' => ['minimum_amount' => 10000000],
        ], 'purchase.minimum_amount');
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

    /**
     * An interval strtotime() can add to a date, in any of its units, and null for gift cards that never expire
     *
     * @dataProvider provideValidityPeriodsAcceptedWhileCompiling
     *
     * @test
     */
    public function it_takes_an_interval_or_null_as_the_validity_period(?string $period): void
    {
        $container = self::processThroughTheCompilerPasses(['default_validity_period' => $period]);

        self::assertSame($period, $container->getParameter('setono_sylius_gift_card.default_validity_period'));
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function provideValidityPeriodsAcceptedWhileCompiling(): iterable
    {
        yield 'years' => ['3 years'];
        yield 'months' => ['18 months'];
        yield 'weeks' => ['2 weeks'];
        yield 'days' => ['90 days'];
        yield 'one of a unit' => ['1 year'];
        yield 'more than one unit' => ['1 year 6 months'];
        yield 'null, for gift cards that never expire' => [null];
    }

    /**
     * While it compiles the container, Symfony checks a value taken from an environment variable against a dummy
     * value, '' for a string, which is no interval. The interval is then left to the value the variable has at
     * runtime, which GiftCardExpiryResolver checks when it uses it
     *
     * @dataProvider provideValidityPeriodsTakenFromEnvironmentVariables
     *
     * @test
     */
    public function it_takes_the_validity_period_from_an_environment_variable(string $period): void
    {
        $container = self::processThroughTheCompilerPasses(['default_validity_period' => $period]);

        self::assertSame($period, $container->resolveEnvPlaceholders($container->getParameter('setono_sylius_gift_card.default_validity_period')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidityPeriodsTakenFromEnvironmentVariables(): iterable
    {
        yield 'a variable' => ['%env(GIFT_CARD_VALIDITY)%'];
        yield 'a variable read as a string' => ['%env(string:GIFT_CARD_VALIDITY)%'];
    }

    /**
     * Only the interval of an environment variable waits for runtime. A value written in the configuration is still
     * held to the rule while the container compiles, with the message it always had, and so is the type an environment
     * variable gives, which is known by then
     *
     * @dataProvider provideValidityPeriodsRefusedWhileCompiling
     *
     * @test
     */
    public function it_refuses_an_invalid_validity_period_while_compiling(mixed $period, string $expectedMessage): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($expectedMessage);

        self::processThroughTheCompilerPasses(['default_validity_period' => $period]);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function provideValidityPeriodsRefusedWhileCompiling(): iterable
    {
        yield 'not an interval' => [
            'not a period',
            'Invalid configuration for path "setono_sylius_gift_card.default_validity_period": The default_validity_period must be a valid strtotime interval, e.g. "3 years": "not a period"',
        ];

        yield 'empty' => [
            '',
            'Invalid configuration for path "setono_sylius_gift_card.default_validity_period": The default_validity_period must be a valid strtotime interval, e.g. "3 years": ""',
        ];

        yield 'a number' => [
            3,
            'Invalid configuration for path "setono_sylius_gift_card.default_validity_period": The default_validity_period must be a valid strtotime interval, e.g. "3 years": 3',
        ];

        // strtotime() reads every one of these, but not as an interval and nothing else, and most of them give a card
        // that expires the day it is issued, or before
        foreach ([
            'a unit strtotime() does not know, read as a timezone' => '3 yrs',
            'a misspelled unit' => '3 yeers',
            'another misspelled unit' => '18 mnths',
            'a unit in another language' => '2 jahre',
            'a number without a unit, read as a timezone' => '3',
            'a day name, read as the third Monday from now' => '3 mon',
            'a day of the month' => '1 month last day of',
            'a date' => '2030-01-01',
            'a time of day' => '3 years noon',
            'a timezone' => '3 years UTC',
            'an interval of nothing' => '0 days',
            'a negative interval' => '-1 year',
            'an interval into the past' => '3 years ago',
            'a unit that goes back, after one that goes forward' => '1 year -18 months',
        ] as $name => $period) {
            yield $name => [
                $period,
                sprintf('Invalid configuration for path "setono_sylius_gift_card.default_validity_period": The default_validity_period must be a valid strtotime interval, e.g. "3 years": "%s"', $period),
            ];
        }

        yield 'an environment variable read as an integer' => [
            '%env(int:GIFT_CARD_VALIDITY)%',
            'Invalid type for path "setono_sylius_gift_card.default_validity_period". Expected "string", but got "int".',
        ];
    }

    protected function getConfiguration(): ConfigurationInterface
    {
        return new Configuration();
    }

    /**
     * Processes the plugin's configuration the way the container does while it compiles, environment variables
     * included
     *
     * @param array<string, mixed> $config
     */
    private static function processThroughTheCompilerPasses(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('env_var_processor', EnvVarProcessor::class)->addTag('container.env_var_processor');
        $container->registerExtension(new SetonoSyliusGiftCardExtension());
        $container->loadFromExtension('setono_sylius_gift_card', $config);

        (new RegisterEnvVarProcessorsPass())->process($container);
        (new MergeExtensionConfigurationPass())->process($container);
        (new ValidateEnvPlaceholdersPass())->process($container);

        return $container;
    }
}
