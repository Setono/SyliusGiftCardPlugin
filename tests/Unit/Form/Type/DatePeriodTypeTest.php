<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Form\Type;

use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Form\Type\DatePeriodType;
use Setono\SyliusGiftCardPlugin\Provider\DatePeriodUnitProviderInterface;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;

final class DatePeriodTypeTest extends TypeTestCase
{
    use ProphecyTrait;

    private ObjectProphecy $datePeriodUnitProvider;

    protected function setUp(): void
    {
        $this->datePeriodUnitProvider = $this->prophesize(DatePeriodUnitProviderInterface::class);

        parent::setUp();
    }

    /**
     * @test
     */
    public function it_submits_valid_data(): void
    {
        $formData = [
            'value' => 5,
            'unit' => 'day',
        ];

        $this->datePeriodUnitProvider->getPeriodUnits()->willReturn([
            'hour',
            'day',
        ]);
        $form = $this->factory->create(DatePeriodType::class);

        $expected = [
            'value' => 5,
            'unit' => 'day',
        ];

        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertEquals($expected, $formData);
    }

    protected function getExtensions(): array
    {
        $type = new DatePeriodType($this->datePeriodUnitProvider->reveal());

        return [
            new PreloadedExtension([$type], []),
        ];
    }
}
