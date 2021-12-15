<?php

declare(strict_types=1);

/*
 * This file is part of the plausibleio extension for TYPO3
 * - (c) 2021 waldhacker UG (haftungsbeschränkt)
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Waldhacker\Plausibleio\Tests\Unit\Dashboard\DataProvider;

use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Waldhacker\Plausibleio\Dashboard\DataProvider\DeviceDataProvider;
use Waldhacker\Plausibleio\Services\PlausibleService;

class DeviceDataProviderTest extends UnitTestCase
{
    use ProphecyTrait;

    private ObjectProphecy $languageServiceProphecy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->languageServiceProphecy = $this->prophesize(LanguageService::class);
        $GLOBALS['LANG'] = $this->languageServiceProphecy->reveal();
    }

    public function getBrowserDataReturnsProperValuesDataProvider(): \Generator
    {
        yield 'all items are transformed' => [
            'plausibleSiteId' => 'waldhacker.dev',
            'timeFrame' => '7d',
            'filters' => [],
            'endpointData' => [
                ['browser' => 'Firefox', 'visitors' => 12],
                ['browser' => 'Chrome', 'visitors' => 8],
            ],
            'expected' => [
                'data' => [
                    ['browser' => 'Firefox', 'visitors' => 12, 'percentage' => 60.0],
                    ['browser' => 'Chrome', 'visitors' => 8, 'percentage' => 40.0],
                ],
                'columns' => [
                    [
                        'name' => 'browser',
                        'label' => 'Browser',
                        'filter' => [
                            'name' => 'visit:browser',
                            'label' => 'Browser is',
                        ],
                    ],
                    [
                        'name' => 'visitors',
                        'label' => 'Visitors',
                    ],
                ],
            ],
        ];

        yield 'all items are transformed with filter' => [
            'plausibleSiteId' => 'waldhacker.dev',
            'timeFrame' => '7d',
            'filters' => [
                ['name' => 'visit:browser==firefox'],
            ],
            'endpointData' => [
                ['browser_version' => '48.0', 'visitors' => 12],
                ['browser_version' => '46.0', 'visitors' => 8],
            ],
            'expected' => [
                'data' => [
                    ['browser_version' => '48.0', 'visitors' => 12, 'percentage' => 60.0],
                    ['browser_version' => '46.0', 'visitors' => 8, 'percentage' => 40.0],
                ],
                'columns' => [
                    [
                        'name' => 'browser_version',
                        'label' => '${browser} version',
                        'filter' => [
                            'name' => 'visit:browser_version',
                            'label' => '${browser} version is',
                        ],
                    ],
                    [
                        'name' => 'visitors',
                        'label' => 'Visitors',
                    ],
                ],
            ],
        ];

        yield 'items without browser are ignored' => [
            'plausibleSiteId' => 'waldhacker.dev',
            'timeFrame' => '7d',
            'filters' => [],
            'endpointData' => [
                ['browser' => 'Firefox', 'visitors' => 8],
                ['browser' => '', 'visitors' => 12],
                ['visitors' => 4],
            ],
            'expected' => [
                'data' => [
                    ['browser' => 'Firefox', 'visitors' => 8, 'percentage' => 40.0],
                    ['browser' => '', 'visitors' => 12, 'percentage' => 60.0],
                ],
                'columns' => [
                    [
                        'name' => 'browser',
                        'label' => 'Browser',
                        'filter' => [
                            'name' => 'visit:browser',
                            'label' => 'Browser is',
                        ],
                    ],
                    [
                        'name' => 'visitors',
                        'label' => 'Visitors',
                    ],
                ],
            ],
        ];

        yield 'items without visitors are ignored' => [
            'plausibleSiteId' => 'waldhacker.dev',
            'timeFrame' => '7d',
            'filters' => [],
            'endpointData' => [
                ['browser' => 'Firefox', 'visitors' => 99],
                ['browser' => 'Chrome', 'visitors' => null],
                ['browser' => 'Chrome'],
            ],
            'expected' => [
                'data' => [
                    ['browser' => 'Firefox', 'visitors' => 99, 'percentage' => 100],
                ],
                'columns' => [
                    [
                        'name' => 'browser',
                        'label' => 'Browser',
                        'filter' => [
                            'name' => 'visit:browser',
                            'label' => 'Browser is',
                        ],
                    ],
                    [
                        'name' => 'visitors',
                        'label' => 'Visitors',
                    ],
                ],
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getBrowserDataReturnsProperValuesDataProvider
     * @covers \Waldhacker\Plausibleio\Dashboard\DataProvider\DeviceDataProvider::__construct
     * @covers \Waldhacker\Plausibleio\Dashboard\DataProvider\DeviceDataProvider::getBrowserData
     * @covers \Waldhacker\Plausibleio\Dashboard\DataProvider\DeviceDataProvider::getData
     * @covers \Waldhacker\Plausibleio\Dashboard\DataProvider\DeviceDataProvider::calcPercentage
     * @covers \Waldhacker\Plausibleio\Dashboard\DataProvider\DeviceDataProvider::getLanguageService
     */
    public function getBrowserDataReturnsProperValues(
        string $plausibleSiteId,
        string $timeFrame,
        array $filters,
        ?array $endpointData,
        array $expected
    ): void {
        $plausibleServiceProphecy = $this->prophesize(PlausibleService::class);

        $this->languageServiceProphecy->getLL('barChart.labels.visitors')->willReturn('Visitors');
        $this->languageServiceProphecy->getLL('barChart.labels.browser')->willReturn('Browser');
        $this->languageServiceProphecy->getLL('barChart.labels.browserVersion')->willReturn('${browser} version');
        $this->languageServiceProphecy->getLL('filter.deviceData.browserIs')->willReturn('Browser is');
        $this->languageServiceProphecy->getLL('filter.deviceData.browserVersionIs')->willReturn('${browser} version is');

        $plausibleServiceProphecy->filtersToPlausibleFilterString([['name' => 'visit:browser==firefox']])->willReturn('visit:browser==firefox');
        $plausibleServiceProphecy->filtersToPlausibleFilterString([])->willReturn('');
        $plausibleServiceProphecy->isFilterActivated('visit:browser', [['name' => 'visit:browser==firefox']])->willReturn(['name' => 'visit:browser==firefox']);
        $plausibleServiceProphecy->isFilterActivated('visit:browser', [])->willReturn(null);

        $authorizedRequestParams = [
            'site_id' => $plausibleSiteId,
            'period' => $timeFrame,
            'property' => $filters ? 'visit:browser_version' : 'visit:browser',
            'metrics' => 'visitors',
        ];
        if ($filters) {
            $authorizedRequestParams['filters'] = 'visit:browser==firefox';
        }

        $plausibleServiceProphecy->sendAuthorizedRequest(
            $plausibleSiteId,
            'api/v1/stats/breakdown?',
            $authorizedRequestParams
        )
        ->willReturn($endpointData)
        ->shouldBeCalled();

        $subject = new DeviceDataProvider($plausibleServiceProphecy->reveal());
        self::assertSame($expected, $subject->getBrowserData($plausibleSiteId, $timeFrame, $filters));
    }

    public function getOSDataReturnsProperValuesDataProvider(): \Generator
    {
        yield 'all items are transformed' => [
            'plausibleSiteId' => 'waldhacker.dev',
            'timeFrame' => '7d',
            'filters' => [],
            'endpointData' => [
                ['os' => 'Windows', 'visitors' => 32],
                ['os' => 'Linux', 'visitors' => 48],
            ],
            'expected' => [
                'data' => [
                    ['os' => 'Windows', 'visitors' => 32, 'percentage' => 40.0],
                    ['os' => 'Linux', 'visitors' => 48, 'percentage' => 60.0],
                ],
                'columns' => [
                    [
                        'name' => 'os',
                        'label' => 'Operating system',
                        'filter' => [
                            'name' => 'visit:os',
                            'label' => 'Operating system is',
                        ],
                    ],
                    [
                        'name' => 'visitors',
                        'label' => 'Visitors',
                    ],
                ],
            ],
        ];

        yield 'all items are transformed with filter' => [
            'plausibleSiteId' => 'waldhacker.dev',
            'timeFrame' => '7d',
            'filters' => [
                ['name' => 'visit:os==Mac'],
            ],
            'endpointData' => [
                ['os_version' => '10.15', 'visitors' => 32],
                ['os_version' => '10.11', 'visitors' => 48],
            ],
            'expected' => [
                'data' => [
                    ['os_version' => '10.15', 'visitors' => 32, 'percentage' => 40.0],
                    ['os_version' => '10.11', 'visitors' => 48, 'percentage' => 60.0],
                ],
                'columns' => [
                    [
                        'name' => 'os_version',
                        'label' => '${os} version',
                        'filter' => [
                            'name' => 'visit:os_version',
                            'label' => '${os} version is',
                        ],
                    ],
                    [
                        'name' => 'visitors',
                        'label' => 'Visitors',
                    ],
                ],
            ],
        ];

        yield 'items without os are ignored' => [
            'plausibleSiteId' => 'waldhacker.dev',
            'timeFrame' => '7d',
            'filters' => [],
            'endpointData' => [
                ['os' => 'Windows', 'visitors' => 5],
                ['os' => '', 'visitors' => 15],
                ['visitors' => 4],
            ],
            'expected' => [
                'data' => [
                    ['os' => 'Windows', 'visitors' => 5, 'percentage' => 25.0],
                    ['os' => '', 'visitors' => 15, 'percentage' => 75.0],
                ],
                'columns' => [
                    [
                        'name' => 'os',
                        'label' => 'Operating system',
                        'filter' => [
                            'name' => 'visit:os',
                            'label' => 'Operating system is',
                        ],
                    ],
                    [
                        'name' => 'visitors',
                        'label' => 'Visitors',
                    ],
                ],
            ],
        ];

        yield 'items without visitors are ignored' => [
            'plausibleSiteId' => 'waldhacker.dev',
            'timeFrame' => '7d',
            'filters' => [],
            'endpointData' => [
                ['os' => 'Windows', 'visitors' => 3],
                ['os' => 'Linux', 'visitors' => null],
                ['os' => 'Linux'],
            ],
            'expected' => [
                'data' => [
                    ['os' => 'Windows', 'visitors' => 3, 'percentage' => 100],
                ],
                'columns' => [
                    [
                        'name' => 'os',
                        'label' => 'Operating system',
                        'filter' => [
                            'name' => 'visit:os',
                            'label' => 'Operating system is',
                        ],
                    ],
                    [
                        'name' => 'visitors',
                        'label' => 'Visitors',
                    ],
                ],
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getOSDataReturnsProperValuesDataProvider
     * @covers \Waldhacker\Plausibleio\Dashboard\DataProvider\DeviceDataProvider::__construct
     * @covers \Waldhacker\Plausibleio\Dashboard\DataProvider\DeviceDataProvider::getOSData
     * @covers \Waldhacker\Plausibleio\Dashboard\DataProvider\DeviceDataProvider::getData
     * @covers \Waldhacker\Plausibleio\Dashboard\DataProvider\DeviceDataProvider::calcPercentage
     * @covers \Waldhacker\Plausibleio\Dashboard\DataProvider\DeviceDataProvider::getLanguageService
     */
    public function getOSDataReturnsProperValues(
        string $plausibleSiteId,
        string $timeFrame,
        array $filters,
        ?array $endpointData,
        array $expected
    ): void {
        $plausibleServiceProphecy = $this->prophesize(PlausibleService::class);

        $this->languageServiceProphecy->getLL('barChart.labels.visitors')->willReturn('Visitors');
        $this->languageServiceProphecy->getLL('barChart.labels.os')->willReturn('Operating system');
        $this->languageServiceProphecy->getLL('barChart.labels.osVersion')->willReturn('${os} version');
        $this->languageServiceProphecy->getLL('filter.deviceData.osIs')->willReturn('Operating system is');
        $this->languageServiceProphecy->getLL('filter.deviceData.osVersionIs')->willReturn('${os} version is');

        $plausibleServiceProphecy->filtersToPlausibleFilterString([['name' => 'visit:os==Mac']])->willReturn('visit:os==Mac');
        $plausibleServiceProphecy->filtersToPlausibleFilterString([])->willReturn('');
        $plausibleServiceProphecy->isFilterActivated('visit:os', [['name' => 'visit:os==Mac']])->willReturn(['name' => 'visit:os==Mac']);
        $plausibleServiceProphecy->isFilterActivated('visit:os', [])->willReturn(null);

        $authorizedRequestParams = [
            'site_id' => $plausibleSiteId,
            'period' => $timeFrame,
            'property' => $filters ? 'visit:os_version' : 'visit:os',
            'metrics' => 'visitors',
        ];
        if ($filters) {
            $authorizedRequestParams['filters'] = 'visit:os==Mac';
        }

        $plausibleServiceProphecy->sendAuthorizedRequest(
            $plausibleSiteId,
            'api/v1/stats/breakdown?',
            $authorizedRequestParams
        )
        ->willReturn($endpointData)
        ->shouldBeCalled();

        $subject = new DeviceDataProvider($plausibleServiceProphecy->reveal());
        self::assertSame($expected, $subject->getOSData($plausibleSiteId, $timeFrame, $filters));
    }

    public function getDeviceDataReturnsProperValuesDataProvider(): \Generator
    {
        yield 'all items are transformed' => [
            'plausibleSiteId' => 'waldhacker.dev',
            'timeFrame' => '7d',
            'filters' => [],
            'endpointData' => [
                ['device' => 'Tablet', 'visitors' => 3],
                ['device' => 'Desktop', 'visitors' => 9],
            ],
            'expected' => [
                'data' => [
                    ['device' => 'Tablet', 'visitors' => 3, 'percentage' => 25.0],
                    ['device' => 'Desktop', 'visitors' => 9, 'percentage' => 75.0],
                ],
                'columns' => [
                    [
                        'name' => 'device',
                        'label' => 'Screen Size',
                        'filter' => [
                            'name' => 'visit:device',
                            'label' => 'Screen size is',
                        ],
                    ],
                    [
                        'name' => 'visitors',
                        'label' => 'Visitors',
                    ],
                ],
            ],
        ];

        yield 'all items are transformed with filter' => [
            'plausibleSiteId' => 'waldhacker.dev',
            'timeFrame' => '7d',
            'filters' => [
                ['name' => 'visit:device==Desktop'],
            ],
            'endpointData' => [
                ['device' => 'Desktop', 'visitors' => 3],
                ['device' => 'Desktop', 'visitors' => 9],
            ],
            'expected' => [
                'data' => [
                    ['device' => 'Desktop', 'visitors' => 3, 'percentage' => 25.0],
                    ['device' => 'Desktop', 'visitors' => 9, 'percentage' => 75.0],
                ],
                'columns' => [
                    [
                        'name' => 'device',
                        'label' => 'Screen Size',
                        'filter' => [
                            'name' => 'visit:device',
                            'label' => 'Screen size is',
                        ],
                    ],
                    [
                        'name' => 'visitors',
                        'label' => 'Visitors',
                    ],
                ],
            ],
        ];

        yield 'items without device are ignored' => [
            'plausibleSiteId' => 'waldhacker.dev',
            'timeFrame' => '7d',
            'filters' => [],
            'endpointData' => [
                ['device' => 'Tablet', 'visitors' => 9],
                ['device' => '', 'visitors' => 3],
                ['visitors' => 4],
            ],
            'expected' => [
                'data' => [
                    ['device' => 'Tablet', 'visitors' => 9, 'percentage' => 75.0],
                    ['device' => '', 'visitors' => 3, 'percentage' => 25.0],
                ],
                'columns' => [
                    [
                        'name' => 'device',
                        'label' => 'Screen Size',
                        'filter' => [
                            'name' => 'visit:device',
                            'label' => 'Screen size is',
                        ],
                    ],
                    [
                        'name' => 'visitors',
                        'label' => 'Visitors',
                    ],
                ],
            ],
        ];

        yield 'items without visitors are ignored' => [
            'plausibleSiteId' => 'waldhacker.dev',
            'timeFrame' => '7d',
            'filters' => [],
            'endpointData' => [
                ['device' => 'Tablet', 'visitors' => 3],
                ['device' => 'Desktop', 'visitors' => null],
                ['device' => 'Desktop'],
            ],
            'expected' => [
                'data' => [['device' => 'Tablet', 'visitors' => 3, 'percentage' => 100]],
                'columns' => [
                    [
                        'name' => 'device',
                        'label' => 'Screen Size',
                        'filter' => [
                            'name' => 'visit:device',
                            'label' => 'Screen size is',
                        ],
                    ],
                    [
                        'name' => 'visitors',
                        'label' => 'Visitors',
                    ],
                ],
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getDeviceDataReturnsProperValuesDataProvider
     * @covers \Waldhacker\Plausibleio\Dashboard\DataProvider\DeviceDataProvider::__construct
     * @covers \Waldhacker\Plausibleio\Dashboard\DataProvider\DeviceDataProvider::getDeviceData
     * @covers \Waldhacker\Plausibleio\Dashboard\DataProvider\DeviceDataProvider::getData
     * @covers \Waldhacker\Plausibleio\Dashboard\DataProvider\DeviceDataProvider::getLanguageService
     * @covers \Waldhacker\Plausibleio\Dashboard\DataProvider\DeviceDataProvider::calcPercentage
     */
    public function getDeviceDataReturnsProperValues(
        string $plausibleSiteId,
        string $timeFrame,
        array $filters,
        ?array $endpointData,
        array $expected
    ): void {
        $plausibleServiceProphecy = $this->prophesize(PlausibleService::class);

        $this->languageServiceProphecy->getLL('barChart.labels.visitors')->willReturn('Visitors');
        $this->languageServiceProphecy->getLL('barChart.labels.screenSize')->willReturn('Screen Size');
        $this->languageServiceProphecy->getLL('filter.deviceData.screenSizeIs')->willReturn('Screen size is');

        $plausibleServiceProphecy->filtersToPlausibleFilterString([['name' => 'visit:device==Desktop']])->willReturn('visit:device==Desktop');
        $plausibleServiceProphecy->filtersToPlausibleFilterString([])->willReturn('');

        $authorizedRequestParams = [
            'site_id' => $plausibleSiteId,
            'period' => $timeFrame,
            'property' => 'visit:device',
            'metrics' => 'visitors',
        ];
        if ($filters) {
            $authorizedRequestParams['filters'] = 'visit:device==Desktop';
        }

        $plausibleServiceProphecy->sendAuthorizedRequest(
            $plausibleSiteId,
            'api/v1/stats/breakdown?',
            $authorizedRequestParams
        )
        ->willReturn($endpointData)
        ->shouldBeCalled();

        $subject = new DeviceDataProvider($plausibleServiceProphecy->reveal());
        self::assertSame($expected, $subject->getDeviceData($plausibleSiteId, $timeFrame, $filters));
    }

    /**
     * @test
     * @covers \Waldhacker\Plausibleio\Dashboard\DataProvider\DeviceDataProvider::__construct
     * @covers \Waldhacker\Plausibleio\Dashboard\DataProvider\DeviceDataProvider::calcPercentage
     */
    public function calcPercentageReturnsProperValue()
    {
        $plausibleServiceProphecy = $this->prophesize(PlausibleService::class);
        $subject = new DeviceDataProvider($plausibleServiceProphecy->reveal());

        self::assertSame(
            [
                ['device' => 'Tablet', 'visitors' => 3, 'percentage' => 25.0],
                ['device' => 'Desktop', 'visitors' => 9, 'percentage' => 75.0],
            ],
            $subject->calcPercentage([
                ['device' => 'Tablet', 'visitors' => 3,],
                ['device' => 'Desktop', 'visitors' => 9,],
            ])
        );
    }
}
