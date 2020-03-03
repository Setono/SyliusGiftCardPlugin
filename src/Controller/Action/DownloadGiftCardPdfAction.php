<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action;

use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\Pdf;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardChannelConfigurationProviderInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Security\GiftCardVoter;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;
use function Safe\sprintf;

final class DownloadGiftCardPdfAction
{
    /** @var GiftCardRepositoryInterface */
    private $giftCardRepository;

    /** @var AuthorizationCheckerInterface */
    private $authChecker;

    /** @var FlashBagInterface */
    private $flashBag;

    /** @var GiftCardChannelConfigurationProviderInterface */
    private $configurationProvider;

    /** @var Environment */
    private $twig;

    /** @var Pdf */
    private $snappy;

    public function __construct(
        GiftCardRepositoryInterface $giftCardRepository,
        AuthorizationCheckerInterface $authChecker,
        FlashBagInterface $flashBag,
        GiftCardChannelConfigurationProviderInterface $configurationProvider,
        Environment $twig,
        Pdf $snappy
    ) {
        $this->giftCardRepository = $giftCardRepository;
        $this->authChecker = $authChecker;
        $this->flashBag = $flashBag;
        $this->configurationProvider = $configurationProvider;
        $this->twig = $twig;
        $this->snappy = $snappy;
    }

    public function __invoke(Request $request, int $id): Response
    {
        $giftCard = $this->giftCardRepository->find($id);
        if (!$giftCard instanceof GiftCardInterface) {
            throw new NotFoundHttpException('Gift card not found');
        }
        if (!$this->authChecker->isGranted([GiftCardVoter::READ], $giftCard)) {
            $this->flashBag->add('error', 'setono_sylius_gift_card.gift_card.read_error');

            /** @var string $redirectUrl */
            $redirectUrl = filter_var($request->headers->get('referer'), \FILTER_SANITIZE_URL);

            return new RedirectResponse($redirectUrl);
        }

        $configuration = $giftCard->getConfiguration();
        if (!$configuration instanceof GiftCardConfigurationInterface) {
            $configuration = $this->configurationProvider->getConfigurationForGiftCard($giftCard);
        }

        if (!$configuration instanceof GiftCardConfigurationInterface) {
            throw new NotFoundHttpException(sprintf('Configuration not found for gift card %d', $giftCard->getId()));
        }

        $html = $this->twig->render('@SetonoSyliusGiftCardPlugin/Shop/GiftCard/pdf.html.twig', [
            'giftCard' => $giftCard,
            'configuration' => $configuration,
        ]);

        return new PdfResponse($this->snappy->getOutputFromHtml($html), 'gift_card.pdf');
    }
}
