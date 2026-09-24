<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Controller\Action\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Controller\Action\Admin\AdjustGiftCardBalanceAction;
use Setono\SyliusGiftCardPlugin\Controller\Action\Admin\AdjustGiftCardBalanceCommand;
use Setono\SyliusGiftCardPlugin\Form\Type\AdjustGiftCardBalanceType;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Operator\GiftCardBalanceOperatorInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;
use Symfony\Component\Validator\Validation;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * The action hands the adjustment to the balance operator, the only thing allowed to move a balance, and owns the
 * flush that makes it stick. It runs on a real form built from AdjustGiftCardBalanceType so the submitted major units
 * reach the operator the way they do in the admin
 */
final class AdjustGiftCardBalanceActionTest extends TestCase
{
    use ProphecyTrait;

    private const ID = 42;

    /** @var ObjectProphecy<GiftCardBalanceOperatorInterface> */
    private ObjectProphecy $balanceOperator;

    /** @var ObjectProphecy<EntityManagerInterface> */
    private ObjectProphecy $manager;

    protected function setUp(): void
    {
        $this->balanceOperator = $this->prophesize(GiftCardBalanceOperatorInterface::class);
        $this->manager = $this->prophesize(EntityManagerInterface::class);
    }

    /** @test */
    public function it_answers_not_found_for_an_unknown_gift_card(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->action(null)(Request::create('/admin/gift-cards/42/adjust-balance'), self::ID);
    }

    /** @test */
    public function it_shows_the_form_in_the_currency_of_the_gift_card(): void
    {
        $this->balanceOperator->adjust(Argument::cetera())->shouldNotBeCalled();
        $this->manager->flush()->shouldNotBeCalled();

        $response = $this->action($this->giftCard())(Request::create('/admin/gift-cards/42/adjust-balance'), self::ID);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ADJUSTME in DKK', $response->getContent());
    }

    /** @test */
    public function it_adjusts_the_balance_and_redirects_back_to_the_gift_card(): void
    {
        $giftCard = $this->giftCard();

        $this->balanceOperator->adjust($giftCard, -1250, 'Returned goods')->shouldBeCalledOnce();
        $this->manager->flush()->shouldBeCalledOnce();

        $request = $this->submission('-12.50', 'Returned goods');
        $response = $this->action($giftCard)($request, self::ID);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/gift-cards/42/edit', $response->getTargetUrl());

        $session = $request->getSession();
        self::assertInstanceOf(Session::class, $session);
        self::assertSame(['setono_sylius_gift_card.gift_card.balance_adjusted'], $session->getFlashBag()->get('success'));
    }

    /** @test */
    public function it_shows_the_form_again_without_adjusting_anything_when_the_submission_is_invalid(): void
    {
        $this->balanceOperator->adjust(Argument::cetera())->shouldNotBeCalled();
        $this->manager->flush()->shouldNotBeCalled();

        $response = $this->action($this->giftCard())($this->submission('10', ''), self::ID);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ADJUSTME in DKK', $response->getContent());
    }

    private function action(?GiftCardInterface $giftCard): AdjustGiftCardBalanceAction
    {
        $giftCardRepository = $this->prophesize(GiftCardRepositoryInterface::class);
        $giftCardRepository->find(self::ID)->willReturn($giftCard);

        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(Argument::type('string'))->willReturn($this->manager->reveal());

        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator
            ->generate('setono_sylius_gift_card_admin_gift_card_update', ['id' => self::ID])
            ->willReturn('/admin/gift-cards/42/edit')
        ;

        // Only an invalid reason is needed to prove an invalid submission is not applied, and building the validator
        // from the XML mapping would drag in every constraint on the command
        $validator = Validation::createValidatorBuilder()
            ->addLoader(new class() implements LoaderInterface {
                public function loadClassMetadata(ClassMetadata $metadata): bool
                {
                    if (AdjustGiftCardBalanceCommand::class !== $metadata->getClassName()) {
                        return false;
                    }

                    $metadata->addPropertyConstraint('reason', new NotBlank(groups: ['setono_sylius_gift_card']));

                    return true;
                }
            })
            ->getValidator()
        ;

        $formFactory = Forms::createFormFactoryBuilder()
            ->addType(new AdjustGiftCardBalanceType())
            ->addExtension(new HttpFoundationExtension())
            ->addExtension(new ValidatorExtension($validator))
            ->getFormFactory()
        ;

        $twig = new Environment(new ArrayLoader([
            '@SetonoSyliusGiftCardPlugin/admin/gift_card/adjust_balance.html.twig' => '{{ giftCard.code }} in {{ form.amount.vars.currency }}',
        ]));

        return new AdjustGiftCardBalanceAction(
            $giftCardRepository->reveal(),
            $this->balanceOperator->reveal(),
            $formFactory,
            $twig,
            $urlGenerator->reveal(),
            $managerRegistry->reveal(),
        );
    }

    private function giftCard(): GiftCardInterface
    {
        $giftCard = new GiftCard();
        $giftCard->setCode('ADJUSTME');
        $giftCard->setCurrencyCode('DKK');
        $giftCard->setAmount(5000);

        return $giftCard;
    }

    private function submission(string $amount, string $reason): Request
    {
        $request = Request::create('/admin/gift-cards/42/adjust-balance', 'POST', [
            'setono_sylius_gift_card_adjust_balance' => ['amount' => $amount, 'reason' => $reason],
        ]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}
