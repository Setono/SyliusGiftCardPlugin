<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Controller\Action;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Applicator\GiftCardApplicatorInterface;
use Setono\SyliusGiftCardPlugin\Checker\GiftCardEligibilityChecker;
use Setono\SyliusGiftCardPlugin\Controller\Action\AddGiftCardToOrderAction;
use Setono\SyliusGiftCardPlugin\Form\DataTransformer\GiftCardToCodeDataTransformer;
use Setono\SyliusGiftCardPlugin\Form\Type\AddGiftCardToOrderType;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Resolver\RedirectUrlResolverInterface;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardIsEligibleValidator;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Validator\ContainerConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;

/**
 * A gift card code is a bearer token, so this action is the one place in the shop where codes can be guessed.
 * These tests pin the two things that make guessing pointless: the attempts are throttled, and every rejected
 * code gives the same answer whatever the reason.
 */
final class AddGiftCardToOrderActionTest extends TestCase
{
    use ProphecyTrait;

    private const GENERIC_ERROR = 'setono_sylius_gift_card.gift_card.could_not_be_applied';

    private const TOO_MANY_ATTEMPTS = 'setono_sylius_gift_card.gift_card.too_many_attempts';

    /** @test */
    public function it_refuses_further_attempts_once_the_rate_limit_is_exhausted(): void
    {
        $cartContext = $this->prophesize(CartContextInterface::class);
        $cartContext->getCart()->willReturn($this->order()->reveal());

        $action = $this->createAction(
            $this->createFormFactory(null),
            $this->createRateLimiterFactory(3),
            $cartContext->reveal(),
        );

        $session = $this->session();

        for ($i = 0; $i < 3; ++$i) {
            $response = $action($this->createRequest('NOSUCHCODE12', $session));

            self::assertInstanceOf(RedirectResponse::class, $response);
        }

        self::assertSame(
            [self::GENERIC_ERROR, self::GENERIC_ERROR, self::GENERIC_ERROR],
            $session->getFlashBag()->get('error'),
        );

        $response = $action($this->createRequest('NOSUCHCODE12', $session));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame([self::TOO_MANY_ATTEMPTS], $session->getFlashBag()->get('error'));

        // the refused attempt never even reached the cart
        $cartContext->getCart()->shouldHaveBeenCalledTimes(3);
    }

    /** @test */
    public function it_refuses_attempts_from_a_fresh_session_on_an_exhausted_address(): void
    {
        $action = $this->createAction($this->createFormFactory(null), $this->createRateLimiterFactory(3));

        $session = $this->session();

        for ($i = 0; $i < 3; ++$i) {
            $action($this->createRequest('NOSUCHCODE12', $session, 'the-session-id'));
        }
        $session->getFlashBag()->get('error');

        // throwing the cookie away to start over does not hand out a new budget, because the address has one
        // of its own
        $action($this->createRequest('NOSUCHCODE12', $session, 'a-brand-new-session-id'));

        self::assertSame([self::TOO_MANY_ATTEMPTS], $session->getFlashBag()->get('error'));
    }

    /** @test */
    public function it_does_not_throttle_when_no_rate_limiter_is_configured(): void
    {
        $action = $this->createAction($this->createFormFactory(null), null);

        $session = $this->session();

        for ($i = 0; $i < 20; ++$i) {
            $action($this->createRequest('NOSUCHCODE12', $session));
        }

        self::assertSame(array_fill(0, 20, self::GENERIC_ERROR), $session->getFlashBag()->get('error'));
    }

    /**
     * The whole point of the exercise: whichever way a code is unusable, the shop says the same thing, so the
     * form cannot be used to find out which codes exist.
     *
     * @test
     */
    public function it_gives_the_same_error_whether_a_code_is_unknown_or_unusable(): void
    {
        // an unknown code and a disabled one are indistinguishable already at the repository, which only ever
        // hands out enabled gift cards
        $unknown = $this->errorsFor(null);
        $disabled = $this->errorsFor(null);

        $expired = $this->errorsFor($this->giftCard(static function (GiftCard $giftCard): void {
            $giftCard->setExpiresAt(new \DateTimeImmutable('-1 day'));
        }));

        $empty = $this->errorsFor($this->giftCard(static function (GiftCard $giftCard): void {
            $giftCard->setAmount(0);
        }));

        $otherChannel = $this->giftCard(static function (GiftCard $giftCard): void {
            $channel = new Channel();
            $channel->setCode('OTHER_CHANNEL');
            $giftCard->setChannel($channel);
        });

        $otherCurrency = $this->giftCard(static function (GiftCard $giftCard): void {
            $giftCard->setCurrencyCode('DKK');
        });

        self::assertSame([self::GENERIC_ERROR], $unknown);
        self::assertSame($unknown, $disabled);
        self::assertSame($unknown, $expired);
        self::assertSame($unknown, $empty);
        self::assertSame($unknown, $this->errorsFor($otherChannel));
        self::assertSame($unknown, $this->errorsFor($otherCurrency));
    }

    /** @test */
    public function it_applies_a_gift_card_that_can_be_used(): void
    {
        $giftCard = $this->giftCard();

        $order = $this->order()->reveal();
        $applicator = $this->prophesize(GiftCardApplicatorInterface::class);
        $applicator->apply($order, $giftCard)->shouldBeCalledOnce();

        $cartContext = $this->prophesize(CartContextInterface::class);
        $cartContext->getCart()->willReturn($order);

        $action = $this->createAction(
            $this->createFormFactory($giftCard),
            $this->createRateLimiterFactory(3),
            $cartContext->reveal(),
            $applicator->reveal(),
        );

        $session = $this->session();

        $action($this->createRequest('VALIDCODE123', $session));

        self::assertSame([], $session->getFlashBag()->get('error'));
        self::assertSame(['setono_sylius_gift_card.gift_card_added'], $session->getFlashBag()->get('success'));
    }

