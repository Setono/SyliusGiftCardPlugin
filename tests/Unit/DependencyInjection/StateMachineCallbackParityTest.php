<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Argument\ArgumentsWildcard;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\MethodProphecy;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\DependencyInjection\SetonoSyliusGiftCardExtension;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Operator\OrderGiftCardOperator;
use Setono\SyliusGiftCardPlugin\Operator\OrderGiftCardOperatorInterface;
use Setono\SyliusGiftCardPlugin\Redemption\GiftCardRedemptionMethodInterface;
use Setono\SyliusGiftCardPlugin\StateMachine\GiftCardCoverageGuard;
use Setono\SyliusGiftCardPlugin\StateMachine\GiftCardCoverageGuardInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Event\Event;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;
use Webmozart\Assert\Assert;

/**
 * The plugin hooks the order state machines twice, as winzou callbacks prepended by the extension and as Symfony
 * Workflow subscribers, and only one of the two sets runs depending on the adapter the application picked. So the
 * two sets have to describe the same hooks: the same transition, the same collaborator method, and priorities that
 * place them identically among Sylius' own callbacks. winzou runs callbacks in ascending priority order while
 * Symfony's dispatcher runs listeners in descending order, so a hook at winzou priority p sits at workflow priority -p
 */
final class StateMachineCallbackParityTest extends TestCase
{
    use ProphecyTrait;

    /**
     * The service a subscriber's collaborator resolves to, as the winzou callbacks name it
     */
    private const COLLABORATOR_SERVICES = [
        OrderGiftCardOperatorInterface::class => '@' . OrderGiftCardOperator::class,
        GiftCardRedemptionMethodInterface::class => '@setono_sylius_gift_card.redemption_method',
        GiftCardCoverageGuardInterface::class => '@' . GiftCardCoverageGuard::class,
    ];

    /**
     * A winzou "guard" callback corresponds to Symfony Workflow's "guard" event, a "before" callback to "transition"
     * and an "after" callback to "completed"
     */
    private const WORKFLOW_EVENT_BY_POSITION = [
        'guard' => 'guard',
        'before' => 'transition',
        'after' => 'completed',
    ];

    /** @test */
    public function it_gives_every_winzou_callback_an_explicit_priority(): void
    {
        foreach ($this->winzouCallbacks() as $name => $callback) {
            self::assertIsInt($callback['priority'], sprintf(
                '%s declares no priority, so winzou would run it last, after everything Sylius does. State where it belongs',
                $name,
            ));
        }
    }

    /** @test */
    public function it_registers_the_same_hooks_with_mirrored_priorities_on_both_adapters(): void
    {
        $winzou = [];
        foreach ($this->winzouCallbacks() as $callback) {
            $winzou[$callback['hook']] = $callback['priority'];
        }
        ksort($winzou);

        $workflow = $this->workflowHooks();

        self::assertNotEmpty($winzou);
        self::assertSame(
            $winzou,
            $workflow,
            'The winzou callbacks in SetonoSyliusGiftCardExtension::prepend() and the subscribers in EventSubscriber/Workflow have drifted apart',
        );
    }

