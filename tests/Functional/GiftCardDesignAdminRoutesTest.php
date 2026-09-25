<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

/**
 * The admin has a designs grid, a form to create a design, one to edit it and a delete button, and the design resource
 * registers the routes for exactly those. A design has no show page: Sylius' admin ships no show template for a
 * resource to fall back on, so a show route could only ever answer with a 500
 */
final class GiftCardDesignAdminRoutesTest extends AdminFunctionalTestCase
{
    private const FORM = 'setono_sylius_gift_card_gift_card_design';

    protected function setUp(): void
    {
        parent::setUp();

        $this->getChannel();
        $this->logInAsAdministrator();
    }

    /** @test */
    public function it_registers_no_show_route_for_designs(): void
    {
        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');

        self::assertNull($router->getRouteCollection()->get('setono_sylius_gift_card_admin_gift_card_design_show'));
    }

    /**
     * Trimming /edit off the address of a design's edit page leaves the path of its delete route, which only answers
     * DELETE
     *
     * @test
     */
    public function it_answers_that_a_design_cannot_be_opened_without_its_edit_page(): void
    {
        $design = $this->persistDesign('christmas');

        $response = $this->request('GET', sprintf('/admin/gift-card-designs/%d', (int) $design->getId()));

        self::assertSame(405, $response->getStatusCode());
    }

    /**
     * Unless the routes say otherwise Sylius sends the admin to the show page of a resource that was just saved, which
     * a design does not have, so the design routes send them back to the designs grid
     *
     * @test
     */
    public function it_returns_to_the_designs_after_creating_one(): void
    {
        $response = $this->submit('POST', '/admin/gift-card-designs/new', 'christmas');

        self::assertSame(['christmas'], $this->codesListedAfter($response));
    }

    /** @test */
    public function it_returns_to_the_designs_after_updating_one(): void
    {
        $design = $this->persistDesign('christmas');

        $response = $this->submit('PUT', sprintf('/admin/gift-card-designs/%d/edit', (int) $design->getId()), 'birthday');

        self::assertSame(['birthday'], $this->codesListedAfter($response));
    }

    /**
     * Fills in the design form found at the URI and submits it with the given method
     */
    private function submit(string $method, string $uri, string $code): Response
    {
        $form = $this->request('GET', $uri);
        self::assertSame(200, $form->getStatusCode());

        return $this->request('POST', $uri, [
            '_method' => $method,
            self::FORM => [
                'code' => $code,
                'translations' => ['en_US' => ['name' => ucfirst($code)]],
                'position' => '1',
                'channels' => ['TEST_CHANNEL'],
                'enabled' => '1',
                '_token' => self::valueOf($form, sprintf('//input[@name="%s[_token]"]', self::FORM)),
            ],
        ]);
    }

    /**
     * @return list<string> the codes in the designs grid the response redirects to
     */
    private function codesListedAfter(Response $response): array
    {
        self::assertTrue($response->isRedirect(), sprintf('Expected a redirect, got a %d response', $response->getStatusCode()));
        // The index is handed the id of the saved design, which it ignores
        self::assertSame('/admin/gift-card-designs/', parse_url((string) $response->headers->get('Location'), \PHP_URL_PATH));

        $index = $this->followRedirect($response);
        self::assertSame(200, $index->getStatusCode());

        return self::textsOf($index, '//tbody[@data-test-grid-table-body]/tr/td[2]');
    }

    private function persistDesign(string $code): GiftCardDesignInterface
    {
        /** @var FactoryInterface<GiftCardDesignInterface> $factory */
        $factory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card_design');

        $design = $factory->createNew();
        $design->setCode($code);
        $design->setCurrentLocale('en_US');
        $design->setFallbackLocale('en_US');
        $design->setName(ucfirst($code));
        $design->setPosition(1);
        $design->addChannel($this->getChannel());

        $this->manager->persist($design);
        $this->manager->flush();

        return $design;
    }
}