    /**
     * Runs a single attempt with the given gift card behind the submitted code and returns the error messages
     * the shop customer ends up with
     *
     * @return list<string>
     */
    private function errorsFor(?GiftCardInterface $giftCard): array
    {
        $action = $this->createAction($this->createFormFactory($giftCard), null);

        $session = $this->session();
        $action($this->createRequest('SOMEGIFTCARD', $session));

        /** @var list<string> $errors */
        $errors = $session->getFlashBag()->get('error');

        return $errors;
    }

    private function createAction(
        FormFactoryInterface $formFactory,
        ?RateLimiterFactory $rateLimiterFactory,
        ?CartContextInterface $cartContext = null,
        ?GiftCardApplicatorInterface $applicator = null,
    ): AddGiftCardToOrderAction {
        if (null === $cartContext) {
            $cartContextProphecy = $this->prophesize(CartContextInterface::class);
            $cartContextProphecy->getCart()->willReturn($this->order()->reveal());
            $cartContext = $cartContextProphecy->reveal();
        }

        $redirectUrlResolver = $this->prophesize(RedirectUrlResolverInterface::class);
        $redirectUrlResolver->getUrlToRedirectTo(Argument::cetera())->willReturn('/cart');

        $entityManager = $this->prophesize(EntityManagerInterface::class);

        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(Argument::any())->willReturn($entityManager->reveal());

        return new AddGiftCardToOrderAction(
            $formFactory,
            $cartContext,
            $applicator ?? $this->prophesize(GiftCardApplicatorInterface::class)->reveal(),
            $redirectUrlResolver->reveal(),
            $managerRegistry->reveal(),
            $rateLimiterFactory,
        );
    }

    /**
     * The real form, transformer and validator, because what a customer gets to see is decided by all three
     * together: the transformer for a code that matches nothing, the constraint for a code that matches a
     * gift card that cannot be used
     */
    private function createFormFactory(?GiftCardInterface $giftCard): FormFactoryInterface
    {
        $repository = $this->prophesize(GiftCardRepositoryInterface::class);
        $repository->findOneEnabledByCodeAndChannel(Argument::cetera())->willReturn($giftCard);

        $channelContext = $this->prophesize(ChannelContextInterface::class);
        $channelContext->getChannel()->willReturn($this->channel());

        $cartContext = $this->prophesize(CartContextInterface::class);
        $cartContext->getCart()->willReturn($this->order()->reveal());

        $validator = Validation::createValidatorBuilder()
            ->addXmlMapping(dirname(__DIR__, 4) . '/src/Resources/config/validation/AddGiftCardToOrderCommand.xml')
            ->setConstraintValidatorFactory(new ContainerConstraintValidatorFactory(new ServiceLocator([
                GiftCardIsEligibleValidator::class => static fn (): GiftCardIsEligibleValidator => new GiftCardIsEligibleValidator($cartContext->reveal(), new GiftCardEligibilityChecker()),
            ])))
            ->getValidator();

        $transformer = new GiftCardToCodeDataTransformer($repository->reveal(), $channelContext->reveal());

        return Forms::createFormFactoryBuilder()
            ->addExtension(new HttpFoundationExtension())
            ->addExtension(new ValidatorExtension($validator, false))
            ->addType(new AddGiftCardToOrderType($transformer, ['setono_sylius_gift_card']))
            ->getFormFactory();
    }

    private function createRateLimiterFactory(int $limit): RateLimiterFactory
    {
        return new RateLimiterFactory([
            'id' => 'setono_sylius_gift_card_apply',
            'policy' => 'sliding_window',
            'limit' => $limit,
            'interval' => '1 minute',
        ], new InMemoryStorage());
    }

    private function createRequest(string $code, Session $session, string $sessionId = 'the-session-id'): Request
    {
        $request = Request::create(
            '/gift-cards',
            'POST',
            ['setono_sylius_gift_card_add_gift_card_to_order' => ['giftCard' => $code]],
            [$session->getName() => $sessionId],
            [],
            ['REMOTE_ADDR' => '203.0.113.10'],
        );
        $request->setSession($session);

        return $request;
    }

    private function session(): Session
    {
        return new Session(new MockArraySessionStorage());
    }

    private function channel(): ChannelInterface
    {
        $channel = new Channel();
        $channel->setCode('WEB');

        return $channel;
    }

    /**
     * @param (callable(GiftCard):void)|null $make turns an applicable gift card into one that cannot be used
     */
    private function giftCard(?callable $make = null): GiftCardInterface
    {
        $giftCard = new GiftCard();
        $giftCard->setCode('SOMEGIFTCARD');
        $giftCard->setEnabled(true);
        $giftCard->setAmount(1000);
        $giftCard->setCurrencyCode('USD');
        $giftCard->setChannel($this->channel());

        if (null !== $make) {
            $make($giftCard);
        }

        return $giftCard;
    }

    /**
     * @return ObjectProphecy<OrderInterface>
     */
    private function order(): ObjectProphecy
    {
        $order = $this->prophesize(OrderInterface::class);
        $order->getChannel()->willReturn($this->channel());
        $order->getCurrencyCode()->willReturn('USD');
        $order->hasGiftCard(Argument::any())->willReturn(false);

        return $order;
    }
}
