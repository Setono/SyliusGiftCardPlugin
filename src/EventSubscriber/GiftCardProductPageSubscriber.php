<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EventSubscriber;

use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfigurationFactoryInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Resource\Metadata\RegistryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Gives a gift card product a shop page of its own instead of Sylius' product page.
 *
 * Sylius' resource controller dispatches sylius.product.show once it has found the product, and answers the request
 * with the response a listener put on the event instead of rendering its own template. This renders the plugin's
 * template with the variables the controller would have passed to Sylius' (configuration, metadata, resource and
 * product), so the product is looked up once, by Sylius.
 *
 * The admin's product page and the shop's partial product route run the same controller action and fire the same
 * event, so only the shop's product page is answered here
 */
final class GiftCardProductPageSubscriber implements EventSubscriberInterface
{
    public const ROUTE = 'sylius_shop_product_show';

    public const TEMPLATE = '@SetonoSyliusGiftCardPlugin/shop/product/show.html.twig';

    /** The resource the event and the controller belong to, by its alias */
    private const RESOURCE = 'sylius.product';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly RequestConfigurationFactoryInterface $requestConfigurationFactory,
        private readonly RegistryInterface $resourceRegistry,
        private readonly Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Sylius registers nothing on this event. Low, so a listener of the application's that answers the request
            // itself (a redirect, say) gets to do so first, and the page is only rendered when none has
            'sylius.product.show' => ['renderGiftCardProductPage', -100],
        ];
    }

    public function renderGiftCardProductPage(ResourceControllerEvent $event): void
    {
        if (null !== $event->getResponse()) {
            return;
        }

        $product = $event->getSubject();
        if (!$product instanceof ProductInterface || !$product->isGiftCard()) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (null === $request || self::ROUTE !== $request->attributes->get('_route')) {
            return;
        }

        $metadata = $this->resourceRegistry->get(self::RESOURCE);
        $configuration = $this->requestConfigurationFactory->create($metadata, $request);

        // The controller only renders a template for an HTML request, and leaves the others to its view handler
        if (!$configuration->isHtmlRequest()) {
            return;
        }

        $event->setResponse(new Response($this->twig->render(self::TEMPLATE, [
            'configuration' => $configuration,
            'metadata' => $metadata,
            'resource' => $product,
            'product' => $product,
        ])));
    }
}