    /**
     * The callbacks prepend() registers, one entry per transition a callback listens to
     *
     * @return array<string, array{hook: string, priority: int|null}>
     */
    private function winzouCallbacks(): array
    {
        $container = new ContainerBuilder();
        (new SetonoSyliusGiftCardExtension())->prepend($container);

        $callbacks = [];

        foreach ($container->getExtensionConfig('winzou_state_machine') as $config) {
            foreach ($config as $graph => $graphConfig) {
                self::assertIsArray($graphConfig);
                $positions = $graphConfig['callbacks'] ?? null;
                self::assertIsArray($positions);

                foreach ($positions as $position => $positionCallbacks) {
                    self::assertIsString($position);
                    self::assertArrayHasKey($position, self::WORKFLOW_EVENT_BY_POSITION);
                    self::assertIsArray($positionCallbacks);

                    foreach ($positionCallbacks as $name => $callback) {
                        self::assertIsString($name);
                        self::assertIsArray($callback);

                        $transitions = $callback['on'] ?? null;
                        self::assertIsArray($transitions);

                        $do = $callback['do'] ?? null;
                        self::assertIsArray($do);
                        [$service, $method] = $do;
                        self::assertIsString($service);
                        self::assertIsString($method);

                        $priority = $callback['priority'] ?? null;
                        if (null !== $priority) {
                            self::assertIsInt($priority);
                        }

                        foreach ($transitions as $transition) {
                            self::assertIsString($transition);

                            $callbacks[sprintf('%s on %s', $name, $transition)] = [
                                'hook' => self::hook($position, $graph, $transition, $service, $method),
                                'priority' => $priority,
                            ];
                        }
                    }
                }
            }
        }

        return $callbacks;
    }

    /**
     * The hooks the workflow subscribers register, each with the winzou priority its own priority corresponds to
     *
     * @return array<string, int>
     */
    private function workflowHooks(): array
    {
        $hooks = [];

        foreach (self::subscriberClasses() as $class) {
            foreach ($class::getSubscribedEvents() as $event => $listeners) {
                // Symfony Workflow names its events workflow.<graph>.<kind>.<transition>
                $parts = explode('.', $event, 4);
                self::assertCount(4, $parts, sprintf('%s subscribes to %s, which is not a Symfony Workflow transition event', $class, $event));
                [$prefix, $graph, $kind, $transition] = $parts;
                self::assertSame('workflow', $prefix);

                $position = array_search($kind, self::WORKFLOW_EVENT_BY_POSITION, true);
                self::assertIsString($position, sprintf('%s subscribes to %s, which has no winzou callback position', $class, $event));

                foreach (self::listeners($listeners) as [$handler, $priority]) {
                    [$collaboratorInterface, $method] = $this->forwardedCall($class, $handler, $kind);

                    $service = self::COLLABORATOR_SERVICES[$collaboratorInterface] ?? null;
                    self::assertIsString($service, sprintf(
                        '%s is built with a %s, which none of the winzou callbacks name a service for',
                        $class,
                        $collaboratorInterface,
                    ));

                    $hooks[self::hook($position, $graph, $transition, $service, $method)] = -$priority;
                }
            }
        }

        ksort($hooks);

        return $hooks;
    }

    /**
     * Every subscriber in src/EventSubscriber/Workflow, found on disk so a new one cannot be added without its
     * winzou counterpart
     *
     * @return list<class-string<EventSubscriberInterface>>
     */
    private static function subscriberClasses(): array
    {
        $files = glob(dirname(__DIR__, 3) . '/src/EventSubscriber/Workflow/*.php');
        self::assertIsArray($files);
        self::assertNotEmpty($files);

        $classes = [];
        foreach ($files as $file) {
            $class = 'Setono\\SyliusGiftCardPlugin\\EventSubscriber\\Workflow\\' . basename($file, '.php');
            if (!is_subclass_of($class, EventSubscriberInterface::class)) {
                self::fail(sprintf('%s is not an event subscriber', $class));
            }

            $classes[] = $class;
        }

        return $classes;
    }

    /**
     * getSubscribedEvents() lets a subscriber say 'method', ['method', priority] or [['method', priority], ...];
     * this brings any of them to the last form with the default priority (0) filled in
     *
     * @return list<array{string, int}>
     */
    private static function listeners(mixed $listeners): array
    {
        if (is_string($listeners)) {
            return [[$listeners, 0]];
        }

        self::assertIsArray($listeners);
        if (is_string($listeners[0] ?? null)) {
            $listeners = [$listeners];
        }

        $normalized = [];
        foreach ($listeners as $listener) {
            self::assertIsArray($listener);

            $method = $listener[0] ?? null;
            self::assertIsString($method);

            $priority = $listener[1] ?? 0;
            self::assertIsInt($priority);

            $normalized[] = [$method, $priority];
        }

        return $normalized;
    }

