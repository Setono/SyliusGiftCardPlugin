<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\CustomerInterface;
use Twig\Environment;

/**
 * The customer column of the gift card grid renders an icon only link to the customer. The fixtures never
 * assign a customer to a gift card, so the browser suite only ever sees the "no customer" branch and this
 * renders the template directly instead.
 */
final class CustomerGridFieldTest extends GiftCardFunctionalTestCase
{
    private const TEMPLATE = '@SetonoSyliusGiftCardPlugin/admin/gift_card/grid/field/customer.html.twig';

    /** @test */
    public function it_names_the_icon_only_link_and_opens_it_safely(): void
    {
        $html = $this->render($this->createCustomer());

        // An icon carries no text, so without a name of its own the link reads as "link" and nothing else
        self::assertMatchesRegularExpression(
            '#<a [^>]*aria-label="Open customer customer@example\.com in a new tab"#',
            $html,
        );

        // A new tab gets a handle on this one through window.opener unless the link opts out
        self::assertMatchesRegularExpression('#<a [^>]*target="_blank"[^>]*rel="noopener"#', $html);
    }

    /** @test */
    public function it_renders_a_placeholder_without_a_customer(): void
    {
        self::assertStringContainsString('No customer', $this->render(null));
    }

    private function render(?CustomerInterface $customer): string
    {
        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');

        return $twig->render(self::TEMPLATE, ['data' => $customer]);
    }

    private function createCustomer(): CustomerInterface
    {
        $customer = new Customer();
        $customer->setEmail('customer@example.com');
        $customer->setEmailCanonical('customer@example.com');

        $this->manager->persist($customer);
        $this->manager->flush();

        return $customer;
    }
}
