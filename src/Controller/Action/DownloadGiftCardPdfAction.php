<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action;

use const FILTER_SANITIZE_URL;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardPdfGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardChannelConfigurationProviderInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Security\GiftCardVoter;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class DownloadGiftCardPdfAction
{
    private GiftCardRepositoryInterface $giftCardRepository;

    private AuthorizationCheckerInterface $authChecker;

    private FlashBagInterface $flashBag;

    private GiftCardChannelConfigurationProviderInterface $configurationProvider;

    private GiftCardPdfGeneratorInterface $giftCardPdfGenerator;

    private UrlGeneratorInterface $urlGenerator;

    public function __construct(
        GiftCardRepositoryInterface $giftCardRepository,
        AuthorizationCheckerInterface $authChecker,
        FlashBagInterface $flashBag,
        GiftCardChannelConfigurationProviderInterface $configurationProvider,
        GiftCardPdfGeneratorInterface $giftCardPdfGenerator,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->giftCardRepository = $giftCardRepository;
        $this->authChecker = $authChecker;
        $this->flashBag = $flashBag;
        $this->configurationProvider = $configurationProvider;
        $this->giftCardPdfGenerator = $giftCardPdfGenerator;
        $this->urlGenerator = $urlGenerator;
    }

    public function __invoke(Request $request, int $id): Response
    {
        $redirectUrl = $this->getRedirectUrl($request);

        $giftCard = $this->giftCardRepository->find($id);

        if (!$giftCard instanceof GiftCardInterface) {
            return $this->sendErrorResponse($redirectUrl, 'Gift card not found');
        }

        if (!$this->authChecker->isGranted(GiftCardVoter::READ, $giftCard)) {
            return $this->sendErrorResponse($redirectUrl, 'setono_sylius_gift_card.gift_card.read_error');
        }

        $configuration = $this->configurationProvider->getConfigurationForGiftCard($giftCard);
        if (!$configuration instanceof GiftCardConfigurationInterface) {
            return $this->sendErrorResponse(
                $redirectUrl,
                'Configuration not found for this gift card. Create one by going to the gift card configuration.'
            );
        }

        return $this->giftCardPdfGenerator->generatePdfResponse($giftCard, $configuration);
    }

    private function sendErrorResponse(string $redirectUrl, string $message): RedirectResponse
    {
        $this->flashBag->add('error', $message);

        return new RedirectResponse($redirectUrl);
    }

    private function getRedirectUrl(Request $request): string
    {
        $referrer = $request->headers->get('referer');
        if (is_string($referrer)) {
            /** @var string $referrer */
            $referrer = filter_var($referrer, FILTER_SANITIZE_URL);

            return $referrer;
        }

        return $this->urlGenerator->generate('setono_sylius_gift_card_admin_gift_card_index');
    }
}