    /**
     * Builds the subscriber around a dummy of its collaborator, hands it the event its subscription is for and
     * reports which collaborator method the order was forwarded to
     *
     * @param class-string<EventSubscriberInterface> $class
     * @param string $kind the Symfony Workflow event kind the subscriber listens to (guard, transition, completed)
     *
     * @return array{class-string, string} the collaborator interface and the method called on it
     */
    private function forwardedCall(string $class, string $handler, string $kind): array
    {
        $constructor = (new \ReflectionClass($class))->getConstructor();
        self::assertNotNull($constructor);

        $parameters = $constructor->getParameters();
        self::assertCount(1, $parameters, sprintf('%s should take its collaborator as its only constructor argument', $class));

        $type = $parameters[0]->getType();
        self::assertInstanceOf(\ReflectionNamedType::class, $type);
        $collaboratorInterface = $type->getName();
        if (!interface_exists($collaboratorInterface)) {
            self::fail(sprintf('%s should be built with an interface, not %s', $class, $collaboratorInterface));
        }

        $collaborator = $this->prophesize($collaboratorInterface);
        self::stubEveryMethod($collaborator, $collaboratorInterface);

        $subscriber = new $class($collaborator->reveal());
        $listener = [$subscriber, $handler];
        self::assertIsCallable($listener);

        $listener(self::event($kind, $this->prophesize(OrderInterface::class)->reveal()));

        $called = [];
        foreach ((new \ReflectionClass($collaboratorInterface))->getMethods() as $method) {
            if ([] !== $collaborator->findProphecyMethodCalls($method->getName(), new ArgumentsWildcard([Argument::cetera()]))) {
                $called[] = $method->getName();
            }
        }
        self::assertCount(1, $called, sprintf('%s::%s should call exactly one method on its collaborator', $class, $handler));

        return [$collaboratorInterface, $called[0]];
    }

    /**
     * Makes the dummy accept any call. A dummy answers null to everything, which PHP refuses for a method declared
     * to return a scalar or an array (a guard's collaborator answers with a bool), and once one method is stubbed
     * Prophecy rejects calls to any other. So every method is stubbed, and those answering with a scalar or an array
     * answer something PHP accepts and that lets the transition through, so the subscriber has nothing to do but
     * call its one collaborator method
     *
     * @param ObjectProphecy<object> $collaborator
     * @param class-string $collaboratorInterface
     */
    private static function stubEveryMethod(ObjectProphecy $collaborator, string $collaboratorInterface): void
    {
        foreach ((new \ReflectionClass($collaboratorInterface))->getMethods() as $method) {
            $prophecy = $collaborator->__call($method->getName(), [Argument::cetera()]);
            Assert::isInstanceOf($prophecy, MethodProphecy::class);

            $type = $method->getReturnType();
            if (!$type instanceof \ReflectionNamedType || $type->allowsNull()) {
                continue;
            }

            $default = match ($type->getName()) {
                'bool' => true,
                'int' => 0,
                'float' => 0.0,
                'string' => '',
                'array' => [],
                default => null,
            };
            if (null !== $default) {
                $prophecy->willReturn($default);
            }
        }
    }

    private static function event(string $kind, object $subject): Event
    {
        $marking = new Marking();
        $transition = new Transition('t', 'from', 'to');

        return match ($kind) {
            'guard' => new GuardEvent($subject, $marking, $transition),
            'transition' => new TransitionEvent($subject, $marking, $transition),
            'completed' => new CompletedEvent($subject, $marking, $transition),
            default => self::fail(sprintf('no Symfony Workflow event is built for the "%s" kind', $kind)),
        };
    }

    private static function hook(string $position, string $graph, string $transition, string $service, string $method): string
    {
        return sprintf('%s %s.%s -> %s::%s', $position, $graph, $transition, $service, $method);
    }
}
