<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\EventSubscriber\GiftCardProductPageSubscriber;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Bundle\ResourceBundle\Controller\Parameters;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfigurationFactoryInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\Product as SyliusProduct;
use Sylius\Resource\Metadata\Metadata;
use Sylius\Resource\Metadata\MetadataInterface;
use Sylius\Resource\Metadata\Registry;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class GiftCardProductPageSubscriberTest extends TestCase
{
    use ProphecyTrait;

    private const SHOP_ROUTE = 'sylius_shop_product_show';

    private RequestStack $requestStack;

    /** @var ObjectProphecy<RequestConfigurationFactoryInterface> */
    private ObjectProphecy $requestConfigurationFactory;

    private MetadataInterface $metadata;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        $this->requestConfigurationFactory = $this->prophesize(RequestConfigurationFactoryInterface::class);
        // What Sylius' factory makes of a route without _sylius parameters
        $this->requestConfigurationFactory->create(Argument::type(MetadataInterface::class), Argument::type(Request::class))->will(
            static function (array $arguments): RequestConfiguration {
                [$metadata, $request] = $arguments;
                self::assertInstanceOf(MetadataInterface::class, $metadata);
                self::assertInstanceOf(Request::class, $request);

                return new RequestConfiguration($metadata, $request, new Parameters());
            },
        );
        $this->metadata = Metadata::fromAliasAndConfiguration('sylius.product', [
            'driver' => 'doctrine/orm',
            'classes' => ['model' => Product::class],
        ]);
    }

    /**
     * Sylius registers nothing on the event, and an application's listener that answers the request itself goes first
     *
     * @test
     */
    public function it_renders_the_page_once_the_applications_listeners_have_had_their_say(): void
    {
        self::assertSame(
            ['sylius.product.show' => ['renderGiftCardProductPage', -100]],
            GiftCardProductPageSubscriber::getSubscribedEvents(),
        );
    }

    /**
     * The template gets the variables Sylius' controller passes to its own, without a second lookup of the product
     *
     * @test
     */
    public function it_answers_the_shops_product_page_of_a_gift_card_product_with_the_plugins_template(): void
    {
        $product = self::giftCardProduct();
        $request = $this->pushRequest(self::SHOP_ROUTE);
        $event = new ResourceControllerEvent($product);

        $this->subscriber()->renderGiftCardProductPage($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('gift_card|gift_card|sylius.product|' . self::SHOP_ROUTE, $response->getContent());
        $this->requestConfigurationFactory->create($this->metadata, $request)->shouldHaveBeenCalledOnce();
    }

    /** @test */
    public function it_leaves_a_product_that_is_not_a_gift_card_to_sylius(): void
    {
        $product = new Product();
        $product->setCode('mug');
        $this->pushRequest(self::SHOP_ROUTE);
        $event = new ResourceControllerEvent($product);

        $this->subscriber()->renderGiftCardProductPage($event);

        self::assertNull($event->getResponse());
    }

    /**
     * A product model the application has not given the plugin's interface cannot be a gift card
     *
     * @test
     */
    public function it_leaves_a_product_without_the_gift_card_flag_to_sylius(): void
    {
        $this->pushRequest(self::SHOP_ROUTE);
        $event = new ResourceControllerEvent(new SyliusProduct());

        $this->subscriber()->renderGiftCardProductPage($event);

        self::assertNull($event->getResponse());
    }

    /**
     * The admin's product page and the shop's partial product route run the same controller action, and fire the same
     * event, as does a product page of the application's own
     *
     * @test
     *
     * @dataProvider otherRoutes
     */
    public function it_leaves_the_other_routes_showing_a_product_to_sylius(?string $route): void
    {
        $this->pushRequest($route);
        $event = new ResourceControllerEvent(self::giftCardProduct());

        $this->subscriber()->renderGiftCardProductPage($event);

        self::assertNull($event->getResponse());
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function otherRoutes(): iterable
    {
        yield 'admin product page' => ['sylius_admin_product_show'];
        yield 'admin partial product route' => ['sylius_admin_partial_product_show'];
        yield 'shop partial product route' => ['sylius_shop_partial_product_show_by_slug'];
        yield 'no route' => [null];
    }

    /** @test */
    public function it_does_nothing_outside_a_request(): void
    {
        $event = new ResourceControllerEvent(self::giftCardProduct());

        $this->subscriber()->renderGiftCardProductPage($event);

        self::assertNull($event->getResponse());
    }

    /**
     * Sylius' controller only renders a template for an HTML request
     *
     * @test
     */
    public function it_leaves_a_request_for_another_format_to_sylius(): void
    {
        $this->pushRequest(self::SHOP_ROUTE)->setRequestFormat('json');
        $event = new ResourceControllerEvent(self::giftCardProduct());

        $this->subscriber()->renderGiftCardProductPage($event);

        self::assertNull($event->getResponse());
    }

    /** @test */
    public function it_keeps_the_response_another_listener_answered_with(): void
    {
        $this->pushRequest(self::SHOP_ROUTE);
        $event = new ResourceControllerEvent(self::giftCardProduct());
        $redirect = new RedirectResponse('/somewhere-else');
        $event->setResponse($redirect);

        $this->subscriber()->renderGiftCardProductPage($event);

        self::assertSame($redirect, $event->getResponse());
    }

    private function subscriber(): GiftCardProductPageSubscriber
    {
        $registry = new Registry();
        $registry->add($this->metadata);

        return new GiftCardProductPageSubscriber(
            $this->requestStack,
            $this->requestConfigurationFactory->reveal(),
            $registry,
            // Prints what the page is given, so the test can tell the variables apart
            new Environment(new ArrayLoader([
                GiftCardProductPageSubscriber::TEMPLATE => '{{ product.code }}|{{ resource.code }}|{{ metadata.alias }}|{{ configuration.request.attributes.get("_route") }}',
            ]), ['strict_variables' => true]),
        );
    }

    private function pushRequest(?string $route): Request
    {
        $request = Request::create('/en_US/products/gift-card');
        if (null !== $route) {
            $request->attributes->set('_route', $route);
        }

        $this->requestStack->push($request);

        return $request;
    }

    private static function giftCardProduct(): Product
    {
        $product = new Product();
        $product->setCode('gift_card');
        $product->setGiftCard(true);

        return $product;
    }
}
