<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Core\Model\AdminUser;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\FrameworkBundle\Test\TestBrowserToken;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Sends requests through the booted test kernel, optionally as a signed in administrator.
 *
 * The plugin does not depend on symfony/browser-kit, so instead of a KernelBrowser the requests are handed to the
 * kernel directly and the cookies it sets are carried to the next one. That is all an admin page needs: the admin
 * firewall reads its token from the session, and the CSRF tokens a page renders are kept in that same session.
 *
 * The kernel resets its services between two requests like it does under a worker runtime, which clears the entity
 * manager: entities loaded before a request are detached after it, so tests look them up again by id.
 */
abstract class AdminFunctionalTestCase extends GiftCardFunctionalTestCase
{
    /** @var array<string, string> */
    private array $cookies = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->cookies = [];
    }

    /**
     * Signs in the way KernelBrowser::loginUser() does: the token is stored in a session of the admin firewall's
     * context, and the session cookie is sent with every following request
     */
    protected function logInAsAdministrator(): void
    {
        $administrator = new AdminUser();
        $administrator->setUsername('administrator');
        $administrator->setEmail('administrator@example.com');
        $administrator->setPassword('irrelevant, the session carries the token');
        $administrator->setLocaleCode('en_US');
        $administrator->setEnabled(true);

        $this->manager->persist($administrator);
        $this->manager->flush();

        // Loaded the way the admin firewall loads its users
        /** @var UserProviderInterface<UserInterface> $userProvider */
        $userProvider = self::getContainer()->get('sylius.admin_user_provider.email_or_name_based');
        $user = $userProvider->loadUserByIdentifier('administrator');

        /** @var SessionFactoryInterface $sessionFactory */
        $sessionFactory = self::getContainer()->get('session.factory');
        $session = $sessionFactory->createSession();
        // The token carries the role the admin firewall's access control asks for
        $session->set('_security_admin', serialize(new TestBrowserToken([AdminUserInterface::DEFAULT_ADMIN_ROLE], $user, 'admin')));
        $session->save();

        $this->cookies[$session->getName()] = $session->getId();
    }

    /**
     * Forgets the session, so the next request arrives like one from a visitor who never signed in
     */
    protected function logOut(): void
    {
        $this->cookies = [];
    }

    /**
     * A gift card in the test channel's base currency that still holds the amount it was issued with
     */
    protected function persistGiftCard(string $code, int $amount, bool $enabled = true): GiftCardInterface
    {
        /** @var GiftCardFactoryInterface $factory */
        $factory = self::getContainer()->get(GiftCardFactoryInterface::class);

        $giftCard = $factory->createForChannel($this->getChannel());
        $giftCard->setCode($code);
        $giftCard->setInitialAmount($amount);
        $giftCard->setAmount($amount);
        $giftCard->setEnabled($enabled);

        $this->manager->persist($giftCard);
        $this->manager->flush();

        return $giftCard;
    }

    /**
     * Reads the gift card back from the database rather than from what the entity manager still holds
     */
    protected function reloadGiftCard(GiftCardInterface $giftCard): GiftCardInterface
    {
        $id = $giftCard->getId();
        $this->manager->clear();

        /** @var GiftCardRepositoryInterface $repository */
        $repository = self::getContainer()->get('setono_sylius_gift_card.repository.gift_card');
        $reloaded = $repository->find($id);
        self::assertInstanceOf(GiftCardInterface::class, $reloaded);

        return $reloaded;
    }

    /**
     * @param array<string, mixed> $parameters the query string of a GET, the form body of anything else
     */
    protected function request(string $method, string $uri, array $parameters = []): Response
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(KernelInterface::class, $kernel);

        $response = $kernel->handle(Request::create($uri, $method, $parameters, $this->cookies));

        foreach ($response->headers->getCookies() as $cookie) {
            $this->cookies[$cookie->getName()] = (string) $cookie->getValue();
        }

        return $response;
    }

    protected function followRedirect(Response $response): Response
    {
        self::assertTrue($response->isRedirect(), sprintf('Expected a redirect, got a %d response', $response->getStatusCode()));

        return $this->request('GET', (string) $response->headers->get('Location'));
    }

    /**
     * @return string the value attribute of the first element matching the XPath expression
     */
    protected static function valueOf(Response $response, string $expression): string
    {
        $nodes = self::query($response, $expression);

        $node = $nodes->item(0);
        self::assertInstanceOf(\DOMElement::class, $node, sprintf('Nothing on the page matches %s', $expression));

        return $node->getAttribute('value');
    }

    /**
     * @return list<string> the text of every element matching the XPath expression, with its whitespace collapsed
     */
    protected static function textsOf(Response $response, string $expression): array
    {
        $texts = [];
        foreach (self::query($response, $expression) as $node) {
            $texts[] = trim((string) preg_replace('/\s+/u', ' ', (string) $node->nodeValue));
        }

        return $texts;
    }

    /**
     * @return \DOMNodeList<\DOMNameSpaceNode|\DOMNode>
     */
    private static function query(Response $response, string $expression): \DOMNodeList
    {
        $document = new \DOMDocument();

        // libxml predates HTML5 and complains about its elements, which is noise here. It also reads HTML as
        // ISO-8859-1 unless told otherwise, which would garble a currency sign
        $useInternalErrors = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">' . (string) $response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($useInternalErrors);

        $nodes = (new \DOMXPath($document))->query($expression);
        self::assertInstanceOf(\DOMNodeList::class, $nodes, sprintf('%s is not a valid XPath expression', $expression));

        return $nodes;
    }
}
